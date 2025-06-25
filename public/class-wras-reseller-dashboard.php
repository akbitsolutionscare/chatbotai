<?php
/**
 * Reseller Dashboard for WRAS
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WRAS
 * @subpackage WRAS/public
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Wras_Reseller_Dashboard.
 *
 * Handles the [reseller_dashboard] shortcode and its content.
 */
class Wras_Reseller_Dashboard {

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_shortcode( 'reseller_dashboard', array( $this, 'render_dashboard' ) );
		add_action( 'wp_ajax_wras_generate_affiliate_link', array( $this, 'ajax_generate_affiliate_link' ) );
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) ); // Removed
	}

	// Removed enqueue_dashboard_assets method

	/**
	 * Render the reseller dashboard.
	 *
	 * @return string The HTML content for the dashboard.
	 */
	public function render_dashboard() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view the reseller dashboard.', 'woocommerce-reseller-affiliate-system' ) . '</p>';
		}

		$user = wp_get_current_user();
		if ( ! in_array( 'reseller', (array) $user->roles ) ) {
			return '<p>' . esc_html__( 'You are not authorized to view this dashboard. Please contact support if you believe this is an error.', 'woocommerce-reseller-affiliate-system' ) . '</p>';
		}

		// Start output buffering
		ob_start();

		$this->display_product_list_for_link_generation();
		$this->display_reseller_earnings_summary(); // For step 8, placeholder for now

        // Actual enqueueing moved here to ensure it only loads when shortcode is active
        if ( ! wp_script_is( 'wras-dashboard-js', 'enqueued' ) ) {
            wp_enqueue_script(
                'wras-dashboard-js',
                WRAS_PLUGIN_URL . 'assets/js/wras-dashboard.js',
                array( 'jquery' ),
                WRAS_VERSION,
                true
            );

            wp_localize_script(
                'wras-dashboard-js',
                'wras_dashboard_params',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'wras_generate_link_nonce' ),
                    'currency_symbol' => get_woocommerce_currency_symbol(),
                    'price_decimals' => wc_get_price_decimals(),
                    'i18n_generating_link' => esc_html__( 'Generating...', 'woocommerce-reseller-affiliate-system' ),
                    'i18n_error_generating_link' => esc_html__( 'Error generating link.', 'woocommerce-reseller-affiliate-system' ),
                    'i18n_ajax_error' => esc_html__( 'AJAX Error: Could not generate link.', 'woocommerce-reseller-affiliate-system' ),
                    'i18n_invalid_profit_val' => esc_html__( 'Invalid Additional Profit value.', 'woocommerce-reseller-affiliate-system' ),
                    'i18n_invalid_discount_val' => esc_html__( 'Invalid Reseller Discount value.', 'woocommerce-reseller-affiliate-system' ),
                )
            );
        }
        // Enqueue styles if any:
        // wp_enqueue_style('wras-dashboard-css', WRAS_PLUGIN_URL . 'assets/css/wras-dashboard.css', array(), WRAS_VERSION);


		return ob_get_clean();
	}

	/**
	 * Display the list of products for link generation.
	 */
	private function display_product_list_for_link_generation() {
		?>
		<div class="wras-dashboard-section">
			<h2><?php esc_html_e( 'Generate Affiliate Links', 'woocommerce-reseller-affiliate-system' ); ?></h2>
			<p><?php esc_html_e( 'Here you can set your additional profit and a percentage discount for your customers on any product. A unique affiliate link will be generated for you to share.', 'woocommerce-reseller-affiliate-system' ); ?></p>
		<?php

		$products_query_args = array(
			'post_type'   => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1, // Get all products
			'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_price', // Ensure product has a price
					'value'   => 0,
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
			'orderby' => 'title',
			'order' => 'ASC',
		);

		$products_loop = new WP_Query( $products_query_args );

		if ( $products_loop->have_posts() ) :
			?>
			<table class="wras-product-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product Name', 'woocommerce-reseller-affiliate-system' ); ?></th>
						<th><?php esc_html_e( 'Original Price', 'woocommerce-reseller-affiliate-system' ); ?></th>
						<th><?php esc_html_e( 'Admin Discount', 'woocommerce-reseller-affiliate-system' ); ?></th>
						<th><?php esc_html_e( 'Your Base Price', 'woocommerce-reseller-affiliate-system' ); ?></th>
						<th><?php esc_html_e( 'Your Additional Profit', 'woocommerce-reseller-affiliate-system' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</th>
						<th><?php esc_html_e( 'Customer Discount', 'woocommerce-reseller-affiliate-system' ); ?> (%)</th>
						<th><?php esc_html_e( 'Your Net Profit', 'woocommerce-reseller-affiliate-system' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</th>
						<th><?php esc_html_e( 'Customer Price', 'woocommerce-reseller-affiliate-system' ); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)</th>
						<th><?php esc_html_e( 'Generate Link', 'woocommerce-reseller-affiliate-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					while ( $products_loop->have_posts() ) :
						$products_loop->the_post();
						global $product;
						if ( ! is_a( $product, 'WC_Product' ) ) {
							continue;
						}

                        // Skip product if it's variable and has no purchasable variations
                        if ($product->is_type('variable') && !$product->has_child()) {
                            continue;
                        }

                        if ($product->is_type('simple') || $product->is_type('variation')) {
                            $this->render_product_row($product);
                        } elseif ($product->is_type('variable')) {
                            $variations = $product->get_available_variations();
                            if (empty($variations)) continue;

                            echo '<tr class="wras-variable-product-group-header"><td colspan="9"><strong>' . esc_html( $product->get_name() ) . '</strong> - ' . esc_html__('(Select a variation below)', 'woocommerce-reseller-affiliate-system') . '</td></tr>';
                            foreach ($variations as $variation_data) {
                                $variation_obj = wc_get_product($variation_data['variation_id']);
                                if ($variation_obj && $variation_obj->is_purchasable() && $variation_obj->get_price() !== '') {
                                    $this->render_product_row($variation_obj, true);
                                }
                            }
                        }
					endwhile;
					wp_reset_postdata();
					?>
				</tbody>
			</table>
		<?php
		else :
			echo '<p>' . esc_html__( 'No products are currently available for link generation.', 'woocommerce-reseller-affiliate-system' ) . '</p>';
		endif;
		?>
		</div>
		<?php
	}

    /**
     * Renders a single product row in the table.
     * @param WC_Product $product_obj The product or variation object.
     * @param bool $is_variation Whether this is a variation.
     */
    private function render_product_row(WC_Product $product_obj, $is_variation = false) {
        $product_id = $product_obj->get_id();
        $product_name = $product_obj->get_name();

        // For variations, include attribute details in the name
        if ($is_variation && $product_obj->is_type('variation')) {
            $product_name = wc_get_formatted_variation( $product_obj, true, true, false );
        }


        $original_base_price = (float) $product_obj->get_regular_price();
        if (empty($original_base_price)) { // If regular price is empty, try price
             $original_base_price = (float) $product_obj->get_price();
        }


        $admin_discount_percent = (float) $product_obj->get_meta( Wras_Admin_Product_Meta::META_KEY, true );
        $discounted_base_price = wras_get_product_discounted_base_price( $product_obj );

        if ($discounted_base_price === null || $discounted_base_price === '') {
            // If product is not purchasable or has no price, skip it.
            // This can happen if wras_get_product_discounted_base_price returns null for parent variable product
            // or if a variation has no price set.
            // error_log("Skipping product ID {$product_id} ({$product_name}) due to null/empty discounted base price.");
            return;
        }
        ?>
        <tr class="wras-product-row" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-discounted-base-price="<?php echo esc_attr( $discounted_base_price ); ?>">
            <td><?php echo esc_html( $product_name ); ?></td>
            <td><?php echo wc_price( $original_base_price ); ?></td>
            <td><?php echo esc_html( $admin_discount_percent ); ?>%</td>
            <td class="wras-discounted-base-price-display"><?php echo wc_price( $discounted_base_price ); ?></td>
            <td>
                <input type="number" class="wras-additional-profit" name="wras_additional_profit[<?php echo esc_attr( $product_id ); ?>]" value="0" min="0" max="800" step="any" style="width:100px;">
            </td>
            <td>
                <input type="number" class="wras-reseller-discount-percent" name="wras_reseller_discount_percent[<?php echo esc_attr( $product_id ); ?>]" value="0" min="0" max="100" step="any" style="width:100px;">
            </td>
            <td class="wras-adjusted-profit-display"><?php echo wc_price(0); ?></td>
            <td class="wras-customer-final-price-display"><?php echo wc_price( $discounted_base_price ); ?></td>
            <td>
                <button type="button" class="button button-primary wras-generate-link-button" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                    <?php esc_html_e( 'Generate Link', 'woocommerce-reseller-affiliate-system' ); ?>
                </button>
                <div class="wras-generated-link-display" style="margin-top:5px; display:none;">
                    <input type="text" readonly class="wras-affiliate-url" style="width:100%; padding: 5px;" aria-label="<?php esc_attr_e('Generated Affiliate Link', 'woocommerce-reseller-affiliate-system'); ?>">
                </div>
                 <div class="wras-link-generation-spinner" style="display:none; margin-top:5px;"><?php esc_html_e('Generating...', 'woocommerce-reseller-affiliate-system'); ?></div>
                 <div class="wras-link-generation-error" style="display:none; color:red; margin-top:5px;"></div>
            </td>
        </tr>
        <?php
    }


	/**
	 * Display reseller earnings summary, past sales, and total earnings.
	 */
	private function display_reseller_earnings_summary() {
		$reseller_id = get_current_user_id();
		$earnings_data = $this->get_reseller_sales_data( $reseller_id );
		?>
		<div class="wras-dashboard-section" style="margin-top: 30px;">
			<h2><?php esc_html_e( 'Your Earnings Summary', 'woocommerce-reseller-affiliate-system' ); ?></h2>

			<?php if ( empty( $earnings_data['items'] ) ) : ?>
				<p><?php esc_html_e( 'You have no sales recorded yet.', 'woocommerce-reseller-affiliate-system' ); ?></p>
			<?php else : ?>
				<p>
					<strong><?php esc_html_e( 'Total Earnings:', 'woocommerce-reseller-affiliate-system' ); ?></strong>
					<?php echo wc_price( $earnings_data['total_earnings_for_reseller'] ); ?>
				</p>
				<h3><?php esc_html_e( 'Your Past Sales:', 'woocommerce-reseller-affiliate-system' ); ?></h3>
				<table class="wras-reseller-sales-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order ID', 'woocommerce-reseller-affiliate-system' ); ?></th>
							<th><?php esc_html_e( 'Order Date', 'woocommerce-reseller-affiliate-system' ); ?></th>
							<th><?php esc_html_e( 'Product Name', 'woocommerce-reseller-affiliate-system' ); ?></th>
                            <th><?php esc_html_e( 'Quantity', 'woocommerce-reseller-affiliate-system' ); ?></th>
							<th><?php esc_html_e( 'Your Earning (Line)', 'woocommerce-reseller-affiliate-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $earnings_data['items'] as $item ) : ?>
							<tr>
								<td>#<?php echo esc_html( $item['order_id'] ); ?></td>
								<td><?php echo esc_html( $item['order_date'] ); ?></td>
								<td><?php echo esc_html( $item['product_name'] ); ?></td>
                                <td><?php echo esc_html( $item['quantity'] ); ?></td>
								<td><?php echo wc_price( $item['reseller_profit_for_line'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Retrieves sales data for a specific reseller from completed orders.
	 * @param int $reseller_id The ID of the reseller.
	 * @return array An array containing 'items' and 'total_earnings_for_reseller'.
	 */
	private function get_reseller_sales_data( $reseller_id ) {
		$sales_data = array(
            'items' => array(),
            'total_earnings_for_reseller' => 0,
        );

		if ( ! $reseller_id ) {
			return $sales_data;
		}

        // Query completed orders
		$orders_query_args = array(
			'post_type'   => 'shop_order',
			'post_status' => array('wc-completed'),
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

					$item_reseller_id = $order_item->get_meta( Wras_Order_Handler::META_RESELLER_ID );

					if ( $item_reseller_id == $reseller_id ) { // Check if this item belongs to the current reseller
						$adjusted_profit_per_item = $order_item->get_meta( Wras_Order_Handler::META_ADJUSTED_PROFIT );
						if ( $adjusted_profit_per_item !== null ) {
                            $quantity = $order_item->get_quantity();
                            $profit_for_line = (float) $adjusted_profit_per_item * $quantity;

							$sales_data['items'][] = array(
								'order_id'        => $order->get_id(),
								'order_date'      => $order->get_date_created()->date_i18n( get_option( 'date_format' ) ),
								'product_name'    => $order_item->get_name(),
                                'quantity'        => $quantity,
								'reseller_profit_for_line' => $profit_for_line,
							);
							$sales_data['total_earnings_for_reseller'] += $profit_for_line;
						}
					}
				}
			}
		}
		wp_reset_postdata();
        // Sort items by date descending, as WP_Query was on order date, but items are processed per order
        // This might not be strictly necessary if orders are already DESC and items within order don't need sorting beyond that.
        // However, if multiple items from different orders could interleave due to processing, explicit sort is safer.
        usort($sales_data['items'], function($a, $b) {
            // Primary sort by order date (desc), secondary by product name (asc) if dates are same
            if ($a['order_date'] == $b['order_date']) {
                return strcmp($a['product_name'], $b['product_name']);
            }
            return strtotime($b['order_date']) - strtotime($a['order_date']);
        });
		return $sales_data;
	}

	/**
	 * AJAX handler for generating an affiliate link.
	 */
	public function ajax_generate_affiliate_link() {
		check_ajax_referer( 'wras_generate_link_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'User not logged in. Please log in and try again.', 'woocommerce-reseller-affiliate-system' ) ) );
		}

		$user = wp_get_current_user();
		if ( ! in_array( 'reseller', (array) $user->roles ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'User not authorized. You must be a reseller to perform this action.', 'woocommerce-reseller-affiliate-system' ) ) );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$additional_profit = isset( $_POST['additional_profit'] ) ? (float) $_POST['additional_profit'] : 0;
		$reseller_discount_percent = isset( $_POST['reseller_discount_percent'] ) ? (float) $_POST['reseller_discount_percent'] : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid product ID provided.', 'woocommerce-reseller-affiliate-system' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Product not found. It may have been removed.', 'woocommerce-reseller-affiliate-system' ) ) );
		}

        if ( ! $product->is_purchasable() || $product->get_price() === '' ) {
            wp_send_json_error( array( 'message' => esc_html__( 'This product is not currently purchasable or has no price defined.', 'woocommerce-reseller-affiliate-system' ) ) );
        }


		// Validate inputs
		if ( $additional_profit < 0 || $additional_profit > 800 ) {
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Additional Profit must be between %s and %s.', 'woocommerce-reseller-affiliate-system' ), '0', '800' ) ) );
		}
		if ( $reseller_discount_percent < 0 || $reseller_discount_percent > 100 ) {
			wp_send_json_error( array( 'message' => sprintf( esc_html__( 'Reseller Discount Percent must be between %s and %s.', 'woocommerce-reseller-affiliate-system' ), '0', '100' ) ) );
		}

        // Check if customer final price would be negative, which indicates an issue.
        // This logic should mirror the JS, but as a server-side validation.
        $discounted_base_price = wras_get_product_discounted_base_price($product_id);
        if ($discounted_base_price === null) {
            wp_send_json_error( array( 'message' => esc_html__( 'Could not calculate the discounted base price for the product. Please check product settings.', 'woocommerce-reseller-affiliate-system' ) ) );
        }
        $reseller_discount_amount = ($discounted_base_price * $reseller_discount_percent) / 100;
        $customer_final_price = $discounted_base_price + $additional_profit - $reseller_discount_amount;

        if ($customer_final_price < 0) {
            wp_send_json_error( array( 'message' => esc_html__( 'The customer final price cannot be negative. Please adjust your additional profit or customer discount values.', 'woocommerce-reseller-affiliate-system' ) ) );
        }


		global $wpdb;
		$table_name = wras_get_affiliate_links_table_name();
		$reseller_id = get_current_user_id();

		// Generate a secure token
		try {
			$token = bin2hex( random_bytes( 16 ) ); // 32 characters hex
		} catch ( Exception $e ) {
			// Fallback for environments where random_bytes is not available (highly unlikely for modern PHP)
			$token = wp_generate_password( 32, false, false );
		}


		$data = array(
			'token'                     => $token,
			'reseller_id'               => $reseller_id,
			'product_id'                => $product_id,
			'profit'                    => $additional_profit, // This is the "Additional Profit" set by reseller
			'reseller_discount_percent' => $reseller_discount_percent,
			'created_at'                => current_time( 'mysql', 1 ),
		);
		$format = array( '%s', '%d', '%d', '%f', '%f', '%s' );

		$result = $wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			// Log error: $wpdb->last_error
            error_log("WRAS: Failed to insert affiliate link into DB. Error: " . $wpdb->last_error . " Data: " . print_r($data, true));
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save affiliate link to the database. Please try again or contact support if the issue persists.', 'woocommerce-reseller-affiliate-system' ) ) );
		}

		// Generate shareable URL
		// Option 1: Link to product page with token
		$product_url = $product->get_permalink();
		$shareable_url = add_query_arg( 'affiliate_token', $token, $product_url );

		// Option 2: Generic link with token and product_id (if not linking directly to product page)
		// $shareable_url = add_query_arg( array(
		// 'affiliate_token' => $token,
		// 'product_id' => $product_id // Useful if the token itself doesn't identify the product contextually
		// ), site_url('/') ); // Or a specific landing page

		wp_send_json_success( array( 'url' => $shareable_url, 'token' => $token ) );
	}
}
