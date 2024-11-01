<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxify Admin class
 *
 * @package     Taxify/includes/admin/Admin
 * @author      Todd Lahman LLC
 * @copyright   Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Admin {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Admin
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		//$this->includes();
		add_action( 'init', array( $this, 'includes' ) );
		//add_action( 'current_screen', array( $this, 'conditonal_includes' ) );
		//add_action( 'plugins_loaded', array( $this, 'dependent_includes' ) );
		add_filter( 'plugin_action_links_' . WCTAXIFY()->taxify_plugin_basename, array( $this, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 100 );
		add_action( 'wp_ajax_wc_taxify_rated', array( $this, 'taxify_rated' ) );
		add_action( 'woocommerce_settings_saved', array( $this, 'check_required_settings' ) );
	}

	/**
	 *
	 * @since  1.0
	 * @return void
	 */
	public function includes() {
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_pages' ) );
	}

	/**
	 *
	 * @since  1.0
	 */
	public function conditonal_includes() {
		$screen = get_current_screen();

		switch ( $screen->id ) {
			case 'dashboard':
				break;
			case 'edit':
				break;
			case 'options-permalink':
				break;
			case 'post':
				break;
			case 'users':
			case 'user' :
			case 'profile':
				break;
			case 'user-edit':
				break;
		}
	}

	/**
	 *
	 * @since  1.0
	 */
	public function dependent_includes() { }

	/**
	 * Include the settings page classes
	 *
	 * @since 1.0
	 *
	 * @param $settings array
	 *
	 * @return array
	 */
	public function add_settings_pages( $settings ) {
		$settings[] = require_once( 'wc-taxify-admin-settings-tab.php' );

		return $settings;
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @since 1.0
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=taxify' ) ) . '" title="' . esc_attr( __( 'View Taxify for WooCommerce Settings', 'woocommerce-taxify' ) ) . '">' . esc_attr( __( 'Settings', 'woocommerce-taxify' ) ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @since 1.0
	 *
	 * @param array  $links
	 * @param string $file
	 *
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( $file == WCTAXIFY()->taxify_plugin_basename ) {
			$row_meta = array(
				'docs'            => '<a href="' . esc_url( apply_filters( 'wc_taxify_docs_url', 'https://www.toddlahman.com/taxify-for-woocommerce/' ) ) . '" title="' . esc_attr( __( 'View Taxify for WooCommerce Documentation', 'woocommerce-taxify' ) ) . '" target=_blank>' . esc_attr( __( 'Docs', 'woocommerce-taxify' ) ) . '</a>',
				'support'         => '<a href="' . esc_url( apply_filters( 'wc_taxify_support_url', 'https://taxify.co/contact-us/' ) ) . '" title="' . esc_attr( __( 'Visit Taxify for WooCommerce Support', 'woocommerce-taxify' ) ) . '" target=_blank>' . esc_attr( __( 'Support', 'woocommerce-taxify' ) ) . '</a>',
				'software_author' => 'Developed by - <a href="' . esc_url( apply_filters( 'wc_taxify_software_author', 'https://www.toddlahman.com/' ) ) . '" title="' . esc_attr( __( 'Visit Todd Lahman LLC', 'woocommerce-taxify' ) ) . '" target=_blank>' . esc_attr( __( 'Todd Lahman LLC', 'woocommerce-taxify' ) ) . '</a>',
			);

			return array_merge( $links, $row_meta );
		}

		return (array) $links;
	}

	/**
	 * Change the admin footer text for Taxify for WooCommerce.
	 *
	 * @since  1.2.8
	 *
	 * @param  string $footer_text
	 *
	 * @return string
	 */
	public function admin_footer_text( $footer_text ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'wc_get_screen_ids' ) ) {
			return $footer_text;
		}

		$current_screen = get_current_screen();
		$wc_pages       = wc_get_screen_ids();
		$wc_pages       = array_diff( $wc_pages, array( 'profile', 'user-edit' ) );

		// Check to make sure we're on a WooCommerce admin page.
		if ( isset( $current_screen->id ) && apply_filters( 'wc_taxify_display_admin_footer_text', in_array( $current_screen->id, $wc_pages ) ) ) {
			//Change the footer text
			if ( ! get_option( 'wc_taxify_admin_footer_text_rated' ) ) {
				$footer_text = printf( /* translators: 1: Taxfy for WooCommerce 2:: five stars */
					__( 'If you like %s please leave us a %s rating. A huge thanks in advance!', 'woocommerce-taxify' ), sprintf( '<strong>%s</strong>', esc_html__( 'Taxify for WooCommerce', 'woocommerce-taxify' ) ), '<a href="https://wordpress.org/support/plugin/taxify-for-woocommerce/reviews?rate=5#new-post" target="_blank" class="wc-rating-link" data-rated="' . esc_attr__( 'Thanks :)', 'woocommerce-taxify' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>' );
				wc_enqueue_js( "
					jQuery( 'a.wc-rating-link' ).click( function() {
						jQuery.post( '" . WC()->ajax_url() . "', { action: 'wc_taxify_rated' } );
						jQuery( this ).parent().text( jQuery( this ).data( 'taxify_rated' ) );
					});
				" );
			} else {
				$footer_text = _e( 'Thank you for preparing taxes with Taxfy for WooCommerce.', 'woocommerce-taxify' );
			}
		}

		return $footer_text;
	}

	/**
	 * Triggered when clicking the rating footer.
	 *
	 * @since 1.2.8
	 */
	public function taxify_rated() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( - 1 );
		}
		update_option( 'wc_taxify_admin_footer_text_rated', 1 );
		wp_die();
	}

	/**
	 * Ensures the required settings remain intact while the plugin is active.
	 *
	 * @since 1.2.8
	 */
	public function check_required_settings() {
		if ( get_option( 'woocommerce_calc_taxes' ) != 'yes' ) {
			update_option( 'woocommerce_calc_taxes', 'yes' );
		}

		if ( get_option( 'woocommerce_prices_include_tax' ) != 'no' ) {
			update_option( 'woocommerce_prices_include_tax', 'no' );
		}

		if ( get_option( 'woocommerce_tax_based_on' ) != 'shipping' ) {
			update_option( 'woocommerce_tax_based_on', 'shipping' );
		}

		if ( get_option( 'woocommerce_default_customer_address' ) != '' ) {
			update_option( 'woocommerce_default_customer_address', '' );
		}

		if ( get_option( 'woocommerce_shipping_tax_class' ) != '' ) {
			update_option( 'woocommerce_shipping_tax_class', '' );
		}

		if ( get_option( 'woocommerce_tax_round_at_subtotal' ) != 'no' ) {
			update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
		}

		if ( get_option( 'woocommerce_tax_display_shop' ) != 'excl' ) {
			update_option( 'woocommerce_tax_display_shop', 'excl' );
		}

		if ( get_option( 'woocommerce_tax_display_cart' ) != 'excl' ) {
			update_option( 'woocommerce_tax_display_cart', 'excl' );
		}

		if ( get_option( 'woocommerce_tax_total_display' ) != 'itemized' ) {
			update_option( 'woocommerce_tax_total_display', 'itemized' );
		}
	}
}