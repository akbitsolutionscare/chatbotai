<?php
/**
 * Wras_Loader
 *
 * @package           WRAS
 * @author            Jules
 * @copyright         2024
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main loader class for the plugin.
 *
 * Defines the plugin name, version, and hooks.
 *
 * @since      1.0.0
 * @package    WRAS
 * @author     Jules
 */
class Wras_Loader {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'woocommerce-reseller-affiliate-system';
		$this->version     = WRAS_VERSION;

		$this->load_dependencies();
		$this->set_locale();
		$this->init_handlers(); // Initialize handlers like order handler
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Initialize handlers that manage their own hooks.
	 * @since 1.0.0
	 * @access private
	 */
	private function init_handlers() {
		new Wras_Order_Handler();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wras_Activator. Defines activate and deactivation hooks.
	 * - Wras_i18n. Defines internationalization functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {
		require_once WRAS_PLUGIN_DIR . 'includes/class-wras-activator.php';
		require_once WRAS_PLUGIN_DIR . 'includes/class-wras-deactivator.php';
		require_once WRAS_PLUGIN_DIR . 'includes/class-wras-i18n.php';
		require_once WRAS_PLUGIN_DIR . 'includes/class-wras-order-handler.php';

		// Admin specific functionality
		require_once WRAS_PLUGIN_DIR . 'admin/class-wras-admin-product-meta.php';
		require_once WRAS_PLUGIN_DIR . 'admin/class-wras-admin-earnings-page.php';

		// Public specific functionality
		require_once WRAS_PLUGIN_DIR . 'public/class-wras-reseller-dashboard.php';
		require_once WRAS_PLUGIN_DIR . 'public/class-wras-affiliate-handler.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wras_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new Wras_i18n();
		add_action( 'plugins_loaded', array( $plugin_i18n, 'load_plugin_textdomain' ) );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		if ( is_admin() ) { // Ensure these only run in admin
			$plugin_admin_product_meta = new Wras_Admin_Product_Meta();
			// Hooks are in Wras_Admin_Product_Meta constructor

			$plugin_admin_earnings_page = new Wras_Admin_Earnings_Page();
			// Hooks are in Wras_Admin_Earnings_Page constructor
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public_dashboard = new Wras_Reseller_Dashboard();
		// The Wras_Reseller_Dashboard class handles its own shortcode registration in its constructor.

		$plugin_affiliate_handler = new Wras_Affiliate_Handler();
		// The Wras_Affiliate_Handler class handles its own hooks in its constructor.
	}


	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		// Actions and filters are added by the define_admin_hooks and define_public_hooks methods.
        // If using a central hook registry/manager, its run method would be called here.
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
