<?php

/**
 * Taxify Admin Settings Class
 *
 * @package     Taxify/includes/admin/Settings Admin
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Taxify_Admin_Settings_Tab extends WC_Settings_Page {

	protected $id = 'taxify';

	public function __construct() {
		$this->label = __( 'Taxify', 'woocommerce-taxify' );

		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 60.4 );
		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );

		parent::__construct();
	}

	/**
	 * Get sections
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			'' => __( 'Main Settings', 'woocommerce-taxify' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Output the settings
	 *
	 * @since 1.0
	 */
	public function output() {
		global $current_section;

		$settings = $this->get_settings( $current_section );

		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save settings
	 *
	 * @since 1.0
	 */
	public function save() {
		global $current_section;

		$is_api_key_valid         = $this->api_key_check();
		$wc_taxify_api_key        = get_option( 'wc_taxify_api_key' );
		$did_schedule_bulk_orders = get_option( 'wc_taxify_did_schedule_bulk_orders' );

		if ( empty( $did_schedule_bulk_orders ) || $did_schedule_bulk_orders != 'yes' ) {
			if ( ! empty( $wc_taxify_api_key ) && $is_api_key_valid ) {
				WCT_SCHEDULER()->schedule_bulk_orders();
			}
		}

		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );
	}

	/**
	 * Get settings array
	 *
	 * @since 1.0
	 *
	 * @param string $current_section
	 *
	 * @return mixed|void
	 */
	public function get_settings( $current_section = '' ) {
		$current_section = 'taxify';

		if ( $current_section ) {
			$is_api_key_valid = $this->api_key_check();

			wc_enqueue_js( "
			    $('#wc_taxify_delete_debug_log').replaceWith('<button type=\"button\" id=\"delete-taxify-log\" class=\"button\">Delete Debug Log</button>');
				$('#delete-taxify-log').on('click', function () {
					var delete_taxify_log_data = {
						action: 'delete_taxify_log_ajax',
						delete_taxify_log_security: '" . wp_create_nonce( 'delete-taxify-log-nonce' ) . "'
					};
					$('.form-table').block({
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6
						}
					});
					$.post('" . esc_url( admin_url( 'admin-ajax.php' ) ) . "', delete_taxify_log_data, function( response ) {
						// Success
						if (response) {
							$('#delete-taxify-log').replaceWith('<span style=\"color: #66ab03; margin: auto;\"><strong>Debug Log Deleted.</strong></span>');
							$('td.forminp-text span.description').replaceWith('');
							$('.form-table').unblock();
						}
					});
				});
			" );

			$settings = apply_filters( 'wc_taxify_settings_main', array(
				array(
					'name' => __( 'Taxify Settings', 'woocommerce-taxify' ),
					'type' => 'title',
					'desc' => sprintf( __( 'Taxify version: %s%sWooCommerce version %s', 'woocommerce-taxify' ), esc_attr( WCTAXIFY()->version ),'<br>', esc_attr( get_option( 'woocommerce_version' ) ) ),
					'id'   => 'wc_taxify_settings_main_title'
				),
				array(
					'name'            => __( 'Enable Taxify', 'woocommerce-taxify' ),
					'desc'            => __( 'Enable Taxify tax calculations', 'woocommerce-taxify' ),
					'id'              => 'wc_taxify_enabled',
					'default'         => 'no',
					'type'            => 'checkbox',
					'desc_tip'        => '',
					'checkboxgroup'   => 'start',
					'show_if_checked' => 'option',
				),
				array(
					'name'     => __( 'Taxify API Key', 'woocommerce-taxify' ),
					'desc'     => sprintf( __( '%s', 'woocommerce-taxify' ), ! empty( $is_api_key_valid ) ? '<span style="color: #66ab03;"><strong>Verified API Key!</strong></span>' : '<span style="color: #ca336c;"><strong>Bad API Key!</strong></span> If you need an API Key, get one at <a href="' . esc_url( 'https://taxify.co/' ) . '" target="blank">Taxify.co</a>' ),
					'tip'      => '',
					'id'       => 'wc_taxify_api_key',
					'css'      => 'min-width:350px;',
					'default'  => '',
					'type'     => 'text',
					'desc_tip' => false,
				),
				array(
					'name'     => __( 'Store Prefix', 'woocommerce-taxify' ),
					'desc'     => sprintf( __( 'Unique store order prefix. The prefix helps identify the store where the order originated from. This will appear in the Taxify dashboard.', 'woocommerce-taxify' ), WCTAXIFY()->store_prefix ),
					'tip'      => '',
					'id'       => 'wc_taxify_store_prefix',
					'css'      => 'min-width:350px;',
					'default'  => WCTAXIFY()->store_prefix,
					'type'     => 'text',
					'desc_tip' => true,
				),
				array(
					'name'     => __( 'Tax Exempt on Checkout', 'woocommerce-taxify' ),
					'desc'     => __( 'Display the tax exempt checkbox on the checkout page.', 'woocommerce-taxify' ),
					'id'       => 'wc_taxify_exempt_checkout_checkbox',
					'type'     => 'select',
					'desc_tip' => '',
					'default'  => 'no',
					'options'  => array( 'yes' => 'Yes', 'no' => 'No' )
				),
				array(
					'name'     => __( 'Debug Logging', 'woocommerce-taxify' ),
					'desc'     => sprintf( __( 'Logs debug events inside <code>%s</code>. <a href="%s">View Log</a>', 'woocommerce-taxify' ), basename( wc_get_log_file_path( 'taxify' ) ), esc_url( self_admin_url() . 'admin.php?page=wc-status&tab=logs' ) ),
					'id'       => 'wc_taxify_debug_log',
					'type'     => 'select',
					'desc_tip' => '',
					'default'  => 'no',
					'options'  => array( 'yes' => 'On', 'no' => 'Off' )
				),
				array(
					'name'        => __( 'Delete Log', 'woocommerce-taxify' ),
					'id'          => 'wc_taxify_delete_debug_log',
					'type'        => 'text',
					'class'       => 'button',
					'css'         => '<button type="button">Delete Log</button>',
					'desc'        => sprintf( __( 'Log file size %s', 'woocommerce-taxify' ), esc_attr( WCT_FORMAT()->human_readable_filesize( wc_get_log_file_path( 'taxify' ) ) ) ),
					'placeholder' => 'Delete Log',
				),
				array(
					'type' => 'sectionend',
				),
			) );
		}

		if ( ! empty( $settings ) && is_array( $settings ) ) {
			return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
		}

		return array();
	}

	/**
	 * Queries the API for the purpose of determining if the API Key is valid
	 *
	 * @since 1.0
	 */
	private function api_key_check() {

		$result = WCT_SOAP()->cancel_tax( 'taxify_api_key_test- ' . current_time( 'mysql' ) );

		if ( is_object( $result ) ) {
			if ( $result->CancelTaxResult->ResponseStatus == 'Success' ) {
				return true;
			}

			if ( $result->CancelTaxResult->ResponseStatus == 'Failure' ) {
				return false;
			}
		}

		return false;
	}

} // end of class

new WC_Taxify_Admin_Settings_Tab();