<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Checkout Class
 *
 * @package     Taxify/includes/Checkout
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 */
class WC_Taxify_Checkout {

	private $valid_address    = false;
	private $document_key     = '';
	private $customer_key     = '';
	private $shipping_tax     = 0;
	private $tax_display_cart = '';
	private $total_tax        = 0;

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Checkout
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		if ( WCTAXIFY()->taxify_enabled() ) {
			$this->document_key = uniqid( 'cart_' );

			//if ( class_exists( 'WC_Cart_Totals' ) ) { // >= Woo 3.2
			//    //add_action( 'woocommerce_after_calculate_totals', array( $this, 'calculate_totals' ) );
			//    add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_totals' ) );
			//} else {
			//    add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ) );
			//}

			add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_totals' ) );

			// WC >= 3.2
			if ( WCTAXIFY()->wc_version >= '3.2' ) {
				add_filter( 'woocommerce_calculated_total', array( $this, 'calculated_total' ), 10, 2 );
			}

			add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'hidden_order_postmeta' ) );

			if ( WCT_TAX()->is_tax_exempt_checkbox_enabled_for_checkout() ) {
				add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'tax_exempt_checkbox' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'load_js' ) );
				add_action( 'wp_ajax_nopriv_wc_taxify_apply_tax_exempt', array( $this, 'apply_tax_exempt' ) );
				add_action( 'wp_ajax_wc_taxify_apply_tax_exempt', array( $this, 'apply_tax_exempt' ) );
				add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_tax_exempt_checkbox' ), 10, 2 );
				add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_order_meta_hidden_order_postmeta' ), 10, 2 );
			}

			// Shipping tax isn't updated from the WC()->cart tax data in the cart
			if ( WCTAXIFY()->wc_version >= '3.0' ) {
				add_action( 'woocommerce_new_order_item', array( $this, 'add_shipping_taxes' ), 10, 3 );
			} else {
				// Shipping tax isn't updated from the WC()->cart tax data in the cart
				add_action( 'woocommerce_order_add_shipping', array( $this, 'add_shipping_taxes' ), 10, 3 );
			}
		}
	}

	/**
	 * Calculate cart tax totals
	 *
	 * @since 1.0
	 *
	 * @param object $cart
	 */
	public function calculate_totals( $cart ) {
		$cart = is_object( $cart ) ? $cart : WC()->cart;

		/**
		 * If the customer is a guest then the custom_id defaults to zero.
		 */
		if ( WCTAXIFY()->wc_version >= '3.2' ) {
			$customer    = $cart->get_customer();
			$customer_id = $customer->get_id();
		} else {
			$customer_id = WC()->customer->get_id();
		}

		$customer_id = $customer_id > 0 ? $customer_id : 0;

		// customer_id of zero is okay.
		$address             = WCT_TAX()->get_cart_tax_address( $customer_id );
		$this->valid_address = WCT_TAX()->valid_address( $address );
		// If the customer_id is zero, use the unique cart_customer_id instead.
		$customer_id            = ! empty( WCT_TAX()->get_cart_customer_id() ) ? WCT_TAX()->get_cart_customer_id() : $customer_id;
		$this->customer_key     = $customer_id;
		$this->tax_display_cart = $cart->tax_display_cart;
		$tax_total_line_items   = 0;

		if ( ! empty( $cart->cart_contents ) && $this->valid_address ) {
			$data[ 'document_key' ] = $this->document_key;
			$data[ 'is_commited' ]  = false;
			$data[ 'customer_key' ] = $this->customer_key;
			$data[ 'is_exempt' ]    = WC()->session->get( 'taxify_tax_exempt' );

			// Causes Taxify to incorrectly calculate totals
			//$data[ 'discount_amount' ]        = $cart->discount_cart;

			$data[ 'discount_discount_type' ] = 'cart';
			$data                             = array_merge( $data, $address );

			if ( ! empty( $cart->shipping_total ) && WCT_TAX()->is_shipping_taxble( $this->get_shipping_method() ) ) {
				$shipping_cost = $this->get_shipping_cost( $cart );
				if ( ! empty( $shipping_cost ) ) {
					$line_items = array_merge( $this->get_cart_line_items( $cart ), $this->get_shipping_cost( $cart ) );
					//$shipping_total = $cart->shipping_total;
				} else {
					$line_items = $this->get_cart_line_items( $cart );
					//$shipping_total = 0;
				}
			} else {
				$line_items = $this->get_cart_line_items( $cart );
				//$shipping_total = 0;
			}

			$result = WCT_SOAP()->calculate_tax( $data, $line_items );

			if ( is_object( $result ) || ! empty( $result ) ) {
				if ( $result->CalculateTaxResult->ResponseStatus == 'Success' ) {
					$taxify_sales_tax_amount = $result->CalculateTaxResult->SalesTaxAmount;

					$this->shipping_tax   = $this->get_shipping_tax( $result );
					$tax_total_line_items = $this->set_and_get_cart_line_items_tax( $result, $cart );
				}

				if ( $result->CalculateTaxResult->ResponseStatus == 'Failure' ) {
					$response = $result->CalculateTaxResult->Errors->Error->Message;
					WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), esc_attr( $response ) ) );
				}

				if ( ! empty( $taxify_sales_tax_amount ) && ! empty( WCTAXIFY()->taxify_rate_id ) ) {
					if ( ! empty( $this->shipping_tax ) ) {
						if ( WCTAXIFY()->wc_version >= '3.2' ) {
							$cart->set_shipping_taxes( array() );
							$cart->set_shipping_tax( $this->shipping_tax );
							$cart->set_shipping_taxes( array( absint( WCTAXIFY()->taxify_rate_id ) => $this->shipping_tax ) );
							//$cart->set_total_tax( $this->shipping_tax );
							//$cart->set_total( 'shipping_tax_total', $this->shipping_tax );
						} else {
							// Clear the shipping_taxes array in case there are non-Taxify custom tax rates for the US
							$cart->shipping_taxes                                         = array();
							$cart->shipping_taxes[ absint( WCTAXIFY()->taxify_rate_id ) ] = $this->shipping_tax;
							$cart->shipping_tax_total                                     = $this->shipping_tax;
						}
					} else {
						$this->shipping_tax = 0;
					}

					$this->total_tax = ! empty( $this->shipping_tax ) && ! empty( $tax_total_line_items ) ? $tax_total_line_items : $taxify_sales_tax_amount;

					if ( WCTAXIFY()->wc_version >= '3.2' ) {
						$cart->set_cart_contents_tax( $this->total_tax );
						$cart->set_fee_tax( 0 );
						$cart->set_cart_contents_taxes( array( absint( WCTAXIFY()->taxify_rate_id ) => $this->total_tax ) );
						$cart->set_total_tax( $this->total_tax );
					} else {
						//$cart->subtotal  = $tax_total_line_items + $cart->cart_contents_total;
						//$cart->total     = $cart->subtotal + $this->shipping_tax + $shipping_total;
						$cart->tax_total                                     = $this->total_tax;
						$cart->taxes[ absint( WCTAXIFY()->taxify_rate_id ) ] = $this->total_tax;
					}
				}

				// Zero taxes in case there are non-Taxify custom tax rates for the US
				if ( $data[ 'is_exempt' ] ) {
					$this->reset_taxes( $cart );
				}
			} else {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: %s', 'woocommerce-taxify' ), 'The API did not return a response.' ) );
			}
		}
	}

	/**
	 * Adds tax to the cart total.
	 *
	 * @since 1.2.5
	 *
	 * @param float  $total
	 * @param object $cart
	 *
	 * @return mixed
	 */
	public function calculated_total( $total, $cart ) {
		// WC >= 3.2
		$shipping_tax = ! empty( $this->shipping_tax ) ? $this->shipping_tax : 0;
		$total_tax    = ! empty( $this->total_tax ) ? $this->total_tax : 0;
		$total        = $total_tax + $shipping_tax + $cart->get_cart_contents_total() + $cart->get_shipping_total();

		return $total;
	}

	/**
	 * Get lines items and return formatted for the API
	 *
	 * @since 1.0
	 *
	 * @param object $cart
	 *
	 * @return array
	 */
	private function get_cart_line_items( $cart ) {
		$items    = array();
		$contents = $cart->get_cart();

		if ( ! empty( $contents ) ) {
			foreach ( $contents as $key => $item ) {
				$product = $item[ 'data' ];

				$items[] = array(
					'LineNumber'          => $product->get_id(),
					'ItemKey'             => WCT_TAX()->get_sku( $product->get_id() ),
					'ActualExtendedPrice' => wc_format_decimal( $item[ 'line_total' ] ),
					'TaxIncludedInPrice'  => WCT_TAX()->prices_include_tax(),
					'Quantity'            => $item[ 'quantity' ],
					'ItemDescription'     => $product->get_title(),
					'ItemTaxabilityCode'  => WCTAXIFY()->wc_version >= '3.0' ? WCT_TAX()->is_line_item_taxable( $item[ 'data' ]->get_tax_status() ) : WCT_TAX()->is_line_item_taxable( $item[ 'data' ]->tax_status ),
					'ItemCategories'      => 'taxify',
				);
			}
		}

		return $items;
	}

	/**
	 * Get shipping cost as a line item and return formatted for the API
	 *
	 * @since 1.0
	 *
	 * @param object $cart
	 *
	 * @return array
	 */
	private function get_shipping_cost( $cart ) {
		$shipping       = array();
		$shipping_total = $cart->get_shipping_total();

		if ( ! empty( $shipping_total ) ) {
			$shipping[] = array(
				'ItemKey'             => 'shipping_cost',
				'ActualExtendedPrice' => $shipping_total,
				'TaxIncludedInPrice'  => WCT_TAX()->prices_include_tax(),
				'Quantity'            => '1',
				'ItemTaxabilityCode'  => 'Shipping',
			);
		}

		return $shipping;
	}

	/**
	 * Get the chosen shipping method
	 *
	 * @since 1.0
	 *
	 * @return bool|string
	 */
	private function get_shipping_method() {
		$shipping = WC()->session->get( 'chosen_shipping_methods' );

		$obj_length = strlen( $shipping[ 0 ] );
		$last_char  = substr( $shipping[ 0 ], $obj_length - 1, $obj_length );

		// If the last character is a number replace colon with an underscore to complete the name.
		if ( is_numeric( $last_char ) ) {
			$shipping_id = str_replace( ':', '_', $shipping[ 0 ] );
		} else {
			// If the last character is not a number, just use the name before the colon.
			$shipping_method = WCT_TAX()->find_needle( $shipping[ 0 ], ':' );
			$shipping_id     = ! empty( $shipping_method ) ? $shipping_method : $shipping[ 0 ];
		}

		return ! empty( $shipping_id ) ? $shipping_id : false;
	}

	/**
	 * Get shipping tax
	 *
	 * @since 1.0
	 *
	 * @param object $result
	 *
	 * @return string
	 */
	private function get_shipping_tax( $result ) {
		if ( is_object( $result ) && ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail ) ) {
			foreach ( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail as $taxify_items ) {
				if ( ! empty( $taxify_items->ItemKey ) && $taxify_items->ItemKey == 'shipping_cost' ) {
					$shipping_tax = $taxify_items->SalesTaxAmount;
				}
			}
		}

		return ! empty( $shipping_tax ) ? $shipping_tax : '';
	}

	/**
	 * Set lines items then returns total line item tax
	 *
	 * @since 1.0
	 *
	 * @param object $result
	 * @param object $cart
	 *
	 * @return int
	 */
	private function set_and_get_cart_line_items_tax( $result, $cart ) {
		$tax_total = 0;

		if ( is_object( $result ) && ! empty( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail ) ) {
			foreach ( $result->CalculateTaxResult->TaxLineDetails->TaxLineDetail as $taxify_items ) {
				if ( ! empty( $cart->cart_contents ) ) {
					foreach ( $cart->cart_contents as $key => $item ) {
						if ( ! empty( $taxify_items->ItemKey ) && $taxify_items->ItemKey != 'shipping_cost' ) {
							if ( ! empty( $taxify_items->LineNumber ) && $taxify_items->LineNumber == $cart->cart_contents[ $key ][ 'product_id' ] ) {
								$tax_total                                                                                            += $taxify_items->SalesTaxAmount;
								$cart->cart_contents[ $key ][ 'line_tax' ]                                                            = $taxify_items->SalesTaxAmount;
								$cart->cart_contents[ $key ][ 'line_subtotal_tax' ]                                                   = $taxify_items->SalesTaxAmount;
								$cart->cart_contents[ $key ][ 'line_tax_data' ][ 'total' ][ absint( WCTAXIFY()->taxify_rate_id ) ]    = $taxify_items->SalesTaxAmount;
								$cart->cart_contents[ $key ][ 'line_tax_data' ][ 'subtotal' ][ absint( WCTAXIFY()->taxify_rate_id ) ] = $taxify_items->SalesTaxAmount;
							}
						}
					}
				}
			}
		}

		return $tax_total;
	}

	/**
	 * Load checkout JavaScript
	 *
	 * @since 1.0
	 */
	public function load_js() {
		if ( is_checkout() ) {
			$suffix  = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$js_path = WCTAXIFY()->plugin_url() . 'includes/js/frontend/';

			if ( ! wp_script_is( 'taxify-tax-exempt' ) ) {
				wp_enqueue_script( 'taxify-tax-exempt', esc_url( $js_path . 'checkout' . $suffix . '.js' ), array( 'jquery' ), WCTAXIFY()->version, true );
				wp_localize_script( 'taxify-tax-exempt', 'taxify_tax_exempt', array(
					'taxify_tax_exempt_ajax_url'       => WCTAXIFY()->ajax_url(),
					'taxify_tax_exempt_ajax_nonce_sec' => wp_create_nonce( 'apply-taxify-tax-exempt' )
				) );
			}
		}
	}

	/**
	 * AJAX apply tax exempt on checkout page
	 *
	 * @since 1.0
	 */
	public function apply_tax_exempt() {
		check_ajax_referer( 'apply-taxify-tax-exempt', 'security' );
		WC()->session->set( 'taxify_tax_exempt', sanitize_text_field( $_POST[ 'checked' ] ) );
		WC()->cart->calculate_totals();
		woocommerce_cart_totals();

		die();
	}

	/**
	 * Output tax exempt checkbox on the checkout form only for customers from the United States
	 *
	 * @since 1.0
	 *
	 * @param object $checkout
	 */
	public function tax_exempt_checkbox( $checkout ) {
		$country = strtolower( WC()->customer->get_country() );

		if ( ! empty( $country ) && ( $country == 'us' || $country == 'usa' || $country == 'united states' ) ) {
			?>
            <h3><?php _e( 'Tax Exempt Details', 'woocommerce-taxify' ); ?></h3>

            <p class="form-row form-row-wide taxify-tax-exempt">
                <input class="input-checkbox" id="taxify-tax-exempt" <?php checked( ( $checkout->get_value( 'taxifytaxexempt' ) == 'yes' ), 'yes' ) ?>
                       type="checkbox" name="taxifytaxexempt" value="yes"/>
                <label for="taxifytaxexempt" class="checkbox"><?php _e( 'Tax Exempt? (U.S. Only)', 'woocommerce-taxify' ); ?></label>
            </p>
			<?php
		}
	}

	/**
	 * Adds tax exempt checkbox value to order postmeta
	 *
	 * @since 1.0
	 *
	 * @param int   $order_id
	 * @param array $posted
	 */
	public function update_order_meta_tax_exempt_checkbox( $order_id, $posted ) {
		if ( ! empty( $_POST[ 'taxifytaxexempt' ] ) ) {
			WCT_ORDER_DATA_STORE()->update_meta( $order_id, 'taxify_exempt', sanitize_text_field( $_POST[ 'taxifytaxexempt' ] ) );
		}
	}

	/**
	 * Adds hidden fields to store as order postmeta
	 *
	 * @since 1.0
	 *
	 * @param $checkout
	 *
	 * @return mixed $checkout
	 */
	public function hidden_order_postmeta( $checkout ) {
		$this->hidden_input( array(
			                     'id'    => '_taxify_cart_document_key',
			                     'value' => sanitize_text_field( $this->document_key ),
			                     'class' => 'taxify-document-key'
		                     ) );

		$this->hidden_input( array(
			                     'id'    => '_taxify_cart_customer_key',
			                     'value' => sanitize_text_field( $this->customer_key ),
			                     'class' => 'taxify-customer-key'
		                     ) );

		return $checkout;
	}

	/**
	 * Adds tax hidden postmet data to order postmeta
	 *
	 * @since 1.0
	 *
	 * @param int   $order_id
	 * @param array $posted
	 */
	public function update_order_meta_hidden_order_postmeta( $order_id, $posted ) {
		$postmeta = array( '_taxify_cart_document_key' => '_taxify_cart_document_key', '_taxify_cart_customer_key' => '_taxify_cart_customer_key' );

		if ( ! empty( $postmeta ) && is_array( $postmeta ) ) {
			foreach ( $postmeta as $key => $value ) {
				if ( ! empty( $_POST[ $key ] ) ) {
					WCT_ORDER_DATA_STORE()->update_meta( $order_id, $key, sanitize_text_field( $_POST[ $key ] ) );
				}
			}
		}
	}

	/**
	 * Output a hidden input box.
	 *
	 * @since 1.0
	 *
	 * @param array $field
	 */
	public function hidden_input( $field ) {
		global $thepostid, $post;

		$thepostid        = empty( $thepostid ) ? $post->ID : $thepostid;
		$field[ 'value' ] = isset( $field[ 'value' ] ) ? $field[ 'value' ] : WCT_ORDER_DATA_STORE()->get_meta( $thepostid, $field[ 'id' ] );
		$field[ 'class' ] = isset( $field[ 'class' ] ) ? $field[ 'class' ] : '';

		echo '<input type="hidden" class="' . esc_attr( $field[ 'class' ] ) . '" name="' . esc_attr( $field[ 'id' ] ) . '" id="' . esc_attr( $field[ 'id' ] ) . '" value="' . esc_attr( $field[ 'value' ] ) . '" /> ';
	}

	/**
	 * Reset taxes
	 *
	 * @since 1.0
	 *
	 * @param object $cart
	 */
	private function reset_taxes( $cart ) {
		if ( WCTAXIFY()->wc_version >= '3.2' ) {
			$cart->set_shipping_taxes( array() );
			$cart->set_shipping_tax( 0 );
			$cart->set_cart_contents_tax( 0 );
			$cart->set_fee_tax( 0 );
			$cart->set_cart_contents_taxes( array() );
		} else {
			$cart->shipping_taxes     = array();
			$cart->shipping_tax_total = 0;
			$cart->tax_total          = 0;
			$cart->taxes              = array();
		}
	}

	/**
	 * Adds Taxify shipping tax calculation for the order.
	 *
	 * @since 1.0
	 *
	 * @param int $item_id
	 * @param WC_Order_Item_Tax object    $item
	 * @param int $order_id
	 */
	public function add_shipping_taxes( $item_id, $item, $order_id ) {
		if ( ! empty( $this->shipping_tax && ! empty( $item_id ) ) ) {
			$taxes[ WCTAXIFY()->taxify_rate_id ] = wc_format_decimal( $this->shipping_tax );

			if ( WCTAXIFY()->wc_version >= '3.0' ) {
				//$shipping_item_id = WCT_ORDER()->get_item_id( $order_id, 'shipping' );

				wc_delete_order_item_meta( $item_id, 'taxes' );
				wc_update_order_item_meta( $item_id, 'taxes', array( 'total' => $taxes ) );
				wc_update_order_item_meta( $item_id, 'total_tax', wc_format_decimal( $this->shipping_tax ) );
			}
		}
	}

	/**
	 * Gets the tax display status. Either excl or incl.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	//private function tax_display_cart() {
	//	return $this->tax_display_cart == 'excl' ? false : true;
	//}

} // End class