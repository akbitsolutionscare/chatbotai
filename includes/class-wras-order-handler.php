<?php
/**
 * Order Handler for WRAS
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WRAS
 * @subpackage WRAS/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Wras_Order_Handler.
 *
 * Handles adding meta to order items for affiliate sales
 * and sending notifications.
 */
class Wras_Order_Handler {

	const AFFILIATE_SESSION_KEY = 'wras_affiliate_data'; // Same as in Wras_Affiliate_Handler
	const META_RESELLER_ID = '_wras_reseller_id';
	const META_ADJUSTED_PROFIT = '_wras_adjusted_profit_for_reseller'; // Profit for reseller after their discount
    const META_AFFILIATE_TOKEN = '_wras_affiliate_token';
    const META_PROFIT_ON_LINK = '_wras_profit_on_link'; // The "Additional Profit" value from the link
    const META_RESELLER_DISCOUNT_PERCENT = '_wras_reseller_discount_percent';
    const META_ADMIN_DISCOUNTED_BASE_PRICE = '_wras_admin_discounted_base_price';


	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		// Add meta to order line items when they are created
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_affiliate_meta_to_order_item' ), 10, 4 );

		// Send notifications on order completion (or processing, depending on needs)
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_order_completed_notifications' ), 10, 1 );
		// Or use: add_action( 'woocommerce_order_status_processing', array( $this, 'handle_order_completed_notifications' ), 10, 1 );

        // Clear session after checkout is processed
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'clear_affiliate_session_after_order' ), 20, 1 );

	}

	/**
	 * Add affiliate meta to order line item if it's an affiliate sale.
	 *
	 * @param WC_Order_Item_Product $item          Order item object.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 */
	public function add_affiliate_meta_to_order_item( $item, $cart_item_key, $values, $order ) {
		if ( WC()->session === null || ! WC()->session->has_session() ) {
            return;
        }
		$affiliate_data = WC()->session->get( self::AFFILIATE_SESSION_KEY );

		if ( ! empty( $affiliate_data ) && isset( $affiliate_data['product_id'] ) &&
		     $item->get_product_id() == $affiliate_data['product_id'] ) {

			// Ensure all expected data points are present in session
            $required_keys = ['reseller_id', 'adjusted_profit_for_reseller', 'token', 'profit_on_link', 'reseller_discount_percent', 'admin_discounted_base_price'];
            foreach ($required_keys as $key) {
                if (!isset($affiliate_data[$key])) {
                    // Missing critical data, log and abort meta saving for this item
                    error_log("WRAS Error: Missing '{$key}' in affiliate session data for product ID {$item->get_product_id()}. Order item meta not saved.");
                    return;
                }
            }

			$item->add_meta_data( self::META_RESELLER_ID, $affiliate_data['reseller_id'], true );
			$item->add_meta_data( self::META_ADJUSTED_PROFIT, $affiliate_data['adjusted_profit_for_reseller'], true );
            $item->add_meta_data( self::META_AFFILIATE_TOKEN, $affiliate_data['token'], true );
            // Store other details from the link for reporting/auditing
            $item->add_meta_data( self::META_PROFIT_ON_LINK, $affiliate_data['profit_on_link'], true );
            $item->add_meta_data( self::META_RESELLER_DISCOUNT_PERCENT, $affiliate_data['reseller_discount_percent'], true );
            $item->add_meta_data( self::META_ADMIN_DISCOUNTED_BASE_PRICE, $affiliate_data['admin_discounted_base_price'], true );

            // $item->save(); // WC saves the order item after this hook typically
		}
	}

	/**
	 * Handle notifications when an order is marked completed.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function handle_order_completed_notifications( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$reseller_notifications = array(); // Group items by reseller

		foreach ( $order->get_items() as $item_id => $item ) {
			$reseller_id = $item->get_meta( self::META_RESELLER_ID );
			$adjusted_profit = $item->get_meta( self::META_ADJUSTED_PROFIT );

			if ( $reseller_id && $adjusted_profit !== null ) { // Ensure profit is explicitly set (could be 0)
				if ( ! isset( $reseller_notifications[ $reseller_id ] ) ) {
					$reseller_user = get_userdata( $reseller_id );
					if ( ! $reseller_user ) continue; // Skip if reseller user not found

					$reseller_notifications[ $reseller_id ] = array(
						'email'        => $reseller_user->user_email,
						'name'         => $reseller_user->display_name,
						'items'        => array(),
						'total_earned' => 0,
					);
				}
                $profit_for_item = (float) $adjusted_profit * $item->get_quantity();
				$reseller_notifications[ $reseller_id ]['items'][] = array(
					'product_name' => $item->get_name(),
					'quantity'     => $item->get_quantity(),
					'profit'       => $profit_for_item,
				);
				$reseller_notifications[ $reseller_id ]['total_earned'] += $profit_for_item;
			}
		}

		// Send emails to resellers
		foreach ( $reseller_notifications as $reseller_id => $data ) {
			if ( $data['total_earned'] > 0 || apply_filters('wras_notify_reseller_on_zero_earning', false, $order, $reseller_id) ) { // Only send if actual earning or if filtered
				$this->send_reseller_notification_email( $data['email'], $data['name'], $order, $data['items'], $data['total_earned'] );
			}
		}

		// Send email to admin if any affiliate sales occurred
		if ( ! empty( $reseller_notifications ) ) {
			$this->send_admin_notification_email( $order, $reseller_notifications );
		}
	}

	/**
	 * Send notification email to reseller.
	 */
	private function send_reseller_notification_email( $reseller_email, $reseller_name, $order, $items, $total_earned ) {
		/* translators: %s: Order number */
		$subject = sprintf( esc_html__( 'You have earned a commission on order #%s!', 'woocommerce-reseller-affiliate-system' ), $order->get_order_number() );

		ob_start();
		?>
		<p><?php /* translators: %s: Reseller name */ printf( esc_html__( 'Hello %s,', 'woocommerce-reseller-affiliate-system' ), esc_html( $reseller_name ) ); ?></p>
		<p><?php /* translators: 1: Order number, 2: Order date */ printf( esc_html__( 'Congratulations! You have earned a commission from order #%1$s placed on %2$s.', 'woocommerce-reseller-affiliate-system' ), esc_html( $order->get_order_number() ), esc_html( $order->get_date_created()->date_i18n() ) ); ?></p>
		<p><?php esc_html_e( 'Order Details:', 'woocommerce-reseller-affiliate-system' ); ?></p>
		<ul>
			<?php foreach ( $items as $item ) : ?>
				<li><?php /* translators: 1: Product name, 2: Quantity, 3: Profit amount */ printf( esc_html__( '%1$s (Quantity: %2$d) - Your Earning: %3$s', 'woocommerce-reseller-affiliate-system' ), esc_html( $item['product_name'] ), esc_html( $item['quantity'] ), wc_price( $item['profit'] ) ); ?></li>
			<?php endforeach; ?>
		</ul>
		<p><strong><?php /* translators: %s: Total earned amount */ printf( esc_html__( 'Total Earning from this Order: %s', 'woocommerce-reseller-affiliate-system' ), wc_price( $total_earned ) ); ?></strong></p>
		<p><?php esc_html_e( 'Thank you for your partnership!', 'woocommerce-reseller-affiliate-system' ); ?></p>
		<p><a href="<?php echo esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) ); ?>"><?php esc_html_e( 'Visit your dashboard for more details.', 'woocommerce-reseller-affiliate-system' ); ?></a></p>
		<?php
		$message = ob_get_clean();

		// Prepare email headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        $from_email = apply_filters('wras_email_from_address', $admin_email);
        $from_name = apply_filters('wras_email_from_name', $site_name);
        $headers[] = "From: {$from_name} <{$from_email}>";

		wp_mail( $reseller_email, $subject, $message, $headers );
	}

	/**
	 * Send notification email to admin.
	 */
	private function send_admin_notification_email( $order, $reseller_notifications_data ) {
		$admin_email = get_option( 'admin_email' );
		/* translators: %s: Order number */
		$subject = sprintf( esc_html__( 'Affiliate Sale on Order #%s', 'woocommerce-reseller-affiliate-system' ), $order->get_order_number() );

		ob_start();
		?>
		<p><?php esc_html_e( 'An order with affiliate sales has been completed:', 'woocommerce-reseller-affiliate-system' ); ?></p>
		<p><?php /* translators: %s: Order number */ printf( esc_html__( 'Order Number: %s', 'woocommerce-reseller-affiliate-system' ), esc_html( $order->get_order_number() ) ); ?></p>
		<p><?php /* translators: %s: Order date */ printf( esc_html__( 'Order Date: %s', 'woocommerce-reseller-affiliate-system' ), esc_html( $order->get_date_created()->date_i18n() ) ); ?></p>
		<p><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>"><?php esc_html_e( 'View Order Details in Admin', 'woocommerce-reseller-affiliate-system'); ?></a></p>

		<h3><?php esc_html_e( 'Reseller Earnings Details:', 'woocommerce-reseller-affiliate-system' ); ?></h3>
		<?php foreach ( $reseller_notifications_data as $reseller_id => $data ) : ?>
			<h4><?php /* translators: 1: Reseller name, 2: Reseller ID */ printf( esc_html__( 'Reseller: %1$s (ID: %2$d)', 'woocommerce-reseller-affiliate-system' ), esc_html( $data['name'] ), esc_html( $reseller_id ) ); ?></h4>
			<ul>
				<?php foreach ( $data['items'] as $item ) : ?>
					<li><?php /* translators: 1: Product name, 2: Quantity, 3: Profit amount */ printf( esc_html__( 'Product: %1$s (Qty: %2$d) - Reseller Earning: %3$s', 'woocommerce-reseller-affiliate-system' ), esc_html( $item['product_name'] ), esc_html( $item['quantity'] ), wc_price( $item['profit'] ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><strong><?php /* translators: 1: Reseller name, 2: Total earned amount */ printf( esc_html__( 'Total Earning for %1$s in this order: %2$s', 'woocommerce-reseller-affiliate-system' ), esc_html( $data['name'] ), wc_price( $data['total_earned'] ) ); ?></strong></p>
			<hr/>
		<?php endforeach; ?>
		<p><?php esc_html_e( 'Please review the order and reseller earnings in the admin panel.', 'woocommerce-reseller-affiliate-system' ); ?></p>
		<?php
		$message = ob_get_clean();

		// Prepare email headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $site_name = get_bloginfo('name');
        // Use a dedicated "from" email for plugin if desired, or default to admin_email
        $from_email_address = apply_filters('wras_admin_email_from_address', get_option('admin_email'));
        $from_name = apply_filters('wras_admin_email_from_name', get_bloginfo('name'));
        $headers[] = "From: {$from_name} <{$from_email_address}>";

		wp_mail( $admin_email, $subject, $message, $headers );
	}

    /**
     * Clear affiliate data from session after order is processed.
     * This prevents the same token from being applied to a subsequent unrelated order
     * if the user navigates back or opens a new cart.
     *
     * @param int $order_id The ID of the order just processed.
     */
    public function clear_affiliate_session_after_order( $order_id ) {
        if ( WC()->session && WC()->session->has_session() && WC()->session->get( self::AFFILIATE_SESSION_KEY ) ) {
            WC()->session->__unset( self::AFFILIATE_SESSION_KEY );
            // error_log("WRAS: Affiliate session data cleared after order ID " . $order_id);
        }
    }
}
