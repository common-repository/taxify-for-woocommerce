<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxify Autoloader Class
 *
 * @package     Taxify/includes/Autoloader
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Autoloader {

	public function __construct() {
		if ( function_exists( "__autoload" ) ) {
			spl_autoload_register( "__autoload" );
		}

		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Make class name lowercase, then replace underscores with dashes, and append a .php
	 *
	 * @since 1.0
	 *
	 * @param string $class
	 *
	 * @return string
	 */
	private function get_file_name_from_class( $class ) {
		return str_replace( '_', '-', strtolower( $class ) ) . '.php';
	}

	/**
	 * Make sure the file is readable, then load it
	 *
	 * @since 1.0
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	private function load_file( $path ) {
		if ( $path && is_readable( $path ) ) {
			require_once( $path );

			return true;
		}

		return false;
	}

	/**
	 * Autoload the class if it has not been loaded already
	 *
	 * @since 1.0
	 *
	 * @param string $class_name
	 */
	private function autoload( $class_name ) {
		if ( strpos( $class_name, 'WC_Taxify_' ) !== 0 ) {
			return;
		}

		$file = $this->get_file_name_from_class( $class_name );

		$paths = array(
			WCTAXIFY()->plugin_path() . '/includes/' . $file,
			WCTAXIFY()->plugin_path() . '/includes/admin/' . $file,
			WCTAXIFY()->plugin_path() . '/includes/data-stores/' . $file,
		);

		if ( ! empty( $paths ) && is_array( $paths ) ) {
			foreach ( $paths as $key => $path ) {
				$this->load_file( $path );
			}
		}
	}
}

new WC_Taxify_Autoloader();