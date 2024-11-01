<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Order Class
 *
 * @package     Taxify/includes/Order Admin
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Order_Admin {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Order_Admin
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		if ( WCTAXIFY()->taxify_enabled() ) {
			add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'show_exempt_order_status' ) );
			//add_action( 'woocommerce_admin_order_totals_after_refunded', array( $this, 'refund_remaining' ) );

			/**
			 * AJAX Hooks
			 */
			//add_filter( 'woocommerce_ajax_calc_line_taxes', array( $this, 'ajax_calc_line_taxes' ), 10, 3 );
			//add_action( 'woocommerce_order_refunded', array( $this, 'ajax_order_refunded' ), 10, 2 );
		}
	}

	/**
	 * Displays the exemption status of the order
	 *
	 * @since 1.0
	 *
	 * @param object $order
	 */
	public function show_exempt_order_status( $order ) {
		//$yes = "<span class='dashicons dashicons-yes' style='color: #66ab03;'></span>";
		//$no  = "<span class='dashicons dashicons-no' style='color: #ca336c;'></span>";
		$filed_time = date_i18n( __( 'M j\, Y \a\t h:i a', 'woocommerce-taxify' ), strtotime( get_post_meta( $order->get_id(), '_taxify_commited_date', true ) ) );
		?>
        <p class="form-field form-field-wide"><?php printf( __( '%sTax Exempt? %s%s', 'woocommerce-taxify' ), '<strong>', '</strong>', WCT_TAX()->is_order_tax_exempt( $order->get_id() ) ? 'Yes' : 'No' ); ?></p>
        <p class="form-field form-field-wide"><?php printf( __( '%sFiled with Taxify? %s%s', 'woocommerce-taxify' ), '<strong>', '</strong>', WCT_TAX()->is_commited_to_taxify( $order->get_id() ) ? 'Yes' : 'No' ); ?></p>
		<?php if ( ! empty( $filed_time ) && WCT_TAX()->is_commited_to_taxify( $order->get_id() ) == 'yes' ) : ?>
            <p class="form-field form-field-wide"><?php printf( __( '%sFiled with Taxify on: %s%s', 'woocommerce-taxify' ), '<strong>', '</strong>', esc_attr( $filed_time ) ); ?></p>
		<?php
		endif;
	}

	//public function refund_remaining( $order_id ) {
	//}

	/**
	 * Calculate order line taxes via AJAX
	 *
	 * @since 1.0
	 *
	 * @param array  $items
	 * @param int    $order_id
	 * @param string $country
	 *
	 * @return mixed
	 */
	//public function ajax_calc_line_taxes( $items, $order_id, $country ) {
	//	$order_meta = WCT_FORMAT()->get_meta_flattened( 'post', $order_id );
	//
	//	if ( ! empty( $order_meta ) && $country == 'US' ) {
	//		$address = WCTAXIFY()->tax_based_on() == 'shipping' && ! empty( $order_meta[ '_shipping_postcode' ] ) ? WCT_FORMAT()->get_formatted_shipping_address( $order_meta ) : WCT_FORMAT()->get_formatted_billing_address( $order_meta );
	//		// $discount_amount = $this->get_discount_amount( $order_id );
	//
	//		if ( WCT_TAX()->valid_address( $address ) ) {
	//			$data[ 'document_key' ] = $order_meta[ '_order_key' ] . '-' . $order_id;
	//			$data[ 'is_commited' ]  = WCT_TAX()->is_order_status_completed( $order_id );
	//			$data[ 'customer_key' ] = WCT_TAX()->get_postmeta_customer_id( $order_id );
	//			$data[ 'is_exempt' ]    = WCT_TAX()->tax_exempt_status( $order_id );
	//			// $data[ 'discount_amount' ]        = $discount_amount;
	//			$data[ 'discount_discount_type' ] = ! empty( $order_meta[ 'discount_type' ] ) ? $order_meta[ 'discount_type' ] : '';
	//			$data                             = array_merge( $data, $address );
	//
	//			$item_ids        = $this->get_calc_line_item_ids( $items );
	//			$calc_line_items = $this->get_calc_line_items( $item_ids, $items );
	//
	//			$order = wc_get_order( $order_id );
	//
	//			$total_shipping = $this->get_calc_shipping_cost( $items );
	//
	//			if ( ! empty( $calc_line_items ) && ! empty( $total_shipping ) && WCT_TAX()->is_shipping_taxble( WCT_ORDER()->get_shipping_method( $order ) ) ) {
	//				$line_items = array_merge( $calc_line_items, $total_shipping );
	//			} else {
	//				$line_items = ! empty( $calc_line_items ) ? $calc_line_items : array();
	//			}
	//
	//			if ( ! empty( $line_items ) ) {
	//				$result = WCT_SOAP()->calculate_tax( $data, $line_items );
	//
	//				if ( is_object( $result ) ) {
	//					if ( $result->CalculateTaxResult->ResponseStatus == 'Success' ) {
	//						//WCT_ORDER()->add_or_update_product_order_tax( $result, $order_id );
	//						//
	//						//return $items;
	//						//
	//						//die();
	//
	//						$tax = WCT_ORDER()->get_line_items_tax( $result, $line_items );
	//						//$line_item_tax = $this->update_calc_line_tax( $item_ids, $items, $result, $calc_line_items );
	//						//$tax_total           = $result->CalculateTaxResult->SalesTaxAmount;
	//						//$total_shipping_cost = $this->get_calc_total_shipping_cost( $items );
	//						$tax_item_id = WCT_ORDER()->get_item_id( $order_id, 'tax' );
	//						//$coupon_item_id      = $this->get_item_id( $order_id, 'coupon' );
	//						//$shipping_item_id    = $this->get_item_id( $order_id, 'shipping' );
	//						$shipping_tax = WCT_ORDER()->get_shipping_tax( $result );
	//
	//						//$items[ 'order_item_tax_class' ] = $this->get_calc_order_item_tax_class( $item_ids );
	//						//$items[ 'line_tax' ]             = $this->set_calc_line_tax( $item_ids, $result );
	//						//$items[ 'line_subtotal_tax' ]    = $this->set_calc_line_tax( $item_ids, $result );
	//						//$line_items_total                = $this->get_calc_line_items_total( $item_ids, $items );
	//						//$order_total                     = $this->get_calc_order_total( $total_shipping_cost, $tax_total, $line_items_total );
	//						//$items                           = $this->set_calc_order_total( $order_total, $items );
	//
	//						WCT_ORDER()->add_or_update_product_order_tax( $result, $order_id );
	//						WCT_ORDER()->maybe_add_or_update_order_tax( $order_id, $shipping_tax );
	//
	//						if ( ! empty( $tax_item_id ) ) {
	//							wc_update_order_item_meta( $tax_item_id, 'tax_amount', wc_format_decimal( $tax ) );
	//						}
	//
	//						if ( ! empty( $shipping_tax ) ) {
	//							//$ship_tax = $this->set_calc_shipping_tax( $shipping_tax, $items );
	//						}
	//
	//						//$items['_order_total'] = 51.00;
	//
	//						return $items;
	//
	//						die();
	//
	//						//update_post_meta( $order_id, '_order_tax', $tax );
	//
	//						//if ( ! empty( $tax_item_id ) ) {
	//						//wc_update_order_item_meta( $tax_item_id, 'tax_amount', wc_format_decimal( $tax ) );
	//						//}
	//
	//						//if ( ! empty( $discount_amount ) ) {
	//						//	wc_update_order_item_meta( $coupon_item_id, 'discount_amount', $discount_amount );
	//						//}
	//						//
	//						//if ( ! empty( $shipping_tax ) ) {
	//						//update_post_meta( $order_id, '_order_shipping_tax', $shipping_tax );
	//						//wc_update_order_item_meta( $tax_item_id, 'shipping_tax_amount', wc_format_decimal( $shipping_tax ) );
	//						//$this->update_shipping_taxes( $shipping_item_id, $shipping_tax );
	//						//}
	//					}
	//
	//					if ( $result->CalculateTaxResult->ResponseStatus == 'Failure' ) {
	//						WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CalculateTaxResult->Errors->Error->Message ) ) );
	//
	//						die();
	//					}
	//				}
	//			}
	//		}
	//	}
	//	//return $items;
	//}

	/**
	 * Get an array of line item ids
	 *
	 * @since 1.0
	 *
	 * @param $items
	 *
	 * @return array
	 */
	//private function get_calc_line_item_ids( $items ) {
	//	foreach ( $items[ 'order_item_id' ] as $item_array => $item ) {
	//		$ids[] = $item;
	//	}
	//
	//	return ! empty( $ids ) ? $ids : array();
	//}

	/**
	 * Get lines items and return formatted for the API
	 *
	 * @since 1.0
	 *
	 * @param array $item_ids
	 * @param array $items_array
	 *
	 * @return array
	 */
	//private function get_calc_line_items( $item_ids, $items_array ) {
	//	$items = array();
	//
	//	if ( ! empty( $item_ids ) ) {
	//		foreach ( $item_ids as $key => $item_id ) {
	//			$item_meta  = WCT_FORMAT()->flatten_array( get_metadata( 'order_item', $item_id ) );
	//			$product_id = ! empty( $item_meta[ '_variation_id' ] ) ? $item_meta[ '_variation_id' ] : $item_meta[ '_product_id' ];
	//
	//			$items[] = array(
	//				'LineNumber'          => $product_id,
	//				'ItemKey'             => WCT_TAX()->get_sku( $product_id ),
	//				'ActualExtendedPrice' => $items_array[ 'line_total' ][ $item_id ],
	//				'TaxIncludedInPrice'  => WCT_TAX()->prices_include_tax(),
	//				'Quantity'            => $items_array[ 'order_item_qty' ][ $item_id ],
	//				'ItemDescription'     => get_the_title( $product_id ),
	//				'ItemTaxabilityCode'  => WCT_TAX()->get_product_tax_status( $product_id ),
	//				'ItemCategories'      => 'taxify',
	//			);
	//		}
	//	}
	//
	//	return $items;
	//}

	/**
	 * Get shipping cost as a line item and return formatted for the API
	 *
	 * @since 1.0
	 *
	 * @param array $items_array
	 *
	 * @return array
	 */
	//private function get_calc_shipping_cost( $items_array ) {
	//	$shipping      = array();
	//	$shipping_cost = 0;
	//
	//	foreach ( $items_array[ 'shipping_cost' ] as $key => $cost ) {
	//		if ( ! empty( $cost ) ) {
	//			$shipping_cost += $cost;
	//		}
	//	}
	//
	//	$shipping[] = array(
	//		'ItemKey'             => 'shipping_cost',
	//		'ActualExtendedPrice' => ! empty( $shipping_cost ) ? $shipping_cost : '',
	//		'Quantity'            => '1',
	//		'ItemTaxabilityCode'  => 'Shipping',
	//	);
	//
	//	return $shipping;
	//}

	//private function get_calc_total_shipping_cost( $items_array ) {
	//	$shipping_cost = 0;
	//
	//	foreach ( $items_array[ 'shipping_cost' ] as $key => $cost ) {
	//		if ( ! empty( $cost ) ) {
	//			$shipping_cost += $cost;
	//		}
	//	}
	//
	//	return $shipping_cost;
	//}

	//private function get_calc_order_item_tax_class( $item_ids ) {
	//	$tax_class = array();
	//
	//	if ( ! empty( $item_ids ) ) {
	//		foreach ( $item_ids as $key => $item_id ) {
	//			$tax_class[ $item_id ] = 'taxify';
	//		}
	//	}
	//
	//	return $tax_class;
	//}

	//private function set_calc_line_tax( $item_ids, $result ) {
	//	$line_tax = array();
	//
	//	if ( ! empty( $item_ids ) ) {
	//		foreach ( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail as $taxify_items ) {
	//			foreach ( $item_ids as $key => $item_id ) {
	//				$item_meta  = WCT_FORMAT()->flatten_array( get_metadata( 'order_item', $item_id ) );
	//				$product_id = ! empty( $item_meta[ '_variation_id' ] ) ? $item_meta[ '_variation_id' ] : $item_meta[ '_product_id' ];
	//
	//				if ( $taxify_items->ItemKey != 'shipping_cost' && $taxify_items->LineNumber == $product_id ) {
	//					$line_tax[ $item_id ] = array( WCTAXIFY()->taxify_rate_id => $taxify_items->SalesTaxAmount );
	//				}
	//			}
	//		}
	//	}
	//
	//	return $line_tax;
	//}

	//private function get_calc_line_items_total( $item_ids, $items_array ) {
	//	$total = 0;
	//
	//	if ( ! empty( $item_ids ) ) {
	//		foreach ( $item_ids as $key => $item_id ) {
	//			$total += $items_array[ 'line_total' ][ $item_id ];
	//		}
	//	}
	//
	//	return $total;
	//}

	//private function get_calc_order_total( $total_shipping, $tax_total, $line_items_total ) {
	//	if ( ! empty( $total_shipping ) ) {
	//		$order_total = $total_shipping + $tax_total + $line_items_total;
	//	} else {
	//		$order_total = $tax_total + $line_items_total;
	//	}
	//
	//	return $order_total;
	//}

	//private function set_calc_order_total( $order_total, $items_array ) {
	//	$items_array[ '_order_total' ] = $order_total;
	//
	//	return $items_array;
	//}

	//private function set_calc_shipping_tax( $shipping_tax, $items_array ) {
	//	if ( ! empty( $shipping_tax ) ) {
	//		foreach ( $items_array[ 'shipping_taxes' ] as $item_id => $array ) {
	//			$items_array[ 'shipping_taxes' ][ $item_id ] = array( WCTAXIFY()->taxify_rate_id => $shipping_tax );
	//		}
	//	}
	//
	//	return $items_array;
	//}

	//public function ajax_order_refunded( $order_id, $refund_id ) {
	//}

} // End class