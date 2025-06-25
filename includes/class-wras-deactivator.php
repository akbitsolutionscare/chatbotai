<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    WRAS
 * @subpackage WRAS/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    WRAS
 * @subpackage WRAS/includes
 * @author     Jules
 */
class Wras_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		// Remove reseller role
		// Consider adding an option to not remove this, or to not remove data.
		// For now, we remove the role as per requirements.
		if ( get_role( 'reseller' ) ) {
			remove_role( 'reseller' );
		}

		// Code to remove custom table could go here, but it's often better to leave data
		// unless specifically requested by the user (e.g., via an "uninstall.php" script or a plugin setting).
		// For now, we will not remove the table on deactivation, only the role.
		// To remove the table:
		// global $wpdb;
		// $table_name = wras_get_affiliate_links_table_name();
		// $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		// Clear any scheduled cron jobs if added later
		// wp_clear_scheduled_hook('wras_custom_cron_hook');

        // Remove options
        // delete_option('wras_some_setting');
        // delete_option('wras_activated_once'); // Example if you want to re-run activation
	}

}
