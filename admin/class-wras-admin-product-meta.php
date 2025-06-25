<?php
/**
 * Admin Product Meta for WRAS
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
 * Class Wras_Admin_Product_Meta.
 *
 * Handles the custom "Admin Discount (%)" field in the WooCommerce product edit screen.
 */
class Wras_Admin_Product_Meta {

	const META_KEY = '_wras_admin_discount_percent';

	/**
	 * Initialize hooks.
	 */
	public function __construct() {
		// Add custom field to product data meta box (General tab)
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_admin_discount_field' ) );

		// Save custom field
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_admin_discount_field' ) );
        // For variations too
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_admin_discount_field_variable' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_admin_discount_field_variable' ), 10, 2 );


        // HPOS compatibility for saving meta
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'hpos_save_admin_discount_field' ) );
        add_action( 'woocommerce_admin_process_variation_object', array( $this, 'hpos_save_admin_discount_field_variation' ), 10, 2 );

	}

	/**
	 * Add "Admin Discount (%)" field to the General tab for simple products.
	 */
	public function add_admin_discount_field() {
		global $product_object;

        if ( $product_object && $product_object->is_type('variable') ) {
            // This field will be handled per-variation for variable products
            return;
        }

		woocommerce_wp_text_input(
			array(
				'id'          => self::META_KEY,
				'label'       => esc_html__( 'Admin Discount (%)', 'woocommerce-reseller-affiliate-system' ),
				'placeholder' => esc_html__( 'Enter discount percentage e.g. 10', 'woocommerce-reseller-affiliate-system' ),
				'desc_tip'    => true,
				'description' => esc_html__( 'Enter a discount percentage (0-100) for the base price available to resellers. This discount is applied before any reseller specific margins or discounts.', 'woocommerce-reseller-affiliate-system' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => 'any',
					'min'  => '0',
					'max'  => '100',
				),
			)
		);
	}

    /**
     * Add "Admin Discount (%)" field for variable products (each variation).
     */
    public function add_admin_discount_field_variable( $loop, $variation_data, $variation_post ) {
        $variation = wc_get_product($variation_post->ID);
        $current_value = $variation->get_meta(self::META_KEY, true);

        ?>
        <tr>
            <td>
                <?php
                woocommerce_wp_text_input(
                    array(
                        'id'            => self::META_KEY . '_' . $loop, // Unique ID for each variation
                        'name'          => self::META_KEY . '[' . $loop . ']', // Name for saving
                        'value'         => esc_attr( $current_value ),
                        'label'         => esc_html__( 'Admin Discount (%)', 'woocommerce-reseller-affiliate-system' ),
                        'placeholder'   => esc_html__( 'e.g. 10', 'woocommerce-reseller-affiliate-system' ),
                        'desc_tip'      => true,
                        'description'   => esc_html__( 'Admin discount for this variation.', 'woocommerce-reseller-affiliate-system' ),
                        'type'          => 'number',
                        'custom_attributes' => array(
                            'step' => 'any',
                            'min'  => '0',
                            'max'  => '100',
                        ),
                        'wrapper_class' => 'form-row form-row-full',
                    )
                );
                ?>
            </td>
        </tr>
        <?php
    }


	/**
	 * Save the "Admin Discount (%)" field value for simple products.
	 *
	 * @param int $post_id The ID of the product being saved.
	 */
	public function save_admin_discount_field( $post_id ) {
        $product = wc_get_product( $post_id );
        if ( ! $product || $product->is_type( 'variable' ) ) {
            // Handled by save_admin_discount_field_variable or hpos_save_admin_discount_field_variation for variations
            // Or by hpos_save_admin_discount_field for simple products with HPOS
            if ( ! ( defined('WC_ADMIN_ENABLE_TRACKING') && WC_ADMIN_ENABLE_TRACKING && get_option( 'woocommerce_custom_orders_table_enabled' ) === 'yes' ) ){
                 // Only proceed if not HPOS or not variable
            } else {
                return; // HPOS will handle it
            }
        }


		$discount_value = isset( $_POST[ self::META_KEY ] ) ? wc_clean( wp_unslash( $_POST[ self::META_KEY ] ) ) : '';

		if ( $discount_value !== '' ) {
			if ( ! is_numeric( $discount_value ) || $discount_value < 0 || $discount_value > 100 ) {
				// Optionally, add an admin notice about invalid input
                if (class_exists('WC_Admin_Meta_Boxes')) {
				    WC_Admin_Meta_Boxes::add_error( esc_html__( 'Admin Discount (%) must be a number between 0 and 100.', 'woocommerce-reseller-affiliate-system' ) );
                }
				$discount_value = ''; // Reset if invalid
			} else {
				$discount_value = (float) $discount_value;
			}
		}
        if ($product && !$product->is_type('variable')) { // Ensure it's a product object and not variable
		    $product->update_meta_data( self::META_KEY, $discount_value );
		    // $product->save(); // Save is called by WC after this hook for non-HPOS
        }
	}

    /**
	 * Save the "Admin Discount (%)" field value for variations.
	 *
	 * @param int $variation_id The ID of the variation being saved.
     * @param int $i Loop index.
	 */
    public function save_admin_discount_field_variable( $variation_id, $i ) {
        if ( defined('WC_ADMIN_ENABLE_TRACKING') && WC_ADMIN_ENABLE_TRACKING && get_option( 'woocommerce_custom_orders_table_enabled' ) === 'yes' ) {
            return; // HPOS will handle it via woocommerce_admin_process_variation_object
        }

        $variation = wc_get_product( $variation_id );
        if ( ! $variation ) {
            return;
        }

        $discount_value = isset( $_POST[self::META_KEY][$i] ) ? wc_clean( wp_unslash( $_POST[self::META_KEY][$i] ) ) : '';

        if ( $discount_value !== '' ) {
            if ( ! is_numeric( $discount_value ) || $discount_value < 0 || $discount_value > 100 ) {
                // Error handling can be added here if necessary
                $discount_value = ''; // Reset if invalid
            } else {
                $discount_value = (float) $discount_value;
            }
        }
        $variation->update_meta_data( self::META_KEY, $discount_value );
        // $variation->save(); // Save is called by WC after this hook
    }

    /**
     * HPOS-compatible save for simple products.
     *
     * @param WC_Product $product Product object.
     */
    public function hpos_save_admin_discount_field( $product ) {
        if ( $product->is_type( 'variable' ) ) {
            return; // Handled by variation specific method
        }

        if ( isset( $_POST[ self::META_KEY ] ) ) {
            $discount_value = wc_clean( wp_unslash( $_POST[ self::META_KEY ] ) );
            if ( $discount_value !== '' ) {
                if ( ! is_numeric( $discount_value ) || $discount_value < 0 || $discount_value > 100 ) {
                    // WC_Admin_Meta_Boxes::add_error is not available here directly.
                    // Consider a different way to show errors if needed, or rely on frontend validation.
                    $discount_value = '';
                } else {
                    $discount_value = (float) $discount_value;
                }
            }
            $product->update_meta_data( self::META_KEY, $discount_value );
        }
    }

    /**
     * HPOS-compatible save for variations.
     *
     * @param WC_Product_Variation $variation Variation object.
     * @param array                $data      Posted data for the variation (not always available/needed here).
     */
    public function hpos_save_admin_discount_field_variation( $variation, $data = null) {
        // For variations, the data might be in $_POST directly indexed by variation ID or loop index.
        // We need to find the correct POST key.
        // The 'woocommerce_product_after_variable_attributes' hook provides $loop.
        // We need to check how 'woocommerce_admin_process_variation_object' gets the data or if we need to iterate $_POST.

        // Assuming $_POST[self::META_KEY] is an array keyed by loop index from add_admin_discount_field_variable
        // This part might need adjustment based on how WC passes variation data in HPOS context.
        // Let's assume we find the relevant discount value from the $_POST array.
        // This often requires matching based on `$_POST['variable_post_id'][$i]` to `variation->get_id()`.

        // A common pattern for variations is to look for `variable_meta_key[variation_id]` or loop through `$_POST['variable_post_id']`
        // For simplicity, let's assume the field name was `_wras_admin_discount_percent[LOOP_INDEX]` in the form.
        // We need to find which loop index corresponds to this $variation object.
        // This is tricky without the loop index ($i) directly.

        // Fallback: Check if a general POST variable for this meta key exists for variations.
        // Often, `$_POST` is structured like `variable_meta_key[$loop_index_or_variation_id]`.
        // Let's find the post data for this specific variation.
        // WooCommerce typically iterates through `$_POST['variable_post_id']` and calls this hook for each.
        // So, the `$_POST` should contain the data for the *current* variation being processed in a loop by WC.

        $posted_meta_values = isset($_POST[self::META_KEY]) ? $_POST[self::META_KEY] : null;

        if (is_array($posted_meta_values)) {
            // Find the correct value for this variation.
            // This requires knowing the $loop index or having a unique identifier in the POST data.
            // Let's try to find it by matching the variation ID if `variable_post_id` is available.
            $variation_id = $variation->get_id();
            $discount_value_for_variation = '';

            if (isset($_POST['variable_post_id'])) {
                $variable_post_ids = $_POST['variable_post_id']; // array of variation IDs
                $key_index = array_search($variation_id, $variable_post_ids);

                if ($key_index !== false && isset($posted_meta_values[$key_index])) {
                     $discount_value_for_variation = wc_clean(wp_unslash($posted_meta_values[$key_index]));
                }
            }


            if ( $discount_value_for_variation !== '' ) {
                if ( ! is_numeric( $discount_value_for_variation ) || $discount_value_for_variation < 0 || $discount_value_for_variation > 100 ) {
                    $discount_value_for_variation = '';
                } else {
                    $discount_value_for_variation = (float) $discount_value_for_variation;
                }
            }
             $variation->update_meta_data( self::META_KEY, $discount_value_for_variation );
        } else if (isset($_POST[self::META_KEY . '_' . $variation->get_id()])) {
            // Alternative if field was named uniquely with ID: _wras_admin_discount_percent_VARIATION_ID
            // This was not how add_admin_discount_field_variable was set up (it used loop index)
            // This is a fallback, the array method above is more likely with current field setup.
            // $discount_value = wc_clean( wp_unslash( $_POST[ self::META_KEY . '_' . $variation->get_id() ] ) );
            // ... validation ...
            // $variation->update_meta_data( self::META_KEY, $discount_value );
        }
    }
}

/**
 * Helper function to get the discounted base price.
 *
 * @param int|WC_Product $product_or_id Product ID or WC_Product object.
 * @return float|null The discounted price, or null if product not found or price not set.
 */
function wras_get_product_discounted_base_price( $product_or_id ) {
	$product = wc_get_product( $product_or_id );

	if ( ! $product ) {
		return null;
	}

	$original_price = $product->get_price( 'edit' ); // Get raw price
    if ($original_price === '' || $original_price === null) {
        // Try regular price if sale price isn't set or product is not on sale
        $original_price = $product->get_regular_price('edit');
    }

    // If it's a variable product, this function might not be appropriate directly.
    // The discount is per variation. This function would need a variation ID for variable products.
    // For now, let's assume it's called with a simple product or a specific variation product object.
    if ( $product->is_type( 'variable' ) && ! $product->is_type( 'variation' ) ) {
        // This function should ideally be called with a variation ID/object for variable products.
        // For a parent variable product, returning a discounted price is ambiguous.
        // Returning null or average/min might be options, but per-variation is correct.
        // For now, let's return null to indicate it needs specific variation handling.
        // error_log("wras_get_product_discounted_base_price called with parent variable product ID: " . $product->get_id());
        return null;
    }


	if ( $original_price === '' || $original_price === null ) {
		return null; // No base price to discount.
	}

	$original_price = (float) $original_price;
	$admin_discount_percent = (float) $product->get_meta( Wras_Admin_Product_Meta::META_KEY, true );

	if ( $admin_discount_percent > 0 && $admin_discount_percent <= 100 ) {
		$discount_amount = ( $original_price * $admin_discount_percent ) / 100;
		return round( $original_price - $discount_amount, wc_get_price_decimals() );
	}

	return $original_price; // No discount or invalid discount.
}
