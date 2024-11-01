<?php

/**
 * WooCommerce Taxify Log Class
 *
 * @package     Taxify/includes/Log
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.2
 *
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Taxify_Log {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Log
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_action( 'wp_ajax_delete_taxify_log_ajax', array( $this, 'delete_taxify_debug_log' ) );
	}

	/**
	 * Log error messages to WooCommerce error log ( /wp-content/woocommerce/logs/ )
	 *
	 * @since  1.0
	 *
	 * @param string $handle For example points.
	 * @param string $message
	 *
	 * @return bool
	 */
	public function log( $handle, $message ) {
		$debug_log = get_option( 'wc_taxify_debug_log' );

		if ( $debug_log == 'yes' ) {
			$logger = wc_get_logger();

			return $logger->add( $handle, $message );
		}

		return false;
	}

	/**
	 * Clear error messages to WooCommerce error log ( /wp-content/woocommerce/logs/ )
	 *
	 * @since  1.2.8
	 *
	 * @param string $handle
	 *
	 * @return bool
	 */
	public function clear( $handle ) {
		$handler = new WC_Log_Handler_File();

		return $handler->clear( $handle );
	}

	/**
	 * Truncate the debug log file. Returns true if truncated.
	 *
	 * @since  1.0
	 *
	 * @param string $handle For example points.
	 *
	 * @return bool
	 */
	public function clear_log( $handle ) {
		$empty_file = false;
		$file       = wc_get_log_file_path( $handle );

		if ( is_file( $file ) && is_writable( $file ) ) {
			$empty_file = fopen( wc_get_log_file_path( $handle ), 'w' );
			fclose( $empty_file );
		}

		return ! empty( $empty_file ) ? true : false;
	}

	/**
	 * Delete log contents.
	 *
	 * @since 1.0
	 *
	 * @param string $handle For example points.
	 */
	public function delete_log( $handle ) {
		check_ajax_referer( 'delete-' . $handle . '-log-nonce', 'delete_' . $handle . '_log_security' );

		$this->clear_log( $handle );

		wp_send_json( true );
	}

	/**
	 * Delete log contents.
	 *
	 * @since 1.0
	 */
	public function delete_taxify_debug_log() {
		$this->delete_log( 'taxify' );
	}

}