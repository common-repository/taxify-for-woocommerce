<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Taxify Format class
 *
 * @package     Taxify/includes/Format
 * @author      Todd Lahman LLC
 * @copyright   Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Format {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Format
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Flattens array using get_post_meta function. Moves all [0] elements one level higher, so each value can be accessed by key.
	 * HHVM compatible.
	 *
	 * @since 1.0
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function flatten_array( $data ) {
		$array = array();

		if ( ! empty( $data ) && is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( count( $value ) == 1 ) {
					if ( is_array( $value ) ) {
						$array[ $key ] = $value[ 0 ];
					} else {
						$array[ $key ] = $value;
					}
				} else if ( count( $value ) > 1 ) {
					$array[ $key ] = $value;
				}
			}
		}

		return $array;
	}

	/**
	 * Returns a flattended post_meta, or user_meta, data array or false.
	 *
	 * @since 1.0
	 *
	 * @param string  $type post|user
	 * @param integer $id
	 * @param string  $key
	 * @param boolean $single
	 *
	 * @return bool|array
	 */
	public function get_meta_flattened( $type = '', $id, $key = '', $single = false ) {
		if ( ! empty( $type ) ) {
			if ( $type == 'post' ) {
				$meta = get_post_meta( $id, $key, $single );
			}

			if ( $type == 'user' ) {
				$meta = get_user_meta( $id, $key, $single );
			}

			if ( ! empty( $meta ) ) {
				return $this->flatten_array( $meta );
			}
		}

		return false;
	}

	/**
	 * Get a formatted billing address for the order.
	 *
	 * @since 1.0
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function get_formatted_billing_address( $data ) {
		$address = apply_filters( 'wc_taxify_formatted_billing_address', array(
			'destination_first_name' => ! empty( $data[ 'billing' ][ 'first_name' ] ) ? $data[ 'billing' ][ 'first_name' ] : '',
			'destination_last_name'  => ! empty( $data[ 'billing' ][ 'last_name' ] ) ? $data[ 'billing' ][ 'last_name' ] : '',
			'destination_company'    => ! empty( $data[ 'billing' ][ 'company' ] ) ? $data[ 'billing' ][ 'company' ] : '',
			'destination_address_1'  => ! empty( $data[ 'billing' ][ 'address_1' ] ) ? $data[ 'billing' ][ 'address_1' ] : '',
			'destination_address_2'  => ! empty( $data[ 'billing' ][ 'address_2' ] ) ? $data[ 'billing' ][ 'address_2' ] : '',
			'destination_city'       => ! empty( $data[ 'billing' ][ 'city' ] ) ? $data[ 'billing' ][ 'city' ] : '',
			'destination_state'      => ! empty( $data[ 'billing' ][ 'state' ] ) ? $data[ 'billing' ][ 'state' ] : '',
			'destination_zip'        => ! empty( $data[ 'billing' ][ 'postcode' ] ) ? $data[ 'billing' ][ 'postcode' ] : '',
			'destination_country'    => ! empty( $data[ 'billing' ][ 'country' ] ) ? $data[ 'billing' ][ 'country' ] : '',
			'destination_email'      => ! empty( $data[ 'billing' ][ 'email' ] ) ? $data[ 'billing' ][ 'email' ] : '',
			'destination_phone'      => ! empty( $data[ 'billing' ][ 'phone' ] ) ? $data[ 'billing' ][ 'phone' ] : '',
		), $data );

		return $address;
	}

	/**
	 * Get a formatted shipping address for the order.
	 *
	 * @since 1.0
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function get_formatted_shipping_address( $data ) {
		$address = apply_filters( 'wc_taxify_formatted_shipping_address', array(
			'destination_first_name' => ! empty( $data[ 'shipping' ][ 'first_name' ] ) ? $data[ 'shipping' ][ 'first_name' ] : '',
			'destination_last_name'  => ! empty( $data[ 'shipping' ][ 'last_name' ] ) ? $data[ 'shipping' ][ 'last_name' ] : '',
			'destination_company'    => ! empty( $data[ 'shipping' ][ 'company' ] ) ? $data[ 'shipping' ][ 'company' ] : '',
			'destination_address_1'  => ! empty( $data[ 'shipping' ][ 'address_1' ] ) ? $data[ 'shipping' ][ 'address_1' ] : '',
			'destination_address_2'  => ! empty( $data[ 'shipping' ][ 'address_2' ] ) ? $data[ 'shipping' ][ 'address_2' ] : '',
			'destination_city'       => ! empty( $data[ 'shipping' ][ 'city' ] ) ? $data[ 'shipping' ][ 'city' ] : '',
			'destination_state'      => ! empty( $data[ 'shipping' ][ 'state' ] ) ? $data[ 'shipping' ][ 'state' ] : '',
			'destination_zip'        => ! empty( $data[ 'shipping' ][ 'postcode' ] ) ? $data[ 'shipping' ][ 'postcode' ] : '',
			'destination_country'    => ! empty( $data[ 'shipping' ][ 'country' ] ) ? $data[ 'shipping' ][ 'country' ] : '',
		), $data );

		return $address;
	}

	/**
	 * Reformats the completed order date to 2015-08-27, rather than the original 2015-08-27 02:54:31,
	 * to match the API expectations.
	 *
	 * @since 1.0
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function order_completed_date_for_api( $order_id ) {
		return date_i18n( 'Y-m-d', strtotime( WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_completed_date' ) ) );
	}

	/**
	 * Reformats the paid order date to 2015-08-27, rather than the original 2015-08-27 02:54:31,
	 * to match the API expectations.
	 *
	 * @since 1.2.2
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function order_paid_date_for_api( $order_id ) {
		return date_i18n( 'Y-m-d', strtotime( WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_paid_date' ) ) );
	}

	/**
	 * Reformats the order date to 2015-08-27, rather than the original 2015-08-27 02:54:31,
	 * to match the API expectations.
	 *
	 * @since 1.2.4
	 *
	 * @param $order_id
	 *
	 * @return string
	 */
	public function order_date_for_api( $order_id ) {
		$post = get_post( $order_id );

		return date_i18n( 'Y-m-d', strtotime( $post->post_date ) );
	}

	/**
	 * Make readable date/time from timestamp (yyyy-mm-dd hh:mm:ss)
	 *
	 * @since 1.0
	 *
	 * @param int $timestamp
	 *
	 * @return string
	 */
	public function get_datetime( $timestamp ) {
		return $this->get_adjusted_datetime( $timestamp, 'Y-m-d H:i:s' );
	}

	/**
	 * Get timezone-adjusted formatted date/time string
	 *
	 * @since 1.0
	 *
	 * @param int    $timestamp
	 * @param string $format
	 * @param string $context
	 *
	 * @return string
	 */
	public function get_adjusted_datetime( $timestamp, $format = null, $context = null ) {
		// Create time zone object and set time zone
		$date_time = new DateTime();
		$date_time->setTimestamp( $timestamp );
		$time_zone = new DateTimeZone( $this->get_time_zone() );
		$date_time->setTimezone( $time_zone );

		// Get datetime as string in ISO format
		$date_time_iso = $date_time->format( 'Y-m-d H:i:s' );

		// Hack to make date_i18n() work with our time zone
		$date_time_utc = new DateTime( $date_time_iso );
		$time_zone_utc = new DateTimeZone( 'UTC' );
		$date_time_utc->setTimezone( $time_zone_utc );

		// No format passed? Get it from WordPress settings and allow developers to override it
		if ( $format === null ) {
			$date_format = apply_filters( 'wc_taxify_date_format', get_option( 'date_format' ), $context );
			$time_format = apply_filters( 'wc_taxify_time_format', get_option( 'time_format' ), $context );
			$format      = $date_format . ( apply_filters( 'wc_taxify_display_event_time', true ) ? ' ' . $time_format : '' );
		}

		// Format and return
		return date_i18n( $format, $date_time_utc->format( 'U' ) );
	}

	/**
	 * Get timezone string
	 *
	 * @since 1.0
	 *
	 * @return mixed|string|void
	 */
	public function get_time_zone() {
		$time_zone        = get_option( 'timezone_string' );
		$utc_offset       = get_option( 'gmt_offset' );
		$time_zone_offset = timezone_name_from_abbr( '', $utc_offset );

		if ( $time_zone ) {
			return $time_zone;
		}

		if ( $utc_offset ) {

			$utc_offset = $utc_offset * HOUR_IN_SECONDS;
			$dst        = date( 'I' );

			// Try to get timezone name from offset
			if ( $time_zone_offset ) {
				return $time_zone;
			}

			// Try to guess timezone by looking at a list of all timezones
			$timezone_abbreviations_list = timezone_abbreviations_list();
			if ( ! empty( $timezone_abbreviations_list ) && is_array( $timezone_abbreviations_list ) ) {
				foreach ( $timezone_abbreviations_list as $abbreviation ) {
					foreach ( $abbreviation as $city ) {
						if ( $city[ 'dst' ] == $dst && $city[ 'offset' ] == $utc_offset ) {
							return $city[ 'timezone_id' ];
						}
					}
				}
			}
		}

		return 'UTC';
	}

	/**
	 * Gets a MySQL TiimeDate format that matches the value returned from current_time( 'mysql' ),
	 * except the date is 13 months ago from now.
	 *
	 * @since 1.0
	 *
	 * @return int
	 */
	public function get_mysql_time_13_months_ago() {
		return $this->get_datetime( mktime( date( 'H' ), date( 'i' ), date( 's' ), date( 'm' ) - 1, date( 'd' ), date( 'Y' ) - 1 ) );
	}

	/**
	 * Returns a human readable file size.
	 *
	 * @since 1.2
	 *
	 * @param string $filepath Full path to the file.
	 * @param int    $decimal_places
	 *
	 * @return string
	 */
	public function human_readable_filesize( $filepath, $decimal_places = 2 ) {
		$filesize = ! empty( $filepath ) && is_writable( $filepath ) ? filesize( $filepath ) : false;

		if ( ! empty( $filesize ) ) {
			$size               = array( 'Bytes', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
			$factor             = floor( ( strlen( $filesize ) - 1 ) / 3 );
			$exponential_result = $filesize / pow( 1024, $factor );

			/**
			 * Arrays and objects can not be used as array keys. Doing so will result in a warning: Illegal offset type.
			 * $factor is an illegal key as a float type, so it is cast as a string to make it legal. The string is then cast as an integer by PHP.
			 */
			return sprintf( "%.{$decimal_places}f", $exponential_result ) . ' ' . $size[ (string) $factor ];
		}

		return '0.00';
	}

	/**
	 * Flattens meta object data.
	 *
	 * @since 1.2.8
	 *
	 * @param object|array $data
	 *
	 * @return array
	 */
	public function flatten_meta_object( $data ) {
		$array = array();

		if ( ! empty( $data ) ) {
			foreach ( (array) $data as $key => $value ) {
				// Skip empty meta values.
				if ( ! empty( $value->value ) ) {
					$array[ $value->key ] = $value->value;
				}
			}
		}

		return $array;
	}

}