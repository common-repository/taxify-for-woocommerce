<?php

/**
 * Taxify SOAP API Class
 *
 * @package     Taxify/includes/SOAP API
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Taxify_Soap_API {

	private $client;
	private $request = array(); // if this were PHP >= 5.4, or JavaScript, then []

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Soap_API
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Create the SoapClient object used to connect to the remote web service
	 *
	 * @since 1.0
	 *
	 * throws SoapFault for the following conditions:
	 *         Invalid username/password
	 *         WSDL error/unavailable (note that XDebug must be disabled otherwise this becomes a un-catchable fatal.
	 *         This can be done with xdebug_disable() followed by xdebug_enable() )
	 *
	 * @param bool|false $soap_version
	 * @param array      $soap_options
	 *
	 * @return \WC_Taxify_SoapClient
	 */
	private function get_connection( $soap_version = false, $soap_options = array() ) {
		if ( is_object( $this->client ) ) {
			return $this->client;
		}

		$enable_xdebug = function_exists( 'xdebug_is_enabled' ) && xdebug_is_enabled() ? true : false;

		/**
		 * Disable xdebug
		 */
		if ( function_exists( 'xdebug_disable' ) ) {
			xdebug_disable();
		}

		if ( empty( $soap_options ) ) {
			$soap_options = array(
				'trace'        => true,
				'exceptions'   => 1,
				'soap_version' => $soap_version === false ? SOAP_1_1 : SOAP_1_2,
				'cache_wsdl'   => 'WSDL_CACHE_NONE'
			);
		}

		// Create SoapClient object, but don't cache WSDL
		$this->client = @new WC_Taxify_SoapClient( WCTAXIFY()->taxify_api_endpoint, $soap_options );

		/**
		 * Enable xdebug
		 */
		if ( $enable_xdebug && function_exists( 'xdebug_enable' ) ) {
			xdebug_enable();
		}

		return $this->client;
	}

	/**
	 * Adds security credentials to request array
	 *
	 * @since  1.0
	 *
	 * @access private
	 */
	private function request_security_creds() {
		$this->request[ 'Request' ][ 'Security' ] = array(
			'PartnerKey' => WCTAXIFY()->taxify_partner_key,
			'Password'   => WCTAXIFY()->taxify_api_key,
		);
	}

	/**
	 * Sends SoapClient request
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
			try {
				$response = $this->get_connection()->$method( $this->request );

				return $response;
			} catch ( Exception $e ) {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $e->getMessage() ) ) );
			}
		}

		return false;
	}

	/**
	 * Calls the CalculateTax method using a SoapClient request
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
		// Initialize an empty array, so old data artifacts don't pollute new data
		$this->request = array();

		// Add the security credentials
		$this->request_security_creds();

		$this->request[ 'Request' ][ 'DocumentKey' ] = ! empty( $data[ 'document_key' ] ) ? WCTAXIFY()->store_prefix . '-' . $data[ 'document_key' ] : '';
		$this->request[ 'Request' ][ 'TaxDate' ]     = ! empty( $data[ 'tax_date' ] ) ? $data[ 'tax_date' ] : date( 'Y-m-d' );

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
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'Company' ]    = ! empty( $data[ 'origin_company' ] ) ? $data[ 'origin_company' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'Street1' ]    = ! empty( $data[ 'origin_address_1' ] ) ? $data[ 'origin_address_1' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'Street2' ]    = ! empty( $data[ 'origin_address_2' ] ) ? $data[ 'origin_address_2' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'City' ]       = ! empty( $data[ 'origin_city' ] ) ? $data[ 'origin_city' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'Region' ]     = ! empty( $data[ 'origin_state' ] ) ? $data[ 'origin_state' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'PostalCode' ] = ! empty( $data[ 'origin_zip' ] ) ? $data[ 'origin_zip' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'Country' ]    = ! empty( $data[ 'origin_country' ] ) ? $data[ 'origin_country' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'Email' ]      = ! empty( $data[ 'origin_email' ] ) ? $data[ 'origin_email' ] : '';
			$this->request[ 'Request' ][ 'OriginAddress' ][ 'Phone' ]      = ! empty( $data[ 'origin_phone' ] ) ? $data[ 'origin_phone' ] : '';
		}

		if ( ! empty( $data[ 'destination_zip' ] ) ) {
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'FirstName' ]  = ! empty( $data[ 'destination_first_name' ] ) ? $data[ 'destination_first_name' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'LastName' ]   = ! empty( $data[ 'destination_last_name' ] ) ? $data[ 'destination_last_name' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'Company' ]    = ! empty( $data[ 'destination_company' ] ) ? $data[ 'destination_company' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'Street1' ]    = ! empty( $data[ 'destination_address_1' ] ) ? $data[ 'destination_address_1' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'Street2' ]    = ! empty( $data[ 'destination_address_2' ] ) ? $data[ 'destination_address_2' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'City' ]       = ! empty( $data[ 'destination_city' ] ) ? $data[ 'destination_city' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'Region' ]     = ! empty( $data[ 'destination_state' ] ) ? $data[ 'destination_state' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'PostalCode' ] = ! empty( $data[ 'destination_zip' ] ) ? $data[ 'destination_zip' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'Country' ]    = ! empty( $data[ 'destination_country' ] ) ? $data[ 'destination_country' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'Email' ]      = ! empty( $data[ 'destination_email' ] ) ? $data[ 'destination_email' ] : '';
			$this->request[ 'Request' ][ 'DestinationAddress' ][ 'Phone' ]      = ! empty( $data[ 'destination_phone' ] ) ? $data[ 'destination_phone' ] : '';
		}

		if ( ! empty( $data[ 'discount_amount' ] ) ) {
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'Order' ]        = ! empty( $data[ 'discount_order' ] ) ? $data[ 'discount_order' ] : '';
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'Code' ]         = ! empty( $data[ 'discount_code' ] ) ? $data[ 'discount_code' ] : '';
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'Amount' ]       = ! empty( $data[ 'discount_amount' ] ) ? $data[ 'discount_amount' ] : '';
			$this->request[ 'Request' ][ 'Discounts' ][ 'Discount' ][ 'DiscountType' ] = ! empty( $data[ 'discount_discount_type' ] ) ? $data[ 'discount_discount_type' ] : '';
		}

		$this->request[ 'Request' ][ 'IsCommited' ]                 = $data[ 'is_commited' ];
		$this->request[ 'Request' ][ 'CustomerKey' ]                = ! empty( $data[ 'customer_key' ] ) ? WCTAXIFY()->store_prefix . '-' . $data[ 'customer_key' ] : '';
		$this->request[ 'Request' ][ 'CustomerTaxabilityCode' ]     = $data[ 'is_exempt' ];
		$this->request[ 'Request' ][ 'CustomerRegistrationNumber' ] = '';

		return $this->request( 'CalculateTax' );
	}

	/**
	 * Calls the CancelTax method using a SoapClient request
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

		// Add the security credentials
		$this->request_security_creds();
		$this->request[ 'Request' ][ 'DocumentKey' ] = ! empty( $document_key ) ? WCTAXIFY()->store_prefix . '-' . $document_key : '';

		return $this->request( 'CancelTax' );
	}

	/**
	 * Calls the CommitTax method using a SoapClient request
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

		// Add the security credentials
		$this->request_security_creds();
		$this->request[ 'Request' ][ 'DocumentKey' ]         = ! empty( $data[ 'document_key' ] ) ? $data[ 'document_key' ] : '';
		$this->request[ 'Request' ][ 'CommitedDocumentKey' ] = ! empty( $data[ 'commited_document_key' ] ) ? $data[ 'commited_document_key' ] : '';

		return $this->request( 'CommitTax' );
	}

	/**
	 * Calls the GetVersion method using a SoapClient request
	 *
	 * @since 1.0
	 *
	 * @return object|bool
	 */
	public function get_version() {
		return $this->request( 'GetVersion' );
	}

	/**
	 * Calls the VerifyAddress method using a SoapClient request
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

		// Add the security credentials
		$this->request_security_creds();

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
	 * Gets a list of Tax Classes specific to this store's product types
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

	/**
	 * For debugging
	 */
	//return $this->request( 'VerifyAddress' );
	//$request_data = $this->client->__getLastRequestHeaders();
	//$request_data = $this->formatRequest( $this->client->__getLastRequest() );

	//$request_data = $this->client->__getLastRequestHeaders();
	//$request_data .=  $this->client->__getLastRequest();
	//return $request_data;

	//return $this->formatRequest( $this->client->__getLastResponse() );
	//return $this->formatRequest( $this->client->__getLastRequest() );
	//return $this->formatRequest( $this->client->__getLastRequestHeaders() );

	/**
	 * Formats a remote request for viewing in viewsource of a web browser
	 *
	 * @since 1.0
	 *
	 * @param $request
	 */
	//private function formatRequest( $request ) {
	//
	//	print( "\nREQUEST\n" );
	//	print( "-------\n" );
	//	$doc = new \DOMDocument( '1.0' );
	//	$doc->loadXML( $request );
	//	$doc->formatOutput = true;
	//	print( $doc->saveXML() );
	//	print( "-------\n" );
	//}

	/**
	 * Formats a remote response for viewing in viewsource of a web browser
	 *
	 * @since 1.0
	 *
	 * @param $response
	 */
	//private function formatResponse( $response ) {
	//
	//	print( "\nRESPONSE\n" );
	//	print( "-------\n" );
	//	$doc = new \DOMDocument( '1.0' );
	//	$doc->loadXML( $response );
	//	$doc->formatOutput = true;
	//	print( $doc->saveXML() );
	//	print( "-------\n" );
	//}

} // end of class