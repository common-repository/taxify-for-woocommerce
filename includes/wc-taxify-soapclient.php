<?php

/**
 * Taxify SOAP Class
 *
 * @package     Taxify/includes/SoapClient
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Taxify_SoapClient extends SoapClient {

	public function __doRequest( $request, $location, $action, $version, $one_way = null ) {

		// Just in case something needs to be added to the DOM

		//$dom                     = new DomDocument( '1.0', 'UTF-8' );
		//$dom->preserveWhiteSpace = false;
		//$dom->loadXML( $request );
		//$hdr = $dom->createElementNS( 'http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Header' );
		//$dom->documentElement->insertBefore( $hdr, $dom->documentElement->firstChild );
		//$dom->formatOutput = true;
		//$request = $dom->saveXML();

		$request = preg_replace( '/ns1/', 'tax', $request, - 1 );
		$request = preg_replace( '/SOAP-ENV/', 'soapenv', $request, - 1 );

		return parent::__doRequest( $request, $location, $action, $version, $one_way );

		/**
		 * For debugging
		 */
		//$ret = parent::__doRequest( $request, $location, $action, $version, $one_way );
		//$this->__last_request = $request;
		//
		//return $ret;
	}
}