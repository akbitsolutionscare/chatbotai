<?php
/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WRAS
 * @subpackage WRAS/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WRAS
 * @subpackage WRAS/includes
 * @author     Jules
 */
class Wras_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;

		// Create custom table
		$table_name      = wras_get_affiliate_links_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			token VARCHAR(64) NOT NULL,
			reseller_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			profit DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
			reseller_discount_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
			created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY reseller_id (reseller_id),
			KEY product_id (product_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Add reseller role
		add_role(
			'reseller',
			__( 'Reseller', 'woocommerce-reseller-affiliate-system' ),
			array(
				'read'         => true, // True allows that capability
				'edit_posts'   => false,
				'delete_posts' => false,
				// Add other capabilities as needed, e.g., upload_files if they need to manage media for their profile
			)
		);

		// Add a flag that activation has run
        update_option('wras_activated_once', true);
	}
}
