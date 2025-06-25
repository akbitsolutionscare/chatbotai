<?php
/**
 * Affiliate Link Handler for WRAS
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
 * Class Wras_Affiliate_Handler.
 *
 * Handles processing of affiliate_token, storing it in session,
 * and overriding product prices based on the token.
 */
class Wras_Affiliate_Handler {

	const SESSION_KEY = 'wras_affiliate_data';

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'process_affiliate_token_from_url' ), 20 ); // Run after WC session init

		// Price override hooks
		add_filter( 'woocommerce_product_get_price', array( $this, 'override_product_price' ), 99, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'override_product_price' ), 99, 2 );
		// For variations:
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'override_product_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', array( 'override_product_price' ), 99, 2 );

        // Sale price hooks (important if a product is already on sale)
        add_filter( 'woocommerce_product_get_sale_price', array( $this, 'override_product_price'), 99, 2 );
        add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'override_product_price'), 99, 2 );

		// Cart item price display (ensures correct display in cart)
		add_filter( 'woocommerce_cart_item_price', array( $this, 'override_cart_item_price_display' ), 99, 3 );

		// Ensure calculations in cart use the overridden price
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'ensure_cart_item_price_override' ), 99, 1 );

        // Clear token if product is removed from cart or cart is emptied (optional, but good for UX)
        add_action( 'woocommerce_remove_cart_item', array( $this, 'maybe_clear_token_on_cart_item_removed'), 10, 2 );
        add_action( 'woocommerce_cart_emptied', array( $this, 'clear_affiliate_data_from_session' ) );

	}

	/**
	 * Process 'affiliate_token' from URL and store in WC session.
	 */
	public function process_affiliate_token_from_url() {
		if ( isset( $_GET['affiliate_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['affiliate_token'] ) );

			if ( empty( $token ) ) {
				return;
			}

			global $wpdb;
			$table_name = wras_get_affiliate_links_table_name();
			// Note: Tokens should be unique. If multiple products share a token (not by design), this gets the first.
			$link_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE token = %s", $token ) );

			if ( $link_data ) {
				// Validate if product still exists and is purchasable
				$product = wc_get_product( $link_data->product_id );
				if ( ! $product || ! $product->is_purchasable() ) {
					$this->clear_affiliate_data_from_session(); // Invalid product association
                    if ( !is_admin() && function_exists('wc_add_notice') ) { // Avoid notices in admin context
                        // Example: wc_add_notice( esc_html__( 'The affiliate link is for a product that is no longer available.', 'woocommerce-reseller-affiliate-system' ), 'error' );
                    }
					return;
				}

				// Store relevant data in session
				$session_data = array(
					'token'                     => $link_data->token,
					'product_id'                => (int) $link_data->product_id,
					'reseller_id'               => (int) $link_data->reseller_id,
					'profit_on_link'            => (float) $link_data->profit, // This is the "Additional Profit" from the link table
					'reseller_discount_percent' => (float) $link_data->reseller_discount_percent,
                    'admin_discounted_base_price' => wras_get_product_discounted_base_price( $link_data->product_id ), // Pre-calculate for efficiency
				);

                // Calculate the actual customer price and adjusted profit based on link data
                if ($session_data['admin_discounted_base_price'] !== null) {
                    $base = $session_data['admin_discounted_base_price'];
                    $reseller_disc_amt = ($base * $session_data['reseller_discount_percent']) / 100;

                    $session_data['customer_final_price'] = $base + $session_data['profit_on_link'] - $reseller_disc_amt;
                    $session_data['adjusted_profit_for_reseller'] = $session_data['profit_on_link'] - $reseller_disc_amt;

                    if ($session_data['customer_final_price'] < 0) {
                        // This should ideally be caught at link generation, but double check
                        // Invalidate the link if it results in negative price
                        $this->clear_affiliate_data_from_session();
                        // error_log("WRAS: Affiliate link {$token} resulted in negative customer price. Invalidating.");
                        return;
                    }

                } else {
                    // Could not determine base price, invalidate
                     $this->clear_affiliate_data_from_session();
                    // error_log("WRAS: Could not determine admin discounted base price for product ID {$link_data->product_id} with token {$token}.");
                    return;
                }


				if ( WC()->session ) {
					WC()->session->set( self::SESSION_KEY, $session_data );
                    // Optional: Add a notice that affiliate pricing is active
                    // if ( function_exists('wc_add_notice') && ! wc_has_notice( esc_html__('Affiliate pricing applied.', 'woocommerce-reseller-affiliate-system'), 'success' ) ) {
                    //    wc_add_notice( esc_html__( 'Special affiliate pricing has been applied for the selected product.', 'woocommerce-reseller-affiliate-system' ), 'success' );
                    //}
				}

                // Redirect to remove the token from URL to prevent re-application on refresh, if desired.
                // This can sometimes interfere with other plugins or WC notices. Test carefully.
                // $current_url = remove_query_arg( 'affiliate_token' );
                // wp_safe_redirect( $current_url );
                // exit;

			} else {
				// Token not found or invalid
				$this->clear_affiliate_data_from_session();
                if ( !is_admin() && function_exists('wc_add_notice') ) { // Avoid notices in admin context
                    // Example: wc_add_notice( esc_html__( 'Invalid or expired affiliate link.', 'woocommerce-reseller-affiliate-system' ), 'error' );
                }
			}
		}
	}

	/**
	 * Override product prices based on affiliate data in session.
	 *
	 * @param float      $price   Original price.
	 * @param WC_Product $product Product object.
	 * @return float Modified price.
	 */
	public function override_product_price( $price, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) { // Don't run in admin unless it's an AJAX request from frontend
			return $price;
		}

        if ( WC()->session === null || ! WC()->session->has_session() ) {
            return $price; // No session active
        }

		$affiliate_data = WC()->session->get( self::SESSION_KEY );

		if ( ! empty( $affiliate_data ) && isset( $affiliate_data['product_id'] ) &&
		     $product->get_id() == $affiliate_data['product_id'] && isset($affiliate_data['customer_final_price']) ) {

            // This hook is called for 'price', 'regular_price', 'sale_price'.
            // We want to return the single calculated customer_final_price for all of them
            // when the affiliate link is active for this product.
            // This effectively makes the product not "on sale" in the traditional WC sense,
            // but rather sets its actual price.

            // If the current filter is for 'sale_price', returning our custom price here
            // will make WooCommerce think it's a sale price.
            // If the current filter is for 'regular_price', it will be the regular price.
            // If it's for 'price', it will be the active price.
            // By returning customer_final_price for all, we simplify.

            return (float) $affiliate_data['customer_final_price'];
		}
		return $price;
	}

    /**
     * Ensure the correct price is displayed in the cart.
     * This is needed because the cart might sometimes cache or re-fetch prices.
     *
     * @param string $price_html Formatted price string.
     * @param array  $cart_item  Cart item data.
     * @param string $cart_item_key Cart item key.
     * @return string Modified price HTML string.
     */
    public function override_cart_item_price_display( $price_html, $cart_item, $cart_item_key ) {
        if ( WC()->session === null || ! WC()->session->has_session() ) {
            return $price_html;
        }
        $affiliate_data = WC()->session->get( self::SESSION_KEY );

        if ( ! empty( $affiliate_data ) && isset( $affiliate_data['product_id'] ) &&
             isset( $cart_item['product_id'] ) && $cart_item['product_id'] == $affiliate_data['product_id'] &&
             isset($affiliate_data['customer_final_price']) ) {

            $_product = $cart_item['data']; // Get the WC_Product object from cart item
            if ( $_product && $_product->is_taxable() ) {
                // If prices include tax, use wc_price with the tax-inclusive price
                if (wc_prices_include_tax()) {
                    $display_price = wc_get_price_including_tax($_product, array('price' => $affiliate_data['customer_final_price']));
                } else {
                // If prices exclude tax, use wc_price with the tax-exclusive price
                    $display_price = wc_get_price_excluding_tax($_product, array('price' => $affiliate_data['customer_final_price']));
                }
            } else {
                 $display_price = $affiliate_data['customer_final_price'];
            }
            return wc_price( $display_price );
        }
        return $price_html;
    }


	/**
	 * Ensures that the cart item's price is set to the affiliate price before totals are calculated.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function ensure_cart_item_price_override( $cart_obj ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

        if ( WC()->session === null || ! WC()->session->has_session() ) {
            return; // No session active
        }

		$affiliate_data = WC()->session->get( self::SESSION_KEY );

		if ( empty( $affiliate_data ) || !isset($affiliate_data['product_id']) || !isset($affiliate_data['customer_final_price']) ) {
			return;
		}

		foreach ( $cart_obj->get_cart() as $key => $item ) {
			if ( $item['product_id'] == $affiliate_data['product_id'] ) {
				// $item['data'] is the WC_Product object
				$item['data']->set_price( (float) $affiliate_data['customer_final_price'] );
			}
		}
	}

    /**
     * If the affiliate product is removed from cart, clear the session data.
     */
    public function maybe_clear_token_on_cart_item_removed( $cart_item_key, $cart ) {
        if ( WC()->session === null || ! WC()->session->has_session() ) {
            return;
        }
        $affiliate_data = WC()->session->get( self::SESSION_KEY );
        if ( ! empty( $affiliate_data ) && isset( $affiliate_data['product_id'] ) ) {
            $removed_item = $cart->get_removed_cart_contents()[$cart_item_key] ?? null;
            if ($removed_item && $removed_item['product_id'] == $affiliate_data['product_id']) {
                // Check if any other instance of this product (with this token) remains in cart
                $is_affiliate_product_still_in_cart = false;
                foreach ($cart->get_cart() as $item) {
                    if ($item['product_id'] == $affiliate_data['product_id']) {
                        $is_affiliate_product_still_in_cart = true;
                        break;
                    }
                }
                if (!$is_affiliate_product_still_in_cart) {
                    $this->clear_affiliate_data_from_session();
                }
            }
        }
    }

	/**
	 * Clear affiliate data from session.
	 */
	public function clear_affiliate_data_from_session() {
		if ( WC()->session ) {
			WC()->session->__unset( self::SESSION_KEY );
		}
	}
}
