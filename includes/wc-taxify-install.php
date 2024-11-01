<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Install Class
 *
 * @package     Taxify/includes/Install
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Install {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Install
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		register_activation_hook( WCTAXIFY()->taxify_plugin_file, array( $this, 'install' ) );
		add_action( 'admin_init', array( $this, 'check_version' ), 5 );
	}

	/**
	 * Trigger install routine which also handles upgrades
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) && ( get_option( 'wc_taxify_version' ) != WCTAXIFY()->version ) ) {
			$this->install();

			do_action( 'wc_taxify_updated' );
		}
	}

	/**
	 * Handles tasks when plugin is activated
	 *
	 * @since 1.0
	 *
	 * return void
	 */
	public function install() {
		$wc_taxify_default_tax_options = get_option( 'wc_taxify_default_tax_options' );
		$wc_taxify_rate_id             = get_option( 'wc_taxify_rate_id' );
		$curr_ver                      = get_option( 'wc_taxify_version' );
		$store_prefix                  = get_option( 'wc_taxify_store_prefix' );
		$tax_exempt_checkbox           = get_option( 'wc_taxify_exempt_checkout_checkbox' );
		$debug_log                     = get_option( 'wc_taxify_debug_log' );

		// checks if the current plugin version is less than the version being installed
		if ( version_compare( WCTAXIFY()->version, $curr_ver, '>' ) || empty( $curr_ver ) ) {
			//$this->upgrade();

			if ( empty( $wc_taxify_default_tax_options ) ) {
				$this->create_options();
			}

			if ( empty( $wc_taxify_rate_id ) ) {
				$this->insert_tax_rate();
			}

			if ( get_option( 'wc_taxify_default_tax_options' ) == 'yes' && is_numeric( get_option( 'wc_taxify_rate_id' ) ) ) {
				update_option( 'wc_taxify_taxes_configured', 'yes' );
			}

			if ( empty( $store_prefix ) ) {
				update_option( 'wc_taxify_store_prefix', WCTAXIFY()->store_prefix );
			}

			if ( empty( $tax_exempt_checkbox ) ) {
				update_option( 'wc_taxify_exempt_checkout_checkbox', 'no' );
			}

			if ( empty( $debug_log ) ) {
				update_option( 'wc_taxify_debug_log', 'no' );
			}

			if ( WCTAXIFY()->version >= '1.2' ) {
				$filepath     = wc_get_log_file_path( 'taxify-for-woocommerce' );
				$old_log_size = ! empty( $filepath ) && is_writable( $filepath ) ? filesize( $filepath ) : false;

				if ( ! empty( $old_log_size ) ) {
					WCT_LOG()->clear_log( 'taxify-for-woocommerce' );
				}
			}

			$this->update();

			update_option( 'wc_taxify_version', WCTAXIFY()->version );
		}

		do_action( 'wc_taxify_installed' );
	}

	/**
	 * Sets default plugin options
	 *
	 * @since 1.0
	 *
	 * return void
	 */
	private function create_options() {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_default_customer_address', '' );
		update_option( 'woocommerce_shipping_tax_class', '' );
		update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		update_option( 'woocommerce_tax_total_display', 'itemized' );
		// Flag default options added
		update_option( 'wc_taxify_default_tax_options', 'yes' );
	}

	/**
	 * Sets default plugin options
	 *
	 * @since 1.0
	 *
	 * return void
	 */
	//private function upgrade() {
	//}

	/**
	 * Sets default woocommerce_tax_rates for Taxify tax class
	 *
	 * @since 1.0
	 *
	 * return void
	 */
	private function insert_tax_rate() {
		// global $wpdb;

		$tax_rate = array(
			'tax_rate_country'  => 'US',
			'tax_rate_state'    => '',
			'tax_rate'          => 0,
			'tax_rate_name'     => 'Tax',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => 'taxify',
		);

		// $wpdb->insert( $wpdb->prefix . 'woocommerce_tax_rates', $tax_rate );
		// $tax_rate_id = $wpdb->insert_id;

		$rate_id = get_option( 'wc_taxify_rate_id' );

		if ( empty( $rate_id ) ) {
			$tax_rate_id = WC_Tax::_insert_tax_rate( $tax_rate );
			update_option( 'wc_taxify_rate_id', $tax_rate_id );
		}
	}

	private function update() {
		/**
		 * Bulk schedule tax exempt, and zero value, orders to be filed with Taxify
		 *
		 * @since 1.1.2
		 */
		$wc_taxify_api_key                   = get_option( 'wc_taxify_api_key' );
		$did_schedule_bulk_orders            = get_option( 'wc_taxify_did_schedule_bulk_orders' );
		$did_schedule_tax_exempt_bulk_orders = get_option( 'wc_taxify_did_schedule_tax_exempt_bulk_orders' );

		if ( ! empty( $did_schedule_bulk_orders ) || $did_schedule_bulk_orders == 'yes' ) {
			if ( ! empty( $wc_taxify_api_key ) && empty( $did_schedule_tax_exempt_bulk_orders ) ) {
				WCT_SCHEDULER()->schedule_bulk_orders();
			}
		}
	}

} // End class