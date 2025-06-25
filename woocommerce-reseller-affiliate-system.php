<?php
/**
 * Plugin Name: WooCommerce Reseller Affiliate System
 * Plugin URI:  https://example.com/woocommerce-reseller-affiliate-system
 * Description: Allows creation of reseller affiliate links with custom discounts and profit margins for WooCommerce products.
 * Version:     1.0.0
 * Author:      Jules
 * Author URI:  https://example.com
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-reseller-affiliate-system
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'WRAS_VERSION', '1.0.0' );
define( 'WRAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WRAS_PLUGIN_FILE', __FILE__ );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require WRAS_PLUGIN_DIR . 'includes/class-wras-loader.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wras_loader() {
	$plugin = new Wras_Loader();
	$plugin->run();
}
run_wras_loader();

/**
 * Activation and Deactivation Hooks.
 */
register_activation_hook( __FILE__, 'activate_wras_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_wras_plugin' );

/**
 * The code that runs during plugin activation.
 */
function activate_wras_plugin() {
	require_once WRAS_PLUGIN_DIR . 'includes/class-wras-activator.php';
	Wras_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wras_plugin() {
	require_once WRAS_PLUGIN_DIR . 'includes/class-wras-deactivator.php';
	Wras_Deactivator::deactivate();
}

/**
 * Check if WooCommerce is active.
 */
function wras_is_woocommerce_active() {
	if ( class_exists( 'WooCommerce' ) ) {
		return true;
	}
	return false;
}

/**
 * Admin notice if WooCommerce is not active.
 */
function wras_woocommerce_not_active_notice() {
	if ( ! wras_is_woocommerce_active() ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e( 'WooCommerce Reseller Affiliate System requires WooCommerce to be activated to function. Please install and activate WooCommerce.', 'woocommerce-reseller-affiliate-system' ); ?></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'wras_woocommerce_not_active_notice' );

// Helper function to get the table name
function wras_get_affiliate_links_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'reseller_affiliate_links';
}
