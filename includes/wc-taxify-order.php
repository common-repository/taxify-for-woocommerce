<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Order Class
 *
 * @package     Taxify/includes/Order
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Order {

	private $tax                = 0;
	private $shipping_tax       = 0;
	private $shipping_item_id   = 0;
	private $is_shipping_taxble = false;

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Order
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		/**
		 * @see \WC_Abstract_Order
		 * do_action( 'woocommerce_order_status_' . $new_status, $this->id );
		 * completed, processing, on-hold, cancelled
		 */
		if ( WCTAXIFY()->taxify_enabled() ) {
			add_action( 'woocommerce_order_status_completed', array( $this, 'call_order_completed' ) );
			add_action( 'woocommerce_saved_order_items', array( $this, 'call_order_cancelled' ), 10, 2 );
			add_action( 'woocommerce_order_status_changed', array( $this, 'order_cancelled' ), 10, 3 );
			add_action( 'woocommerce_order_refunded', array( $this, 'call_order_completed_or_cancel_for_refund_update' ), 10, 2 );
			add_action( 'woocommerce_refund_deleted', array( $this, 'call_order_completed_for_deleted_refund' ), 10, 2 );
			add_action( 'woocommerce_delete_order_items', array( $this, 'delete_order' ) );
		}
	}

	/**
	 * Commits an order in Taxify using the API, and saves an order with calculated taxes.
	 *
	 * @since 1.0
	 *
	 * @param int  $order_id
	 * @param bool $is_commited
	 *
	 * @throws \WC_Data_Exception
	 */
	public function order_completed( $order_id, $is_commited = false ) {
		//$order_meta = WCT_FORMAT()->get_meta_flattened( 'post', $order_id );
		$order_meta = WCT_ORDER_DATA_STORE()->get_meta_data( $order_id );
		/**
		 * Don't file orders that have been deleted, are tax exempt, or were sold for a value of zero, for example $0
		 *
		 * @see line 173
		 */
		//if ( ! empty( $order_meta ) && ! WCT_TAX()->is_order_tax_exempt( $order_id ) && ! empty( $order_meta[ 'total' ] ) ) {

		/**
		 * All orders are filed with Taxify, including Tax Exempt and (free item) orders sold for zero ($0).
		 */
		/**
		 * $order_meta[ '_order_total' ] = $order_meta[ 'total' ]
		 * $order_meta[ '_shipping_postcode' ] = $order_meta['shipping'][ 'postcode' ]
		 */
		if ( ! empty( $order_meta ) && ! empty( $order_meta[ 'total' ] ) ) {
			$address         = WCTAXIFY()->tax_based_on() == 'shipping' && ! empty( $order_meta[ 'shipping' ][ 'postcode' ] ) ? WCT_FORMAT()->get_formatted_shipping_address( $order_meta ) : WCT_FORMAT()->get_formatted_billing_address( $order_meta );
			$discount_amount = $this->get_discount_amount( $order_id );

			if ( WCT_TAX()->valid_address( $address ) ) {
				//$data[ 'discount_amount' ]        = $discount_amount;
				$order                            = wc_get_order( $order_id );
				$data[ 'document_key' ]           = $order_id;
				$data[ 'tax_date' ]               = WCT_FORMAT()->order_date_for_api( $order_id );
				$data[ 'is_commited' ]            = $is_commited !== false ? true : false;
				$data[ 'customer_key' ]           = WCT_TAX()->get_postmeta_customer_id( $order_id );
				$data[ 'is_exempt' ]              = WCT_TAX()->tax_exempt_status( $order_id );
				$data[ 'discount_discount_type' ] = ! empty( $order_meta[ 'discount_type' ] ) ? $order_meta[ 'discount_type' ] : '';
				$data                             = array_merge( $data, $address );
				$items                            = $this->get_cart_line_items( $order );
				$this->shipping_item_id           = $this->get_item_id( $order_id, 'shipping' );
				$this->is_shipping_taxble         = WCT_TAX()->is_shipping_taxble( $this->get_shipping_method( $order_id ) );

				//update_option( 'shipping', $this->shipping_item_id );

				if ( ! empty( $items ) && $this->is_shipping_taxble ) {
					$total_shipping = $this->get_shipping_cost();

					if ( ! empty( $total_shipping ) ) {
						$line_items = array_merge( $items, $total_shipping );
					} else {
						$line_items = ! empty( $items ) ? $items : array();
					}
				} else {
					$line_items = ! empty( $items ) ? $items : array();
				}

				if ( ! empty( $line_items ) ) {
					$refunds = $this->get_cart_line_item_refunds( $order_id );
					$result  = empty( $refunds ) ? WCT_SOAP()->calculate_tax( $data, $line_items ) : WCT_SOAP()->calculate_tax( $data, $line_items, $refunds );

					if ( is_object( $result ) && ! empty( $result ) ) {
						if ( $result->CalculateTaxResult->ResponseStatus == 'Success' ) {
							//$tax_total = $result->CalculateTaxResult->SalesTaxAmount;

							$this->tax      = $this->get_line_items_tax( $result, $line_items );
							$tax_item_id    = $this->get_item_id( $order_id, 'tax' );
							$coupon_item_id = $this->get_item_id( $order_id, 'coupon' );

							if ( $this->is_shipping_taxble ) {
								$this->shipping_tax = $this->get_shipping_tax( $result );
							}

							WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_order_tax', $this->tax );

							if ( ! empty( $tax_item_id ) ) {
								wc_update_order_item_meta( $tax_item_id, 'tax_amount', wc_format_decimal( $this->tax ) );
								wc_update_order_item_meta( $tax_item_id, 'tax_total', wc_format_decimal( $this->tax ) );
							}

							if ( ! empty( $discount_amount ) ) {
								wc_update_order_item_meta( $coupon_item_id, 'discount_amount', $discount_amount );
							}

							WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_order_shipping_tax', wc_format_decimal( $this->shipping_tax ) );
							$this->add_or_update_product_order_tax( $result, $order_id );
							$this->maybe_add_or_update_order_tax( $order_id );
							$this->update_shipping_taxes();
							wc_update_order_item_meta( $this->shipping_item_id, 'total_tax', wc_format_decimal( $this->shipping_tax ) );

							if ( $is_commited !== false ) {
								WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'yes' );
								WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_commited_date', current_time( 'mysql' ) );
							}

							// Unfile the order at Taxify if the tax is zero, and the order has a completed status
							//if ( empty( $tax_total ) && WCT_TAX()->is_order_status_completed( $order_id ) ) {
							/**
							 * Unfile/Cancel Tax Exempt orders from Taxify, or orders that have a value of zero, for example $0
							 *
							 * @see line 85
							 */
							// $this->delete_order_from_taxify( $order_id );
							//}
						}

						if ( $result->CalculateTaxResult->ResponseStatus == 'Failure' ) {
							WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'no' );
							WCT_ORDER_DATA_STORE()->delete_meta( $order_id, '_taxify_commited_date' );
							// Schedule order to be filed in Taxify later to make sure it gets filed
							WCT_SCHEDULER()->schedule_missed_order( $order_id );

							WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: The API returned an error. An attempt will be made to file order# %d again one hour from now.', 'woocommerce-taxify' ), absint( $order_id ) ) );
							WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CalculateTaxResult->Errors->Error->Message ) ) );
						}
					} else {
						WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'no' );
						WCT_ORDER_DATA_STORE()->delete_meta( $order_id, '_taxify_commited_date' );
						// Schedule order to be filed in Taxify later to make sure it gets filed
						WCT_SCHEDULER()->schedule_missed_order( $order_id );
						WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: The connection to the API failed. An attempt will be made to file order# %d again one hour from now.', 'woocommerce-taxify' ), absint( $order_id ) ) );
					}
				}
			}
		}
	}

	/**
	 * Calls order_completed, which Commits an order in Taxify using the API.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @throws \WC_Data_Exception
	 */
	public function call_order_completed( $order_id ) {
		$this->order_completed( $order_id, true );
	}

	/**
	 * Cancels an order in Taxify, then the tax recalculates.
	 *
	 * @since 1.0
	 *
	 * @param int    $order_id
	 * @param string $old_status
	 * @param string $new_status
	 *
	 * @throws \WC_Data_Exception
	 */
	public function order_cancelled( $order_id, $old_status, $new_status ) {
		// Prevents saved from triggering completed or refunded order cancellation in Taxify
		if ( ! WCT_TAX()->is_order_status_completed( $order_id ) || ! WCT_TAX()->is_order_status_refunded( $order_id ) ) {
			$order_statuses = array(
				'saved',
				'pending',
				'processing',
				'on-hold',
				'cancelled',
				'failed',
			);

			if ( in_array( $new_status, $order_statuses ) ) {
				if ( $new_status != 'completed' || $new_status != 'refunded' ) {
					$result = WCT_SOAP()->cancel_tax( $order_id );
				}

				if ( ! empty( $result ) && is_object( $result ) ) {
					if ( $result->CancelTaxResult->ResponseStatus == 'Success' ) {
						WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'no' );
						WCT_ORDER_DATA_STORE()->delete_meta( $order_id, '_taxify_commited_date' );
						// Now recalculate the tax to update the order
						$this->order_completed( $order_id, false );
					}

					if ( $result->CancelTaxResult->ResponseStatus == 'Failure' ) {
						WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CancelTaxResult->Errors->Error->Message ) ) );
					}
				}
			}
		}
	}

	/**
	 * Cancels an order in Taxify.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 */
	public function delete_order_from_taxify( $order_id ) {
		if ( ! empty( $order_id ) ) {
			$result = WCT_SOAP()->cancel_tax( $order_id );

			if ( $result->CancelTaxResult->ResponseStatus == 'Success' ) {
				WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'no' );
				WCT_ORDER_DATA_STORE()->delete_meta( $order_id, '_taxify_commited_date' );
			}
		}
	}

	/**
	 * Calls order_cancelled when an order is saved to trigger on events such as the Calculate Taxes AJAX button.
	 *
	 * @since 1.0
	 *
	 * @param int   $order_id
	 * @param array $items
	 *
	 * @throws \WC_Data_Exception
	 */
	public function call_order_cancelled( $order_id, $items ) {
		if ( ! WCT_TAX()->is_commited_to_taxify( $order_id ) ) {
			$this->order_cancelled( $order_id, '', 'saved' );
		}
	}

	/**
	 * Call order_completed() to add or remove refund as a line item to orders.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 * @param int $refund_id
	 *
	 * @throws \WC_Data_Exception
	 */
	public function call_order_completed_or_cancel_for_refund_update( $order_id, $refund_id ) {
		if ( $this->is_order_fully_refunded( $order_id ) ) {
			$result = WCT_SOAP()->cancel_tax( $order_id );

			if ( ! empty( $result ) && is_object( $result ) ) {
				if ( $result->CancelTaxResult->ResponseStatus == 'Success' ) {
					WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'no' );
					WCT_ORDER_DATA_STORE()->delete_meta( $order_id, '_taxify_commited_date' );
				}

				if ( $result->CancelTaxResult->ResponseStatus == 'Failure' ) {
					WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CancelTaxResult->Errors->Error->Message ) ) );
				}
			}
		} else {
			$this->order_completed( $order_id, true );
		}
	}

	/**
	 * Files a refund order at Taxify using a negative price.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 * @param int $refund_id
	 */
	public function order_refunded( $order_id, $refund_id ) {
		//$order_meta  = WCT_FORMAT()->get_meta_flattened( 'post', $order_id );
		$order_meta = WCT_ORDER_DATA_STORE()->get_meta_data( $order_id );

		if ( WCTAXIFY()->wc_version >= '3.0' ) {
			$refund       = new WC_Order_Refund( $refund_id );
			$refund_total = - abs( $refund->get_amount() );
		} else {
			$refund_total = get_post_meta( $refund_id, '_order_total', true );
		}

		if ( ! empty( $order_meta ) ) {
			$address = WCTAXIFY()->tax_based_on() == 'shipping' && ! empty( $order_meta[ 'shipping' ][ 'postcode' ] ) ? WCT_FORMAT()->get_formatted_shipping_address( $order_meta ) : WCT_FORMAT()->get_formatted_billing_address( $order_meta );

			if ( WCT_TAX()->valid_address( $address ) ) {
				$data = array(
					'document_key' => $refund_id,
					'tax_date'     => WCT_FORMAT()->order_date_for_api( $order_id ),
					'is_commited'  => true,
					'customer_key' => WCT_TAX()->get_postmeta_customer_id( $order_id ),
					'is_exempt'    => WCT_TAX()->tax_exempt_status( $order_id ),
				);

				$items[ 0 ] = array(
					'LineNumber'          => 1,
					'ItemKey'             => 'refund',
					'ActualExtendedPrice' => $refund_total,
					'TaxIncludedInPrice'  => false,
					'Quantity'            => 1,
					'ItemDescription'     => 'Refund for Order# ' . $order_id,
					'ItemTaxabilityCode'  => 'none',
					'ItemCategories'      => 'taxify',
				);

				$data   = array_merge( $data, $address );
				$result = WCT_SOAP()->calculate_tax( $data, $items );

				if ( is_object( $result ) && ! empty( $result ) ) {
					if ( $result->CalculateTaxResult->ResponseStatus == 'Success' ) {
						WCT_ORDER_DATA_STORE()->update_meta( $refund_id, '_taxify_is_commited', 'yes' );
						WCT_ORDER_DATA_STORE()->update_meta( $refund_id, '_taxify_commited_date', current_time( 'mysql' ) );
					}

					if ( $result->CalculateTaxResult->ResponseStatus == 'Failure' ) {
						WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CalculateTaxResult->Errors->Error->Message ) ) );
					}
				} else {
					WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'no' );
					WCT_ORDER_DATA_STORE()->delete_meta( $order_id, '_taxify_commited_date' );

					WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: The connection to the API failed. An attempt will be made to file refund# %d for order# %d again 24 hours from now.', 'woocommerce-taxify' ), absint( $refund_id ), absint( $order_id ) ) );
				}
			}
		}
	}

	/**
	 * Call order_completed() to recalculate taxes when a refund is added or removed.
	 *
	 * @since 1.0
	 *
	 * @param int $refund_id
	 * @param int $order_id
	 *
	 * @throws \WC_Data_Exception
	 */
	public function call_order_completed_for_deleted_refund( $refund_id, $order_id ) {
		$this->order_completed( $order_id, true );
	}

	/**
	 * Deletes the refund order filed at Taxify.
	 *
	 * @since 1.0
	 *
	 * @param int $refund_id
	 * @param int $order_id
	 */
	public function refund_deleted( $refund_id, $order_id ) {
		$result = WCT_SOAP()->cancel_tax( $refund_id );

		if ( is_object( $result ) ) {
			if ( $result->CancelTaxResult->ResponseStatus == 'Success' ) {
				WCT_ORDER_DATA_STORE()->update_meta( $order_id, '_taxify_is_commited', 'no' );
				WCT_ORDER_DATA_STORE()->delete_meta( $order_id, '_taxify_commited_date' );
			}

			if ( $result->CancelTaxResult->ResponseStatus == 'Failure' ) {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CancelTaxResult->Errors->Error->Message ) ) );
			}
		}
	}

	/**
	 * Cancels an order in Taxify using the API when an order is deleted.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 */
	public function delete_order( $order_id ) {
		$result = WCT_SOAP()->cancel_tax( $order_id );

		if ( is_object( $result ) ) {
			if ( $result->CancelTaxResult->ResponseStatus == 'Success' ) {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY NOTICE - Order# %d has been deleted.', 'woocommerce-taxify' ), absint( $order_id ) ) );
			}

			if ( $result->CancelTaxResult->ResponseStatus == 'Failure' ) {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CancelTaxResult->Errors->Error->Message ) ) );
			}
		}
	}

	/**
	 * Commits an order already calculated by Taxify in the shopping cart.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 */
	public function commit_order( $order_id ) {
		$data[ 'document_key' ]          = WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_taxify_cart_document_key' );
		$data[ 'commited_document_key' ] = uniqid( 'commited_document_key_' );

		$result = WCT_SOAP()->commit_tax( $data );

		if ( is_object( $result ) ) {
			if ( $result->CommitTaxResult->ResponseStatus == 'Success' ) {
				WCT_ORDER_DATA_STORE()->update_meta( $order_id, 'taxify_commited_document_key', $data[ 'commited_document_key' ] );
			}

			if ( $result->CommitTaxResult->ResponseStatus == 'Failure' ) {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $result->CommitTaxResult->Errors->Error->Message ) ) );
			}
		}
	}

	/**
	 * Gets the discount amount.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return mixed|string
	 */
	public function get_discount_amount( $order_id ) {
		$shipping_item_id = $this->get_item_id( $order_id, 'coupon' );
		$discount_amount  = wc_get_order_item_meta( $shipping_item_id, 'discount_amount', true );

		return ! empty( $discount_amount ) ? $discount_amount : '';
	}

	/**
	 * Get product and refund line items then return formatted for the API.
	 *
	 * @since 1.0
	 *
	 * @param object $order
	 *
	 * @return array
	 */
	private function get_cart_line_items( $order ) {
		$items = array();

		/**
		 * Send products as line items with postive ActualExtendedPrice
		 */
		if ( ! empty( $order ) && count( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = ! empty( $item[ 'variation_id' ] ) ? $item[ 'variation_id' ] : $item[ 'product_id' ];

				$items[] = array(
					'LineNumber'          => $product_id,
					'ItemKey'             => WCTAXIFY()->store_prefix . '-' . WCT_TAX()->get_sku( $product_id ),
					'ActualExtendedPrice' => ! empty( $item[ 'line_total' ] ) ? $item[ 'line_total' ] : 0, // free item
					'TaxIncludedInPrice'  => WCT_TAX()->prices_include_tax(),
					'Quantity'            => $item[ 'qty' ],
					'ItemDescription'     => $item[ 'name' ],
					'ItemTaxabilityCode'  => WCT_TAX()->get_product_tax_status( $product_id ),
					'ItemCategories'      => 'taxify',
				);
			}
		}

		return $items;
	}

	/**
	 * Get refunds as a discount then return formatted for the API.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	private function get_cart_line_item_refunds( $order_id ) {
		$items = array();

		/**
		 * Send refunds as a discount with a postive Amount
		 */
		$refunds = $this->get_refunds( $order_id );

		if ( ! empty( $refunds ) && ! empty( $order_id ) ) {
			foreach ( $refunds as $refund ) {
				$refund_id = ! empty( $refund->get_id() ) ? $refund->get_id() : $refund->id;

				if ( WCTAXIFY()->wc_version >= '3.0' ) {
					$refund       = new WC_Order_Refund( $refund_id );
					$refund_total = abs( $refund->get_amount() );
				} else {
					$refund_total = get_post_meta( $refund_id, '_order_total', true );
				}

				if ( ! empty( $refund_total ) ) {
					$items[] = array(
						'Code'         => 'Refund #' . $refund_id . ' for Order# ' . $order_id,
						'Amount'       => abs( $refund_total ), // Make sure the float is positive
						'DiscountType' => 'refund',
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Get total line items tax.
	 *
	 * @since 1.0
	 *
	 * @param object $result
	 * @param array  $line_items
	 *
	 * @return int
	 */
	public function get_line_items_tax( $result, $line_items ) {
		$tax_total = 0;

		if ( ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail ) ) {
			foreach ( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail as $taxify_items ) {
				if ( ! empty( $line_items ) && is_array( $line_items ) ) {
					foreach ( $line_items as $key => $item ) {
						if ( ! empty( $taxify_items->ItemKey ) && $taxify_items->ItemKey != 'shipping_cost' && ! empty( $item[ 'LineNumber' ] ) && $taxify_items->LineNumber == $item[ 'LineNumber' ] ) {
							$tax_total += $taxify_items->SalesTaxAmount;
						} elseif ( ! empty( $item[ 'LineNumber' ] ) && ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->LineNumber ) && $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->LineNumber == $item[ 'LineNumber' ] ) {
							$tax_total = $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount;
						}
					}
				}
			}
		}

		return $tax_total;
	}

	/**
	 * Get the chosen shipping method.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function get_shipping_method( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order ) ) {
			$shipping = $order->get_shipping_methods();

			if ( ! empty( $shipping ) && is_array( $shipping ) ) {
				foreach ( $shipping as $key => $method ) {
					$shipping_method_array[] = $method;
				}
			}
		}

		if ( ! empty( $shipping_method_array ) ) {
			$obj_length = strlen( $shipping_method_array[ 0 ][ 'method_id' ] );
			$last_char  = substr( $shipping_method_array[ 0 ][ 'method_id' ], $obj_length - 1, $obj_length );

			// If the last character is a number replace colon with an underscore to complete the name.
			if ( is_numeric( $last_char ) ) {
				$shipping_id = str_replace( ':', '_', $shipping_method_array[ 0 ][ 'method_id' ] );
			} else {
				// If the last character is not a number, just use the name before the colon.
				$shipping_method = WCT_TAX()->find_needle( $shipping_method_array[ 0 ][ 'method_id' ], ':' );
				$shipping_id     = ! empty( $shipping_method ) ? $shipping_method : $shipping_method_array[ 0 ][ 'method_id' ];
			}
		}

		return ! empty( $shipping_id ) ? $shipping_id : false;
	}

	/**
	 * Get shipping cost as a line item then return formatted for the API.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_shipping_cost() {
		$shipping      = array();
		$shipping_cost = wc_get_order_item_meta( $this->shipping_item_id, 'cost', true );

		$shipping[] = array(
			'ItemKey'             => 'shipping_cost',
			'ActualExtendedPrice' => ! empty( $shipping_cost ) ? $shipping_cost : '',
			'Quantity'            => '1',
			'ItemTaxabilityCode'  => 'Shipping',
		);

		return $shipping;
	}

	/**
	 * Get shipping tax.
	 *
	 * @since 1.0
	 *
	 * @param object $result
	 *
	 * @return string
	 */
	public function get_shipping_tax( $result ) {
		if ( ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail ) ) {
			foreach ( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail as $taxify_items ) {
				if ( $taxify_items->ItemKey == 'shipping_cost' ) {
					$shipping_tax = $taxify_items->SalesTaxAmount;
				}
			}
		}

		return ! empty( $shipping_tax ) ? $shipping_tax : 0;
	}

	/**
	 * Get order_item_id.
	 *
	 * @since 1.0
	 *
	 * @param int    $order_id
	 * @param string $type
	 *
	 * @return bool|int
	 */
	public function get_item_id( $order_id, $type ) {
		global $wpdb;

		$item_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT 		order_item_id
			FROM 		{$wpdb->prefix}woocommerce_order_items
			WHERE 		order_id = %d
			AND 		order_item_type = %s
		", absint( $order_id ), esc_attr( $type ) ) );

		return ! empty( $item_id ) ? (int) $item_id : false;
	}

	/**
	 * Get order_item_ids.
	 *
	 * Output example:
	 * Array
	 *(
	 *  [0] = 625
	 *  [1] = 626
	 *  [2] = 627
	 *  [3] = 628
	 *)
	 *
	 * @since 1.0
	 *
	 * @param int    $order_id
	 * @param string $type
	 *
	 * @return array|bool
	 */
	public function get_item_ids( $order_id, $type ) {
		global $wpdb;

		$item_ids = $wpdb->get_results( $wpdb->prepare( "
			SELECT 		order_item_id
			FROM 		{$wpdb->prefix}woocommerce_order_items
			WHERE 		order_id = %d
			AND 		order_item_type = %s
		", absint( $order_id ), esc_attr( $type ) ), ARRAY_A );

		if ( ! empty( $item_ids ) && is_array( $item_ids ) ) {
			foreach ( $item_ids as $order_item_id => $item ) {
				$order_items[] = $item[ 'order_item_id' ];
			}

			return ! empty( $order_items ) ? $order_items : false;
		}

		return false;
	}

	/**
	 * Updates Taxify shipping tax calculation for the order.
	 *
	 * @since 1.0
	 */
	public function update_shipping_taxes() {
		$taxes[ WCTAXIFY()->taxify_rate_id ] = wc_format_decimal( $this->shipping_tax );

		if ( WCTAXIFY()->wc_version >= '3.0' ) {
			wc_delete_order_item_meta( $this->shipping_item_id, 'taxes' );
			wc_update_order_item_meta( $this->shipping_item_id, 'taxes', array( 'total' => $taxes ) );
		}
	}

	/**
	 * Adds or updates taxes for an item in order_item_meta.
	 *
	 * @since 1.0
	 *
	 * @param object $result
	 * @param int    $order_id
	 * @param array  $item_ids
	 */
	public function add_or_update_product_order_tax( $result, $order_id, $item_ids = array() ) {
		if ( empty( $item_ids ) ) {
			$item_ids = $this->get_item_ids( $order_id, 'line_item' );
		}

		if ( ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail ) ) {
			foreach ( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail as $taxify_items ) {
				if ( ! empty( $item_ids ) && is_array( $item_ids ) ) {
					foreach ( $item_ids as $key => $item_id ) {
						$item_meta  = WCT_FORMAT()->flatten_array( get_metadata( 'order_item', $item_id ) );
						$product_id = ! empty( $item_meta[ '_variation_id' ] ) ? $item_meta[ '_variation_id' ] : $item_meta[ '_product_id' ];

						if ( ! empty( $taxify_items->ItemKey ) && $taxify_items->ItemKey != 'shipping_cost' && $taxify_items->LineNumber == $product_id ) {
							wc_update_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( $taxify_items->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax', wc_format_decimal( $taxify_items->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax_data', array(
								'total'    => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $taxify_items->SalesTaxAmount ) ),
								'subtotal' => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $taxify_items->SalesTaxAmount ) )
							) );
						} else if ( ! empty( $taxify_items->LineNumber ) && $taxify_items->LineNumber == $product_id ) {
							wc_update_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( $taxify_items->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax', wc_format_decimal( $taxify_items->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax_data', array(
								'total'    => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $taxify_items->SalesTaxAmount ) ),
								'subtotal' => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $taxify_items->SalesTaxAmount ) )
							) );
						} elseif ( ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->ItemKey ) && $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->ItemKey != 'shipping_cost' && $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->LineNumber == $product_id ) {
							wc_update_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax', wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax_data', array(
								'total'    => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) ),
								'subtotal' => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) )
							) );
						} elseif ( ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->LineNumber ) && $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->LineNumber == $product_id ) {
							wc_update_order_item_meta( $item_id, '_line_subtotal_tax', wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax', wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) );
							wc_update_order_item_meta( $item_id, '_line_tax_data', array(
								'total'    => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) ),
								'subtotal' => array( WCTAXIFY()->taxify_rate_id => wc_format_decimal( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail->SalesTaxAmount ) )
							) );
						}
					}
				}
			}
		}
	}

	/**
	 * Adds or updates the total tax and shipping tax for an item in order_item_meta.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 * @param int $shipping_tax
	 *
	 * @throws \WC_Data_Exception
	 */
	public function maybe_add_or_update_order_tax( $order_id, $shipping_tax = 0 ) {
		if ( ! empty( $this->tax ) ) {
			$item_id = $this->get_item_id( $order_id, 'tax' );

			if ( ! empty( $item_id ) ) {
				wc_update_order_item_meta( $item_id, 'tax_amount', wc_format_decimal( $this->tax ) );
				wc_update_order_item_meta( $item_id, 'shipping_tax_amount', wc_format_decimal( $this->shipping_tax ) );
			} else {
				$order = wc_get_order( $order_id );
				$order->add_tax( WCTAXIFY()->taxify_rate_id, $this->tax, $this->shipping_tax );
			}
		}
	}

	/**
	 * Get order refunds.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function get_refunds( $order_id ) {
		$refunds = array();

		$refund_items = get_posts( array(
			                           'post_type'      => 'shop_order_refund',
			                           'post_parent'    => $order_id,
			                           'posts_per_page' => - 1,
			                           'post_status'    => 'any',
			                           'fields'         => 'ids'
		                           ) );

		if ( ! empty( $refund_items ) ) {
			foreach ( $refund_items as $refund_id ) {
				$refunds[] = new WC_Order_Refund( $refund_id );
			}
		}

		return $refunds;
	}

	/**
	 * Get all orders with a completed or refunded status. Optionally get orders after a certain time and date.
	 *
	 * @since 1.0
	 *
	 * @param string $time_for_comparison
	 *
	 * @return array
	 */
	public function get_order_completed_ids( $time_for_comparison = '' ) {
		global $wpdb;

		$post_ids = $wpdb->get_results( $wpdb->prepare( "
			SELECT 		ID
			FROM 		{$wpdb->prefix}posts
			WHERE 		post_type = %s
			AND 		post_status = %s
			OR 			post_status = %s
			AND 		post_date > %s
		", esc_attr( 'shop_order' ), esc_attr( 'wc-completed' ), esc_attr( 'wc-refunded' ), $time_for_comparison ), ARRAY_A );

		if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
			foreach ( $post_ids as $post_id_key => $post_id ) {
				$ids[] = $post_id[ 'ID' ];
			}
		}

		return ! empty( $ids ) ? $ids : array();
	}

	/**
	 * Get all refunds with a completed status.
	 * Optionally get orders after a certain time and date.
	 *
	 * @since 1.0
	 *
	 * @param string $time_for_comparison
	 *
	 * @return array
	 */
	public function get_refund_ids( $time_for_comparison = '' ) {
		global $wpdb;

		$post_ids = $wpdb->get_results( $wpdb->prepare( "
			SELECT 		ID, post_parent
			FROM 		{$wpdb->prefix}posts
			WHERE 		post_type = %s
			AND 		post_status = %s
			AND 		post_date > %s
		", esc_attr( 'shop_order_refund' ), esc_attr( 'wc-completed' ), $time_for_comparison ), ARRAY_A );

		if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
			foreach ( $post_ids as $post_id_key => $post_id ) {
				$ids[] = array( 'order_id' => $post_id[ 'post_parent' ], 'refund_id' => $post_id[ 'ID' ] );
			}
		}

		return ! empty( $ids ) ? $ids : array();
	}

	/**
	 * Get refund_ids for a specific order_id with a completed status.
	 * Optionally get orders after a certain time and date.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function get_refund_ids_from_order_id( $order_id ) {
		global $wpdb;

		$ids = array();

		$post_ids = $wpdb->get_results( $wpdb->prepare( "
			SELECT 		ID
			FROM 		{$wpdb->prefix}posts
			WHERE 		post_parent = %d
			AND 		post_type = %s
			AND 		post_status = %s
		", absint( $order_id ), esc_attr( 'shop_order_refund' ), esc_attr( 'wc-completed' ) ), ARRAY_A );

		if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
			foreach ( $post_ids as $post_id_key => $post_id ) {
				$ids[] = $post_id[ 'ID' ];
			}
		}

		return $ids;
	}

	/**
	 * Uses the refund_id to find the post_parent (order_id).
	 *
	 * @since 1.0
	 *
	 * @param int $refund_id
	 *
	 * @return bool|int
	 */
	public function get_refund_parent_id( $refund_id ) {
		global $wpdb;

		$parent_id = $wpdb->get_var( $wpdb->prepare( "
			SELECT 		post_parent
			FROM 		{$wpdb->prefix}posts
			WHERE 		ID = %d
			AND 		post_type = %s
			AND 		post_status = %s
		", absint( $refund_id ), esc_attr( 'shop_order_refund' ), esc_attr( 'wc-completed' ) ) );

		return ! empty( $parent_id ) ? (int) $parent_id : false;
	}

	/**
	 * Recalculate order totals along with recalculated tax totals.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 */
	public function recalculate_order_totals( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( is_object( $order ) && ! empty( $order ) ) {
			// Calculate totals with taxes
			$order->calculate_totals( true );
			// Update tax calculations
			//$this->order_completed( $order_id, false );
			// Recalculate totals with updated tax calculations
			$order->calculate_totals( false );
		}
	}

	/**
	 * Returns true if an order is fully refunded, else false.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function is_order_fully_refunded( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( WCTAXIFY()->wc_version >= '3.0' ) {
			$order_total = $order->get_total();
		} else {
			$order_total = get_post_meta( $order_id, '_order_total', true );
		}

		return $order->get_total_refunded() >= $order_total || get_post_status( $order_id ) == 'wc-refunded' ? true : false;
	}

} // End class