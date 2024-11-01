<?php

/**
 * Plugin Name: Taxify for WooCommerce
 * Plugin URI: https://taxify.co/features/woocommerce/
 * Description: Taxify™ accurately calculates sales tax, and automatically prepares and files your returns.
 * Version: 1.2.8.1
 * Author: Taxify
 * Author URI: https://taxify.co/
 * Text Domain: taxify
 * Domain Path: /languages/
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.3.4
 *
 * @package     Taxify for WooCommerce
 * @author      Todd Lahman LLC
 * @uri         https://www.toddlahman.com/
 * @category    Plugin
 * @copyright   Copyright (c) Taxify. All rights reserved.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks if the plugin ready-to-run
 *
 * @since 1.0
 */
if ( WooCommerce_Taxify::ready_to_run() === false ) {
	return;
}

final class WooCommerce_Taxify {

	// For static ready_to_run()
	const WC_MIN_REQUIRED_VERSION = '3.0';

	/**
	 * @var string
	 */
	public $version                 = '1.2.8.1';
	public $wc_min_required_version = '3.0';
	public $taxify_api_endpoint     = 'https://ws.taxify.co/taxify/1.0/core/service.asmx?wsdl';
	public $taxify_partner_key      = 'C72CF3FA-5368-447A-9717-5810C66ECD36';
	public $taxify_api_key          = '';
	public $store_prefix            = '';
	public $taxify_rate_id          = '';
	public $wc_version              = '';
	public $text_domain             = 'woocommerce-taxify';
	public $taxify_plugin_file      = '';
	public $taxify_plugin_basename  = '';

	/**
	 * @var null
	 * @since 1.0
	 */
	private static $_instance = null;

	/**
	 * Singular class instance safeguard.
	 * Ensures only one instance of a class can be instantiated.
	 * Follows a singleton design pattern.
	 *
	 * @since 1.0
	 *
	 * @static
	 * @return null|\WooCommerce_Taxify
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0
	 */
	public function __clone() { }

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0
	 */
	public function __wakeup() { }

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->define_properties();
		$this->define_constants();
		// Include required files and hooks
		$this->includes();

		do_action( 'wc_taxify_loaded' );
	}

	/**
	 * Does ready-to-run checks
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public static function ready_to_run() {
		$wc_version     = defined( 'WC_VERSION' ) ? WC_VERSION : get_option( 'woocommerce_version' );
		$taxify_api_key = get_option( 'wc_taxify_api_key' );
		//$postcode       = get_option( 'wc_taxify_store_postcode' );

		include_once( 'includes/admin/wc-taxify-admin-messages.php' );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::woocommerce_disabled_message' );

			return false;
		}

		if ( ! version_compare( $wc_version, self::WC_MIN_REQUIRED_VERSION, '>=' ) ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::woocommerce_version_disabled_message' );

			return false;
		}

		if ( ! class_exists( 'SoapClient' ) ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::soap_client_disabled_message' );

			return false;
		}

		if ( empty( $taxify_api_key ) ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::activate_api_key_message' );
		}

		add_action( 'admin_notices', 'WC_Taxify_Admin_messages::check_external_blocking' );

		if ( get_option( 'wc_taxify_taxes_configured' ) != 'yes' ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::taxify_taxes_configured_message' );
		}

		if ( get_option( 'woocommerce_prices_include_tax' ) == 'yes' && get_option( 'woocommerce_calc_taxes' ) == 'yes' ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::prices_include_tax_message' );
		}

		if ( get_option( 'woocommerce_tax_based_on' ) == 'base' ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::tax_based_on_message' );
		}

		if ( $wc_version < '3.2' ) {
			add_action( 'admin_notices', 'WC_Taxify_Admin_messages::woocommerce_best_results' );
		}

		//if ( ! empty( $postcode ) ) {
		//	if ( ! self::is_postcode( $postcode, 'US' ) ) {
		//		add_action( 'admin_notices', 'WC_Taxify_Admin_messages::taxify_zipcode_wrong_format_message' );
		//	}
		//} else {
		//	add_action( 'admin_notices', 'WC_Taxify_Admin_messages::taxify_zipcode_empty_message' );
		//}

		return true;
	}

	/**
	 * Get the plugin's url.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function plugin_url() {
		return plugins_url( '/', __FILE__ );
	}

	/**
	 * Get the plugin directory url.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function plugins_dir_url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Get the plugin path.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get the plugin basename.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function plugins_basename() {
		return untrailingslashit( plugin_basename( __FILE__ ) );
	}

	/**
	 * Ajax URL
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function ajax_url() {
		return admin_url( 'admin-ajax.php' );
	}

	/**
	 * Define Constants
	 *
	 * @since 1.0
	 *
	 * @private
	 */
	private function define_constants() {
		$this->define( 'WC_TAXIFY_PLUGIN_FILE', __FILE__ );
		$this->define( 'WC_TAXIFY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
	}

	/**
	 * Define constant if not already set
	 *
	 * @since 1.0
	 *
	 * @private
	 *
	 * @param  string      $name
	 * @param  string|bool $value
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Defines the class properties
	 *
	 * @since 1.0
	 *
	 * @private
	 */
	private function define_properties() {
		$this->taxify_plugin_file     = __FILE__;
		$this->taxify_plugin_basename = plugin_basename( __FILE__ );
		$taxify_api_key               = get_option( 'wc_taxify_api_key' );
		$this->taxify_api_key         = ! empty( $taxify_api_key ) ? $taxify_api_key : '';
		$this->store_prefix           = $this->get_store_prefix();
		$this->wc_version             = defined( 'WC_VERSION' ) ? WC_VERSION : get_option( 'woocommerce_version' );
		$taxify_rate_id               = get_option( 'wc_taxify_rate_id' );
		$this->taxify_rate_id         = ! empty( $taxify_rate_id ) ? $taxify_rate_id : '';
	}

	/**
	 * Are store-wide taxes enabled?
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function wc_tax_enabled() {
		return get_option( 'woocommerce_calc_taxes' ) === 'yes';
	}

	/**
	 * Is Taxify enabled?
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function taxify_enabled() {
		$taxify_enabled    = get_option( 'wc_taxify_enabled' );
		$wc_taxify_api_key = get_option( 'wc_taxify_api_key' );

		if ( $this->wc_tax_enabled() && ! empty( $taxify_enabled ) && $taxify_enabled == 'yes' && ! empty( $wc_taxify_api_key ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets store tax based on value
	 *
	 * @since 1.0
	 *
	 * @return mixed
	 */
	public function tax_based_on() {
		return get_option( 'woocommerce_tax_based_on' );
	}

	/**
	 * Returns the required checks based on the request type
	 *
	 * @since 1.0
	 *
	 * @param  string $type
	 *
	 * @return bool
	 */
	public function is_request( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
			case 'ajax' :
				return defined( 'DOING_AJAX' );
			case 'cron' :
				return defined( 'DOING_CRON' );
			case 'frontend' :
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}

		return false;
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @since 1.0
	 */
	public function includes() {
		require_once( 'includes/wc-taxify-autoloader.php' );
		require_once( 'includes/wc-taxify-core-functions.php' );
		// Drop-in replacement for the WP-CRON scheduler
		require_once( 'includes/libraries/action-scheduler/action-scheduler.php' );

		// Set up localisation
		add_action( 'init', array( $this, 'load_translation' ) );

		/**
		 * Deletes all data if plugin deactivated
		 */
		//register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
	}

	/**
	 * Load Localisation files.
	 *
	 * @since 1.0
	 */
	public function load_translation() {
		load_plugin_textdomain( 'woocommerce-taxify', false, dirname( $this->plugins_basename() ) . '/languages' );
	}

	/**
	 * Checks if a plugin is activated
	 *
	 * @since 1.0
	 *
	 * @param string $slug
	 *
	 * @return bool
	 */
	public static function is_plugin_active( $slug ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( $slug, $active_plugins ) || array_key_exists( $slug, $active_plugins );
	}

	/**
	 * Strips the http:// or https:// prefix from a URL
	 *
	 * @since 1.0
	 *
	 * @param  string $url URL
	 *
	 * @return string Shortened URL
	 */
	public function remove_url_prefix( $url ) {
		return str_ireplace( array( 'http://', 'https://' ), '', $url );
	}

	/**
	 * Returns a shortened URL as the store prefix, or the value set by the store owner/manager
	 *
	 * @since 1.0
	 *
	 * @return mixed
	 */
	public function get_store_prefix() {
		$store_prefix = get_option( 'wc_taxify_store_prefix' );

		if ( ! empty( $store_prefix ) ) {
			return $store_prefix;
		}

		$home_url = home_url();
		$url      = ! empty( $home_url ) ? $home_url : site_url();

		return $this->remove_url_prefix( $url );
	}

	/**
	 * Checks for a valid postcode
	 *
	 * @since 1.0
	 *
	 * @param $postcode
	 * @param $country
	 *
	 * @return bool
	 */
	public static function is_postcode( $postcode, $country ) {
		if ( ! empty( $postcode ) ) {
			if ( strlen( trim( preg_replace( '/[\s\-A-Za-z0-9]/', '', $postcode ) ) ) > 0 ) {
				return false;
			}
		}

		return ( ! empty( $country ) && $country == 'US' && preg_match( "/^([0-9]{5})(-[0-9]{4})?$/i", $postcode ) ) ? true : false;
	}

} // End class

/**
 * Returns the main instance.
 *
 * @since  1.0
 *
 * @return \WooCommerce_Taxify
 */
function WCTAXIFY() {
	return WooCommerce_Taxify::instance();
}

// Initialize the class instance only once
WCTAXIFY();