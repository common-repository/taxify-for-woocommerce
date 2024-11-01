<?php

/**
 * Taxify RESTful API Class
 *
 * @package     Taxify/includes/RESTful API
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.3
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Taxify_Rest_API {

	private $endpoint = 'https://ws.taxify.co/taxify/1.1/core/JSONService.asmx/';
	private $header   = array( 'headers' => array( 'Content-Type' => 'application/json' ) );
	private $request  = array(); // if this were PHP >= 5.4, or JavaScript, then []

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Rest_API
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Adds security credentials to request array.
	 *
	 * @since  1.0
	 *
	 * @access private
	 *
	 * @param string $method Methods that can be called: CalculateTax, CancelTax, CommitTax, GetVersion, and VerifyAddress
	 */
	private function request_security_creds( $method ) {
		$this->request[ $method ][ 'Security' ] = array(
			'Password' => WCTAXIFY()->taxify_api_key,
		);
	}

	/**
	 * Sends RESTful API POST request.
	 *
	 * @since  1.0
	 *
	 * @access private
	 *
	 * @param string $method Methods that can be called: CalculateTax, CancelTax, CommitTax, GetVersion, and VerifyAddress
	 *
	 * @return object|bool
	 */
	private function request( $method = '' ) {
		if ( ! empty( $method ) && ! empty( $this->request ) ) {

			$args    = wp_parse_args( $this->header, $this->request );
			$args    = wp_parse_args( $args, $this->request_security_creds( $method ) );
			$request = wp_safe_remote_post( $this->endpoint . $method . '/', array( 'body' => $args ) );

			if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) != 200 ) {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $request ) ) );

				return array();
			}

			$response = wp_remote_retrieve_body( $request );
			$response = json_decode( $response, true );

			return $response;
		}

		return false;
	}

	/**
	 * Calls the CalculateTax method using a RESTful API POST request.
	 *
	 * @since 1.0
	 *
	 * @param array $data
	 * @param array $line_items
	 * @param array $refunds
	 *
	 * @return bool|object
	 */
	public function calculate_tax( $data = array(), $line_items = array(), $refunds = array() ) {
		$method = 'CalculateTax';
		// Initialize an empty array, so old data artifacts don't pollute new data
		$this->request                                    = array();
		$this->request[ $method ][ 'CustomerKey' ] = ! empty( $data[ 'customer_key' ] ) ? WCTAXIFY()->store_prefix . '-' . $data[ 'customer_key' ] : '';

		//$this->request[ 'Request' ][ 'TaxDate' ]          = ! empty( $data[ 'tax_date' ] ) ? $data[ 'tax_date' ] : date( 'Y-m-d' );

		if ( ! empty( $line_items ) && is_array( $line_items ) ) {
			foreach ( $line_items as $item_key => $item ) {
				$this->request[ 'Request' ][ 'Lines' ][ 'TaxRequestLine' ][ $item_key ] = $item;
			}
		}

		if ( ! empty( $refunds ) && is_array( $refunds ) ) {
			foreach ( $refunds as $refund_key => $refund ) {
				$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ $refund_key ] = $refund;
			}
		}

		if ( ! empty( $data[ 'origin_zip' ] ) ) {
			$this->request[ $method ][ 'OriginAddress' ][ 'Company' ]    = ! empty( $data[ 'origin_company' ] ) ? $data[ 'origin_company' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'Street1' ]    = ! empty( $data[ 'origin_address_1' ] ) ? $data[ 'origin_address_1' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'Street2' ]    = ! empty( $data[ 'origin_address_2' ] ) ? $data[ 'origin_address_2' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'City' ]       = ! empty( $data[ 'origin_city' ] ) ? $data[ 'origin_city' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'Region' ]     = ! empty( $data[ 'origin_state' ] ) ? $data[ 'origin_state' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'PostalCode' ] = ! empty( $data[ 'origin_zip' ] ) ? $data[ 'origin_zip' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'Country' ]    = ! empty( $data[ 'origin_country' ] ) ? $data[ 'origin_country' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'Email' ]      = ! empty( $data[ 'origin_email' ] ) ? $data[ 'origin_email' ] : '';
			$this->request[ $method ][ 'OriginAddress' ][ 'Phone' ]      = ! empty( $data[ 'origin_phone' ] ) ? $data[ 'origin_phone' ] : '';
		}

		if ( ! empty( $data[ 'destination_zip' ] ) ) {
			$this->request[ $method ][ 'DestinationAddress' ][ 'FirstName' ]  = ! empty( $data[ 'destination_first_name' ] ) ? $data[ 'destination_first_name' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'LastName' ]   = ! empty( $data[ 'destination_last_name' ] ) ? $data[ 'destination_last_name' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'Company' ]    = ! empty( $data[ 'destination_company' ] ) ? $data[ 'destination_company' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'Street1' ]    = ! empty( $data[ 'destination_address_1' ] ) ? $data[ 'destination_address_1' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'Street2' ]    = ! empty( $data[ 'destination_address_2' ] ) ? $data[ 'destination_address_2' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'City' ]       = ! empty( $data[ 'destination_city' ] ) ? $data[ 'destination_city' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'Region' ]     = ! empty( $data[ 'destination_state' ] ) ? $data[ 'destination_state' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'PostalCode' ] = ! empty( $data[ 'destination_zip' ] ) ? $data[ 'destination_zip' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'Country' ]    = ! empty( $data[ 'destination_country' ] ) ? $data[ 'destination_country' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'Email' ]      = ! empty( $data[ 'destination_email' ] ) ? $data[ 'destination_email' ] : '';
			$this->request[ $method ][ 'DestinationAddress' ][ 'Phone' ]      = ! empty( $data[ 'destination_phone' ] ) ? $data[ 'destination_phone' ] : '';
		}

		if ( ! empty( $data[ 'discount_amount' ] ) ) {
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'Order' ]        = ! empty( $data[ 'discount_order' ] ) ? $data[ 'discount_order' ] : '';
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'Code' ]         = ! empty( $data[ 'discount_code' ] ) ? $data[ 'discount_code' ] : '';
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'Amount' ]       = ! empty( $data[ 'discount_amount' ] ) ? $data[ 'discount_amount' ] : '';
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'DiscountType' ] = ! empty( $data[ 'discount_discount_type' ] ) ? $data[ 'discount_discount_type' ] : '';
		}

		$this->request[ $method ][ 'IsCommited' ]  = $data[ 'is_commited' ];
		$this->request[ $method ][ 'DocumentKey' ] = ! empty( $data[ 'document_key' ] ) ? WCTAXIFY()->store_prefix . '-' . $data[ 'document_key' ] : '';

		$this->request[ 'Request' ][ 'CustomerTaxabilityCode' ]     = $data[ 'is_exempt' ];
		$this->request[ 'Request' ][ 'CustomerRegistrationNumber' ] = '';

		return $this->request( $method );
	}

	/**
	 * Calls the CancelTax method using a RESTful API POST request.
	 *
	 * @since 1.0
	 *
	 * @param string $document_key
	 *
	 * @return object|bool
	 */
	public function cancel_tax( $document_key ) {
		// Initialize an empty array, so old data artifacts don't pollute new data
		$this->request = array();

		$this->request[ 'Request' ][ 'DocumentKey' ] = ! empty( $document_key ) ? WCTAXIFY()->store_prefix . '-' . $document_key : '';

		return $this->request( 'CancelTax' );
	}

	/**
	 * Calls the CommitTax method using a RESTful API POST request.
	 *
	 * @since 1.0
	 *
	 * @param array $data
	 *
	 * @return object|bool
	 */
	public function commit_tax( $data ) {
		// Initialize an empty array, so old data artifacts don't pollute new data
		$this->request = array();

		$this->request[ 'Request' ][ 'DocumentKey' ]         = ! empty( $data[ 'document_key' ] ) ? $data[ 'document_key' ] : '';
		$this->request[ 'Request' ][ 'CommitedDocumentKey' ] = ! empty( $data[ 'commited_document_key' ] ) ? $data[ 'commited_document_key' ] : '';

		return $this->request( 'CommitTax' );
	}

	/**
	 * Calls the GetVersion method using a RESTful API POST request.
	 *
	 * @since 1.0
	 *
	 * @return object|bool
	 */
	public function get_version() {
		return $this->request( 'GetVersion' );
	}

	/**
	 * Calls the VerifyAddress method using a RESTful API POST request.
	 *
	 * @since 1.0
	 *
	 * @param array $address
	 *
	 * @return object|bool
	 */
	public function verify_address( $address = array() ) {
		// Initialize an empty array, so old data artifacts don't pollute new data
		$this->request = array();

		if ( ! empty( $address ) ) {
			$this->request[ 'Request' ][ 'Street1' ]    = ! empty( $address[ 'address_1' ] ) ? $address[ 'address_1' ] : '';
			$this->request[ 'Request' ][ 'Street2' ]    = ! empty( $address[ 'address_2' ] ) ? $address[ 'address_2' ] : '';
			$this->request[ 'Request' ][ 'City' ]       = ! empty( $address[ 'city' ] ) ? $address[ 'city' ] : '';
			$this->request[ 'Request' ][ 'Region' ]     = ! empty( $address[ 'state' ] ) ? $address[ 'state' ] : '';
			$this->request[ 'Request' ][ 'PostalCode' ] = ! empty( $address[ 'zip' ] ) ? $address[ 'zip' ] : '';
			$this->request[ 'Request' ][ 'Country' ]    = ! empty( $address[ 'country' ] ) ? $address[ 'country' ] : '';

			return $this->request( 'VerifyAddress' );
		}

		return false;
	}

	/**
	 * Gets a list of Tax Classes specific to this store's product types.
	 *
	 * @since 1.0
	 *
	 * @return bool|object
	 */
	public function get_codes() {
		// Initialize an empty array, so old data artifacts don't pollute new data
		$this->request = array();

		// Add the security credentials
		$this->request[ 'GetCodes' ][ 'Security' ] = array(
			'PartnerKey' => WCTAXIFY()->taxify_partner_key,
			'Password'   => WCTAXIFY()->taxify_api_key,
		);

		$this->request[ 'GetCodes' ][ 'CodeType' ] = 'Item';

		return $this->request( 'GetCodes' );
	}

} // end of class