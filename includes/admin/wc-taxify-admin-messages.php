<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxify Admin includes
 *
 * @package     Taxify/includes/admin/Admin Messages
 * @author      Todd Lahman LLC
 * @copyright   Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Admin_messages {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Admin_messages
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Displays Taxify disabled message if WooCommerce is not active
	 *
	 * @since 1.0
	 */
	public static function woocommerce_best_results() {
		if ( ( isset( $_GET[ 'post_type' ] ) || isset( $_GET[ 'page' ] ) ) && ( $_GET[ 'post_type' ] == 'shop_order' || $_GET[ 'page' ] == 'wc-settings' ) ) {
			?>
            <div id="message" class="notice notice-info">
                <p><?php printf( __( '%sWooCommerce%s version 3.2 or greater is required for best results.', 'woocommerce-taxify' ), '<strong>', '</strong>' ); ?></p>
            </div>
			<?php
		}
	}

	/**
	 * Displays Taxify disabled message if WooCommerce is not active
	 *
	 * @since 1.0
	 */
	public static function woocommerce_disabled_message() {
		?>
        <div id="message" class="error">
            <p><?php printf( __( '%sTaxify is inactive.%s The %sWooCommerce%s plugin must be active for Taxify to work. Please activate WooCommerce on the %splugin page%s once it is installed.', 'woocommerce-taxify' ), '<strong>', '</strong>', '<a href="http://wordpress.org/extend/plugins/woocommerce/" target="_blank">', '</a>', '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">', '</a>' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays Taxify disabled message if WooCommerce does not meet the required version
	 *
	 * @since 1.0
	 */
	public static function woocommerce_version_disabled_message() {
		?>
        <div id="message" class="error">
            <p><?php printf( __( '%sTaxify is inactive. WooCommerce version %s %s is installed, but WooCommerce version %s is required for Taxify to work. Please %supdate%s WooCommerce to the most current version.', 'woocommerce-taxify' ), '<strong>', '</strong>', sanitize_text_field( (string) WCTAXIFY()->wc_version ), sanitize_text_field( WCTAXIFY()->wc_min_required_version ), '<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">', '</a>' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays Taxify disabled message if the PHP SoapClient class does not exist
	 *
	 * @since 1.0
	 */
	public static function soap_client_disabled_message() {
		?>
        <div id="message" class="error">
            <p><?php printf( __( '%sTaxify is inactive.%s Taxify requires the PHP SoapClient. Please ask your web host to install PHP SOAP.', 'woocommerce-taxify' ), '<strong>', '</strong>' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays message to activate the Taxify API Key if it is empyt on the settings page
	 *
	 * @since 1.0
	 */
	public static function activate_api_key_message() {
		?>
        <div id="message" class="error">
            <p><?php printf( __( '%sThe Taxify API Key has not been activated.%s Please %senter%s your Taxify API Key to start using Taxify.', 'woocommerce-taxify' ), '<strong>', '</strong>', '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=taxify' ) ) . '">', '</a>' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays message to disable prices include tax
	 *
	 * @since 1.0
	 */
	public static function prices_include_tax_message() {
		?>
        <div id="message" class="error">
            <p><?php printf( __( '%sPrices Entered With Tax%s is set to %sYes, I will enter prices inclusive of tax%s. This will cause taxes to be calculated incorrectly by Taxify. Please go to %sTax settings%s and set the option to %sNo, I will enter prices exclusive of tax%s.', 'woocommerce-taxify' ), '<strong>', '</strong>', '<strong>', '</strong>', '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=tax' ) ) . '">', '</a>', '<strong>', '</strong>' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays message to disable prices include tax
	 *
	 * @since 1.0
	 */
	public static function tax_based_on_message() {
		?>
        <div id="message" class="error">
            <p><?php printf( __( '%sCalculate Tax Based On%s is set to %sShop base address%s. This will cause taxes to be calculated incorrectly by Taxify. Please go to %sTax settings%s and set the option to either %sCustomer shipping address%s (most accurate), or %sCustomer billing address%s.', 'woocommerce-taxify' ), '<strong>', '</strong>', '<strong>', '</strong>', '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=tax' ) ) . '">', '</a>', '<strong>', '</strong>', '<strong>', '</strong>' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Check for external blocking contstant
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public static function check_external_blocking() {
		// show notice if external requests are blocked through the WP_HTTP_BLOCK_EXTERNAL constant
		if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL === true ) {
			// check if our API endpoint is in the allowed hosts
			$host = parse_url( WCTAXIFY()->taxify_api_endpoint, PHP_URL_HOST );

			if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) || stristr( WP_ACCESSIBLE_HOSTS, $host ) === false ) {
				?>
                <div class="error">
                    <p><?php printf( __( '<b>Warning!</b> You\'re blocking external requests which means you won\'t be able to query the %s API. Please add %s to %s.', 'woocommerce-taxify' ), 'Taxify', '<strong>' . $host . '</strong>', '<code>WP_ACCESSIBLE_HOSTS</code>' ); ?></p>
                </div>
				<?php
			}
		}
	}

	/**
	 * Displays Taxify configure message that automatically disappears after displaying once
	 *
	 * @since 1.0
	 */
	public static function taxify_taxes_configured_message() {
		?>
        <div id="message" class="error">
            <p><?php printf( __( 'The %sWooCommerce%s tax settings have been automatically configured to work with %sTaxify%s.', 'woocommerce-taxify' ), '<strong>', '</strong>', '<strong>', '</strong>' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays an error if the Zip Code is not formatted properly.
	 *
	 * @since 1.0
	 */
	public static function taxify_zipcode_wrong_format_message() {
		?>
        <div id="message" class="error">
            <p><?php _e( 'The Zip Code format below is not correct. Either xxxxx or xxxxx-xxxx would work.', 'woocommerce-taxify' ); ?></p>
        </div>
		<?php
	}

	/**
	 * Displays an error if the Zip Code is not set.
	 *
	 * @since 1.0
	 */
	public static function taxify_zipcode_empty_message() {
		?>
        <div id="message" class="error">
            <p><?php _e( 'Please enter a Zip Code for the store location where products are sold and/or shipped from.', 'woocommerce-taxify' ); ?></p>
        </div>
		<?php
	}

}