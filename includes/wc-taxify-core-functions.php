<?php

/**
 * Taxify Core Functions
 *
 * @package     Taxify/includes/Core Functions
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Functions available only for Admin
if ( WCTAXIFY()->is_request( 'admin' ) ) {
	/**
	 * Returns the WC_Taxify_Admin class object
	 *
	 * @since 1.0
	 *
	 * @return \WC_Taxify_Admin
	 */
	function WCT_ADMIN() {
		return WC_Taxify_Admin::instance();
	}

	// Fire now so the class is instantiated immediately, and construct can execute important tasks
	WCT_ADMIN();

	/**
	 * Returns the WC_Taxify_Admin_messages class object
	 *
	 * @since 1.0
	 *
	 * @return \WC_Taxify_Admin_messages
	 */
	function WCT_ADMIN_MESSAGES() {
		return WC_Taxify_Admin_messages::instance();
	}

	/**
	 * Returns the WC_Taxify_Install class object
	 *
	 * @since 1.0
	 *
	 * @return \WC_Taxify_Install
	 */
	function WCT_TAXIFY_INSTALL() {
		return WC_Taxify_Install::instance();
	}

	WCT_TAXIFY_INSTALL();
}

/**
 * Returns the WC_Taxify_Checkout class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Checkout
 */
function WCT_CHECKOUT() {
	return WC_Taxify_Checkout::instance();
}

WCT_CHECKOUT();

/**
 * Returns the WC_Taxify_Format class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Format
 */
function WCT_FORMAT() {
	return WC_Taxify_Format::instance();
}

/**
 * Returns the WC_Taxify_Order_Data_Store class object
 *
 * @since 1.2.8
 *
 * @return \WC_Taxify_Order_Data_Store
 */
function WCT_ORDER_DATA_STORE() {
	return WC_Taxify_Order_Data_Store::instance();
}

/**
 * Returns the WC_Taxify_Product_Data_Store class object
 *
 * @since 1.2.8
 *
 * @return \WC_Taxify_Product_Data_Store
 */
function WCT_PRODUCT_DATA_STORE() {
	return WC_Taxify_Product_Data_Store::instance();
}

/**
 * Returns the WC_Taxify_Product_Data class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Product_Data
 */
function WCT_PRODUCT_DATA() {
	return WC_Taxify_Product_Data::instance();
}

WCT_PRODUCT_DATA();

/**
 * Returns the WC_Taxify_Log class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Log
 */
function WCT_LOG() {
	return WC_Taxify_Log::instance();
}

WCT_LOG();

/**
 * Returns the WC_Taxify_Order class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Order
 */
function WCT_ORDER() {
	return WC_Taxify_Order::instance();
}

WCT_ORDER();

/**
 * Returns the WC_Taxify_Order_Admin class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Order_Admin
 */
function WCT_ORDER_ADMIN() {
	return WC_Taxify_Order_Admin::instance();
}

WCT_ORDER_ADMIN();

/**
 * Returns the WC_Taxify_Scheduler class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Scheduler
 */
function WCT_SCHEDULER() {
	return WC_Taxify_Scheduler::instance();
}

WCT_SCHEDULER();

/**
 * Returns the WC_Taxify_Soap_API class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Soap_API
 */
function WCT_SOAP() {
	return WC_Taxify_Soap_API::instance();
}

/**
 * Returns the WC_Taxify_Tax class object
 *
 * @since 1.0
 *
 * @return \WC_Taxify_Tax
 */
function WCT_TAX() {
	return WC_Taxify_Tax::instance();
}