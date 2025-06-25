<?php
/**
 * Admin Reseller Earnings Page for WRAS
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WRAS
 * @subpackage WRAS/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Wras_Admin_Earnings_Page.
 *
 * Handles the admin page for viewing reseller earnings.
 */
class Wras_Admin_Earnings_Page {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_earnings_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'export_earnings_csv' ) );
	}

	/**
	 * Add the admin menu item.
	 */
	public function add_earnings_admin_menu() {
		add_menu_page(
			esc_html__( 'Reseller Earnings', 'woocommerce-reseller-affiliate-system' ), // Page title
			esc_html__( 'Reseller Earnings', 'woocommerce-reseller-affiliate-system' ), // Menu title
			'manage_woocommerce', // Capability
			'wras_reseller_earnings', // Menu slug
			array( $this, 'render_earnings_page' ), // Function to display the page
			'dashicons-chart-line', // Icon
			56 // Position
		);
	}

	/**
	 * Render the earnings page content.
	 */
	public function render_earnings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reseller Affiliate Earnings', 'woocommerce-reseller-affiliate-system' ); // Changed title slightly for clarity ?></h1>
			<p><?php esc_html_e( 'This page displays earnings for resellers from completed orders. You can export this data to a CSV file.', 'woocommerce-reseller-affiliate-system' ); ?></p>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <?php wp_nonce_field( 'wras_export_earnings_nonce', '_wras_export_nonce' ); ?>
                <button type="submit" name="export_csv" value="true" class="button button-primary">
                    <?php esc_html_e( 'Export All Earnings to CSV', 'woocommerce-reseller-affiliate-system' ); ?>
                </button>
            </form>
            <br>

			<?php $this->display_earnings_table(); ?>
		</div>
		<?php
	}

	/**
	 * Query and display the earnings data in a table.
	 */
	private function display_earnings_table() {
		$earnings_data = $this->get_earnings_data();

		if ( empty( $earnings_data['items'] ) ) {
			echo '<p>' . esc_html__( 'No reseller earnings have been recorded yet.', 'woocommerce-reseller-affiliate-system' ) . '</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order ID', 'woocommerce-reseller-affiliate-system' ); ?></th>
					<th><?php esc_html_e( 'Order Date', 'woocommerce-reseller-affiliate-system' ); ?></th>
					<th><?php esc_html_e( 'Reseller Name', 'woocommerce-reseller-affiliate-system' ); ?></th>
                    <th><?php esc_html_e( 'Reseller Email', 'woocommerce-reseller-affiliate-system' ); ?></th>
					<th><?php esc_html_e( 'Product', 'woocommerce-reseller-affiliate-system' ); ?></th>
                    <th class="short"><?php esc_html_e( 'Qty', 'woocommerce-reseller-affiliate-system' ); ?></th>
					<th><?php esc_html_e( 'Admin Base (Item)', 'woocommerce-reseller-affiliate-system' ); ?></th>
                    <th><?php esc_html_e( 'Reseller Added Profit (Item)', 'woocommerce-reseller-affiliate-system' ); ?></th>
                    <th class="short"><?php esc_html_e( 'Reseller Disc. %', 'woocommerce-reseller-affiliate-system' ); ?></th>
					<th><?php esc_html_e( 'Net Profit (Item)', 'woocommerce-reseller-affiliate-system' ); ?></th>
                    <th><?php esc_html_e( 'Net Profit (Line Total)', 'woocommerce-reseller-affiliate-system' ); ?></th>
					<th><?php esc_html_e( 'Customer Paid (Item)', 'woocommerce-reseller-affiliate-system' ); ?></th>
                    <th><?php esc_html_e( 'Affiliate Token', 'woocommerce-reseller-affiliate-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $earnings_data['items'] as $item ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $item['order_id'] ) ); ?>" title="<?php /* translators: %s: Order ID */ printf(esc_attr__('View Order #%s', 'woocommerce-reseller-affiliate-system'), $item['order_id']); ?>"><?php echo esc_html( $item['order_id'] ); ?></a></td>
						<td><?php echo esc_html( $item['order_date'] ); ?></td>
						<td><?php echo esc_html( $item['reseller_name'] ); ?></td>
                        <td><a href="<?php echo esc_url( 'mailto:' . $item['reseller_email'] ); ?>"><?php echo esc_html( $item['reseller_email'] ); ?></a></td>
						<td>
                            <?php if($item['product_id']): ?>
                                <a href="<?php echo esc_url(get_edit_post_link($item['product_id'])); ?>" title="<?php /* translators: %s: Product Name */ printf(esc_attr__('Edit Product: %s', 'woocommerce-reseller-affiliate-system'), $item['product_name']); ?>">
                                    <?php echo esc_html( $item['product_name'] ); ?>
                                </a>
                                (ID: <?php echo esc_html($item['product_id']); ?>)
                            <?php else: ?>
                                <?php echo esc_html( $item['product_name'] ); ?> <?php esc_html_e('(Product Deleted)', 'woocommerce-reseller-affiliate-system'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $item['quantity'] ); ?></td>
						<td><?php echo wc_price( $item['admin_discounted_base_price'] ); ?></td>
                        <td><?php echo wc_price( $item['profit_on_link'] ); ?></td>
                        <td><?php echo esc_html( $item['reseller_discount_percent'] ); ?>%</td>
						<td><?php echo wc_price( $item['adjusted_profit_per_item'] ); ?></td>
                        <td><?php echo wc_price( $item['total_adjusted_profit_for_line'] ); ?></td>
						<td><?php echo wc_price( $item['customer_paid_per_item'] ); ?></td>
                        <td><?php echo esc_html( $item['affiliate_token'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="10" style="text-align:right;"><?php esc_html_e( 'Grand Total Reseller Earnings:', 'woocommerce-reseller-affiliate-system' ); ?></th>
					<th colspan="1"><?php echo wc_price( $earnings_data['grand_total_reseller_profit'] ); ?></th>
                    <th colspan="2"></th>
				</tr>
			</tfoot>
		</table>
        <style>.short { width: 50px; }</style> <?php // Quick style for narrow columns, better in CSS file ?>
		<?php
	}

	/**
	 * Retrieves earnings data from completed orders.
	 * @return array An array containing 'items' and 'grand_total_reseller_profit'.
	 */
	private function get_earnings_data() {
		$earnings = array(
            'items' => array(),
            'grand_total_reseller_profit' => 0,
        );

		$orders_query_args = array(
			'post_type'   => 'shop_order',
			'post_status' => array('wc-completed'), // Only completed orders
			'posts_per_page' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
		);

		$orders_query = new WP_Query( $orders_query_args );

		if ( $orders_query->have_posts() ) {
			foreach ( $orders_query->get_posts() as $order_post ) {
				$order = wc_get_order( $order_post->ID );
				if ( ! $order ) {
					continue;
				}

				foreach ( $order->get_items() as $item_id => $order_item ) {
					if ( ! $order_item instanceof WC_Order_Item_Product ) {
						continue;
					}

					$reseller_id = $order_item->get_meta( Wras_Order_Handler::META_RESELLER_ID );
					$adjusted_profit_per_item = $order_item->get_meta( Wras_Order_Handler::META_ADJUSTED_PROFIT );

					if ( $reseller_id && $adjusted_profit_per_item !== null ) {
						$reseller_user = get_userdata( $reseller_id );
						if ( ! $reseller_user ) {
							continue; // Reseller account might have been deleted
						}

                        $admin_discounted_base_price = (float) $order_item->get_meta( Wras_Order_Handler::META_ADMIN_DISCOUNTED_BASE_PRICE );
                        $profit_on_link = (float) $order_item->get_meta( Wras_Order_Handler::META_PROFIT_ON_LINK );
                        $reseller_discount_percent = (float) $order_item->get_meta( Wras_Order_Handler::META_RESELLER_DISCOUNT_PERCENT );
                        $quantity = $order_item->get_quantity();
                        $total_adjusted_profit_for_line = (float) $adjusted_profit_per_item * $quantity;

                        // Calculate customer paid price for this item based on stored meta
                        // Customer Paid Price = Admin Discounted Base Price + Profit on Link (Reseller's Additional Profit) - (Admin Discounted Base Price * Reseller Discount %)
                        $customer_paid_per_item = $admin_discounted_base_price + $profit_on_link - ($admin_discounted_base_price * $reseller_discount_percent / 100);


						$earnings['items'][] = array(
							'order_id'             => $order->get_id(),
							'order_date'           => $order->get_date_created()->date_i18n( get_option( 'date_format' ) ),
							'reseller_name'        => $reseller_user->display_name,
                            'reseller_email'       => $reseller_user->user_email,
							'product_id'           => $order_item->get_product_id(),
							'product_name'         => $order_item->get_name(),
                            'quantity'             => $quantity,
							'admin_discounted_base_price' => $admin_discounted_base_price,
                            'profit_on_link'       => $profit_on_link, // This is the "Additional Profit" set by reseller
                            'reseller_discount_percent' => $reseller_discount_percent,
							'adjusted_profit_per_item' => (float) $adjusted_profit_per_item, // This is profit_on_link - reseller_discount_value
                            'total_adjusted_profit_for_line' => $total_adjusted_profit_for_line,
							'customer_paid_per_item' => $customer_paid_per_item, // Calculated
                            'affiliate_token'      => $order_item->get_meta( Wras_Order_Handler::META_AFFILIATE_TOKEN ),
						);
						$earnings['grand_total_reseller_profit'] += $total_adjusted_profit_for_line;
					}
				}
			}
		}
		wp_reset_postdata();
		return $earnings;
	}

	/**
	 * Handle CSV export.
	 */
	public function export_earnings_csv() {
		if ( ! isset( $_GET['export_csv'] ) || $_GET['export_csv'] !== 'true' ) { // Corrected strict comparison
			return;
		}

        // Verify nonce for security
        if ( ! isset( $_GET['_wras_export_nonce'] ) || ! wp_verify_nonce( sanitize_key($_GET['_wras_export_nonce']), 'wras_export_earnings_nonce' ) ) {
            wp_die( esc_html__( 'Security check failed. Please try exporting again from the Reseller Earnings page.', 'woocommerce-reseller-affiliate-system' ), esc_html__( 'Nonce Verification Failed', 'woocommerce-reseller-affiliate-system' ), array( 'response' => 403 ) );
        }

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have the required permissions to export this data.', 'woocommerce-reseller-affiliate-system' ), esc_html__( 'Permission Denied', 'woocommerce-reseller-affiliate-system' ), array( 'response' => 403 ) );
		}

		$earnings_data = $this->get_earnings_data();
		if ( empty( $earnings_data['items'] ) ) {
            // Add an admin notice instead of dying, then redirect or let the page load.
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('There is no earnings data available to export.', 'woocommerce-reseller-affiliate-system') . '</p></div>';
            });
			return;
		}

		$filename = 'reseller-earnings-' . date_i18n( 'Y-m-d' ) . '.csv'; // Use date_i18n for localized date format

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );

		// Add headers
		fputcsv( $output, array(
			esc_html__( 'Order ID', 'woocommerce-reseller-affiliate-system' ),
			esc_html__( 'Order Date', 'woocommerce-reseller-affiliate-system' ),
			esc_html__( 'Reseller Name', 'woocommerce-reseller-affiliate-system' ),
            esc_html__( 'Reseller Email', 'woocommerce-reseller-affiliate-system' ),
			esc_html__( 'Product ID', 'woocommerce-reseller-affiliate-system' ),
			esc_html__( 'Product Name', 'woocommerce-reseller-affiliate-system' ),
            esc_html__( 'Quantity', 'woocommerce-reseller-affiliate-system' ),
			esc_html__( 'Admin Base Price (Item)', 'woocommerce-reseller-affiliate-system' ), // Simplified Header
            esc_html__( 'Reseller Added Profit (Item)', 'woocommerce-reseller-affiliate-system' ), // Simplified Header
            esc_html__( 'Reseller Discount %', 'woocommerce-reseller-affiliate-system' ), // Simplified Header
			esc_html__( 'Net Profit (Item)', 'woocommerce-reseller-affiliate-system' ), // Simplified Header
            esc_html__( 'Net Profit (Line Total)', 'woocommerce-reseller-affiliate-system' ), // Simplified Header
			esc_html__( 'Customer Paid (Item)', 'woocommerce-reseller-affiliate-system' ),
            esc_html__( 'Affiliate Token', 'woocommerce-reseller-affiliate-system' ),
		) );

		// Add data
		foreach ( $earnings_data['items'] as $item ) {
			fputcsv( $output, array(
				$item['order_id'],
				$item['order_date'],
				$item['reseller_name'],
                $item['reseller_email'],
				$item['product_id'],
				$item['product_name'],
                $item['quantity'],
				$item['admin_discounted_base_price'],
                $item['profit_on_link'],
                $item['reseller_discount_percent'],
				$item['adjusted_profit_per_item'],
                $item['total_adjusted_profit_for_line'],
				$item['customer_paid_per_item'],
                $item['affiliate_token'],
			) );
		}

        // Optionally add total row
        fputcsv($output, array()); // Blank line
        fputcsv($output, array(
            '', '', '', '', '', '', '', '', '', '',
            esc_html__('Grand Total Reseller Earnings', 'woocommerce-reseller-affiliate-system'),
            $earnings_data['grand_total_reseller_profit'], // This is a number, not needing translation itself
            '', ''
        ));


		fclose( $output );
		exit;
	}
}
