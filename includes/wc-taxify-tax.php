<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Tax Class
 *
 * @package     Taxify/includes/Tax
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 */
class WC_Taxify_Tax {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Tax
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		if ( WCTAXIFY()->taxify_enabled() ) {
			add_filter( 'woocommerce_rate_label', array( $this, 'get_rate_label' ), 10, 2 );
			add_filter( 'woocommerce_rate_code', array( $this, 'get_rate_code' ), 10, 2 );
			add_filter( 'woocommerce_find_rates', array( $this, 'find_rates' ), 10, 2 );
		}
	}

	/**
	 * Return a given rates label.
	 *
	 * @since 1.0
	 *
	 * @param $rate_name
	 * @param $key
	 *
	 * @return mixed
	 */
	public function get_rate_label( $rate_name, $key ) {
		if ( is_numeric( $key ) && $key == WCTAXIFY()->taxify_rate_id ) {
			return apply_filters( 'wc_taxify_rate_label', __( 'Tax', 'woocommerce-taxify' ), $rate_name, $key );
		}

		return $rate_name;
	}

	/**
	 * Get a rates code. Code is made up of COUNTRY-STATE-NAME-Priority. E.g GB-VAT-1, US-AL-TAX-1
	 *
	 * @since 1.0
	 *
	 * @param $code_string
	 * @param $key
	 *
	 * @return mixed
	 */
	public function get_rate_code( $code_string, $key ) {
		if ( is_numeric( $key ) && $key == WCTAXIFY()->taxify_rate_id ) {
			return apply_filters( 'wc_taxify_rate_code', __( 'US-TAX-1', 'woocommerce-taxify' ), $code_string, $key );
		}

		return $code_string;
	}

	/**
	 * Return address used for tax calculation.
	 *
	 * @since 1.0
	 *
	 * @param int $customer_id
	 *
	 * @return array
	 */
	public function get_cart_tax_address( $customer_id = 0 ) {
		$tax_based_on = WCTAXIFY()->tax_based_on();
		//$postcode     = get_option( 'wc_taxify_store_postcode' );

		$countries = new WC_Countries();
		//$base_postcode = $countries->get_base_postcode();

		if ( ! empty( $customer_id ) && WCTAXIFY()->wc_version >= '3.0' ) {
			$customer = new WC_Customer( $customer_id );

			if ( ! empty( $tax_based_on ) ) {
				if ( $tax_based_on == 'billing' ) {
					$address = array(
						'destination_address_1' => $customer->get_billing_address_1(),
						'destination_address_2' => $customer->get_billing_address_2(),
						'destination_country'   => $customer->get_billing_country(),
						'destination_state'     => $customer->get_billing_state(),
						'destination_city'      => $customer->get_billing_city(),
						'destination_zip'       => $customer->get_billing_postcode(),
					);
				} elseif ( $tax_based_on == 'shipping' ) {
					$address = array(
						'destination_address_1' => $customer->get_shipping_address_1(),
						'destination_address_2' => $customer->get_shipping_address_2(),
						'destination_country'   => $customer->get_shipping_country(),
						'destination_state'     => $customer->get_shipping_state(),
						'destination_city'      => $customer->get_shipping_city(),
						'destination_zip'       => $customer->get_shipping_postcode(),
					);
				} elseif ( $tax_based_on == 'base' ) {
					$address = array(
						'destination_country' => $countries->get_base_country(),
						'destination_state'   => $countries->get_base_state(),
						'destination_zip'     => $countries->get_base_postcode(),
						//'destination_zip'     => empty( $base_postcode ) ? $postcode : $base_postcode,
					);
				}
			}
		} else {
			if ( ! empty( $tax_based_on ) ) {
				if ( $tax_based_on == 'billing' ) {
					$address = array(
						'destination_address_1' => WC()->customer->get_billing_address_1(),
						'destination_address_2' => WC()->customer->get_billing_address_2(),
						'destination_country'   => WC()->customer->get_billing_country(),
						'destination_state'     => WC()->customer->get_billing_state(),
						'destination_city'      => WC()->customer->get_billing_city(),
						'destination_zip'       => WC()->customer->get_billing_postcode(),
					);
				} elseif ( $tax_based_on == 'shipping' ) {
					$address = array(
						'destination_address_1' => WC()->customer->get_shipping_address_1(),
						'destination_address_2' => WC()->customer->get_shipping_address_2(),
						'destination_country'   => WC()->customer->get_shipping_country(),
						'destination_state'     => WC()->customer->get_shipping_state(),
						'destination_city'      => WC()->customer->get_shipping_city(),
						'destination_zip'       => WC()->customer->get_shipping_postcode(),
					);
				} elseif ( $tax_based_on == 'base' ) {
					$address = array(
						'destination_country' => $countries->get_base_country(),
						'destination_state'   => $countries->get_base_state(),
						'destination_zip'     => $countries->get_base_postcode(),
						//'destination_zip'     => empty( $base_postcode ) ? $postcode : $base_postcode,
					);
				}
			}
		}

		//if ( empty( $address[ 'destination_zip' ] ) && WCTAXIFY()->tax_based_on() == 'base' ) {
		//	$address = $this->base_store_address();
		//}

		return ! empty( $address ) ? $address : array();
	}

	/**
	 * Get the store's origin tax address.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function base_store_address() {
		$countries = new WC_Countries();
		$company   = get_option( 'wc_taxify_business_name' );
		$address_1 = get_option( 'wc_taxify_business_address_1' );
		$city      = get_option( 'wc_taxify_business_city' );
		$state     = get_option( 'wc_taxify_business_state' );
		$zip       = get_option( 'wc_taxify_store_postcode' );

		return array(
			'destination_first_name' => ! empty( $company ) ? $company : '',
			'destination_address_1'  => ! empty( $address_1 ) ? $address_1 : '',
			'destination_city'       => ! empty( $city ) ? $city : '',
			'destination_state'      => ! empty( $state ) ? $state : '',
			'destination_zip'        => ! empty( $zip ) ? $zip : '',
			'destination_country'    => $countries->get_base_country(),
		);
	}

	/**
	 * Get the store's origin tax address.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_store_tax_address() {
		$countries = new WC_Countries();
		$company   = get_option( 'wc_taxify_business_name' );
		$address_1 = get_option( 'wc_taxify_business_address_1' );
		$city      = get_option( 'wc_taxify_business_city' );
		$state     = get_option( 'wc_taxify_business_state' );
		$zip       = get_option( 'wc_taxify_store_postcode' );

		return array(
			'origin_company'   => ! empty( $company ) ? $company : '',
			'origin_address_1' => ! empty( $address_1 ) ? $address_1 : '',
			'origin_city'      => ! empty( $city ) ? $city : '',
			'origin_state'     => ! empty( $state ) ? $state : '',
			'origin_zip'       => ! empty( $zip ) ? $zip : '',
			'origin_country'   => $countries->get_base_country(),
		);
	}

	/**
	 * Verify the required address fields are set, and the Country is the United States.
	 *
	 * @since 1.0
	 *
	 * @param array $address
	 *
	 * @return bool
	 */
	public function valid_address( $address ) {
		if ( is_array( $address ) ) {
			$address = array_change_key_case( array_map( 'strtolower', $address ) );

			if ( ! empty( $address[ 'destination_country' ] ) && ! empty( $address[ 'destination_state' ] ) && ! empty( $address[ 'destination_zip' ] ) && ( $address[ 'destination_country' ] == 'us' || $address[ 'destination_country' ] == 'usa' || $address[ 'destination_country' ] == 'united states' ) ) {
				return true;
			}

			//if ( empty( $address[ 'destination_country' ] ) && ! empty( $address[ 'destination_state' ] ) && ! empty( $address[ 'destination_zip' ] ) ) {
			//	return true;
			//}
		}

		return false;
	}

	/**
	 * Get the product sku or return the post slug as the sku if the product sku is empty.
	 *
	 * @since 1.0
	 *
	 * @param int $product_id
	 *
	 * @return bool|mixed|string
	 */
	public function get_sku( $product_id ) {
		if ( is_numeric( $product_id ) && ! empty( $product_id ) ) {
			if ( WCTAXIFY()->wc_version >= '3.0' ) {
				$product = WCT_PRODUCT_DATA_STORE()->get_product_object( $product_id );
				$sku     = $product->get_sku();
			} else {
				$sku = get_post_meta( $product_id, '_sku', true );
			}

			if ( ! empty( $sku ) ) {
				return $sku;
			} else {
				$post = get_post( $product_id );
				if ( ! empty( $post ) ) {
					return strtolower( $post->post_name );
				}
			}
		}

		return false;
	}

	/**
	 * Get customer ID if logged in, or the customer session ID.
	 *
	 * @since 1.0
	 *
	 * @return int
	 */
	public function get_cart_customer_id() {
		$user_id = get_current_user_id();

		return is_user_logged_in() && ! empty( $user_id ) ? $user_id : WC()->session->get_customer_id();
	}

	/**
	 * Get the customer_user or taxify_cart_document_key from postmeta order to use as the document_key.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return mixed
	 */
	public function get_postmeta_customer_id( $order_id ) {
		$customer_user            = WCT_ORDER_DATA_STORE()->get_customer_id( $order_id );
		$taxify_cart_document_key = WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_taxify_cart_document_key' );

		return ! empty( $customer_user ) ? $customer_user : $taxify_cart_document_key;
	}

	/**
	 * Get all states from the United States of America.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_states() {
		return array_unique( apply_filters( 'wc_taxify_states', array(
			'AL' => __( 'Alabama', 'woocommerce-taxify' ),
			'AK' => __( 'Alaska', 'woocommerce-taxify' ),
			'AZ' => __( 'Arizona', 'woocommerce-taxify' ),
			'AR' => __( 'Arkansas', 'woocommerce-taxify' ),
			'CA' => __( 'California', 'woocommerce-taxify' ),
			'CO' => __( 'Colorado', 'woocommerce-taxify' ),
			'CT' => __( 'Connecticut', 'woocommerce-taxify' ),
			'DE' => __( 'Delaware', 'woocommerce-taxify' ),
			'DC' => __( 'District Of Columbia', 'woocommerce-taxify' ),
			'FL' => __( 'Florida', 'woocommerce-taxify' ),
			'GA' => __( 'Georgia', 'woocommerce-taxify' ),
			'HI' => __( 'Hawaii', 'woocommerce-taxify' ),
			'ID' => __( 'Idaho', 'woocommerce-taxify' ),
			'IL' => __( 'Illinois', 'woocommerce-taxify' ),
			'IN' => __( 'Indiana', 'woocommerce-taxify' ),
			'IA' => __( 'Iowa', 'woocommerce-taxify' ),
			'KS' => __( 'Kansas', 'woocommerce-taxify' ),
			'KY' => __( 'Kentucky', 'woocommerce-taxify' ),
			'LA' => __( 'Louisiana', 'woocommerce-taxify' ),
			'ME' => __( 'Maine', 'woocommerce-taxify' ),
			'MD' => __( 'Maryland', 'woocommerce-taxify' ),
			'MA' => __( 'Massachusetts', 'woocommerce-taxify' ),
			'MI' => __( 'Michigan', 'woocommerce-taxify' ),
			'MN' => __( 'Minnesota', 'woocommerce-taxify' ),
			'MS' => __( 'Mississippi', 'woocommerce-taxify' ),
			'MO' => __( 'Missouri', 'woocommerce-taxify' ),
			'MT' => __( 'Montana', 'woocommerce-taxify' ),
			'NE' => __( 'Nebraska', 'woocommerce-taxify' ),
			'NV' => __( 'Nevada', 'woocommerce-taxify' ),
			'NH' => __( 'New Hampshire', 'woocommerce-taxify' ),
			'NJ' => __( 'New Jersey', 'woocommerce-taxify' ),
			'NM' => __( 'New Mexico', 'woocommerce-taxify' ),
			'NY' => __( 'New York', 'woocommerce-taxify' ),
			'NC' => __( 'North Carolina', 'woocommerce-taxify' ),
			'ND' => __( 'North Dakota', 'woocommerce-taxify' ),
			'OH' => __( 'Ohio', 'woocommerce-taxify' ),
			'OK' => __( 'Oklahoma', 'woocommerce-taxify' ),
			'OR' => __( 'Oregon', 'woocommerce-taxify' ),
			'PA' => __( 'Pennsylvania', 'woocommerce-taxify' ),
			'RI' => __( 'Rhode Island', 'woocommerce-taxify' ),
			'SC' => __( 'South Carolina', 'woocommerce-taxify' ),
			'SD' => __( 'South Dakota', 'woocommerce-taxify' ),
			'TN' => __( 'Tennessee', 'woocommerce-taxify' ),
			'TX' => __( 'Texas', 'woocommerce-taxify' ),
			'UT' => __( 'Utah', 'woocommerce-taxify' ),
			'VT' => __( 'Vermont', 'woocommerce-taxify' ),
			'VA' => __( 'Virginia', 'woocommerce-taxify' ),
			'WA' => __( 'Washington', 'woocommerce-taxify' ),
			'WV' => __( 'West Virginia', 'woocommerce-taxify' ),
			'WI' => __( 'Wisconsin', 'woocommerce-taxify' ),
			'WY' => __( 'Wyoming', 'woocommerce-taxify' ),
		) ) );
	}

	/**
	 * Return taxability of the shipping method.
	 *
	 * @since 1.0
	 *
	 * @param string $method
	 *
	 * @return bool
	 */
	public function is_shipping_taxble( $method ) {
		$taxable  = false;
		$settings = get_option( 'woocommerce_' . $method . '_settings' );

		//if ( ! empty( $settings[ 'tax_status' ] ) && $settings[ 'enabled' ] == 'yes' ) {
		if ( ! empty( $settings[ 'tax_status' ] ) ) {
			$taxable = $settings[ 'tax_status' ] == 'taxable' ? true : false;
		} elseif ( ! empty( $settings[ 'tax_status' ] ) && $settings[ 'tax_status' ] == 'taxable' ) {
			if ( ! empty( WC()->cart ) ) {
				foreach ( WC()->cart->get_cart() as $item ) {
					//if ( $item['data'] && ( $item['data']->is_taxable() || $item['data']->is_shipping_taxable() ) ) {
					if ( $item[ 'data' ] && $item[ 'data' ]->is_shipping_taxable() ) {
						$taxable = true;
					}
				}
			}
		}

		return $taxable;
	}

	/**
	 * Gets the tax status of a product, or the Taxify Tax Class.
	 *
	 * @since 1.0
	 *
	 * @param int $product_id
	 *
	 * @return bool|mixed|string
	 */
	public function get_product_tax_status( $product_id ) {
		$product = WCT_PRODUCT_DATA_STORE()->get_product_object( $product_id );

		if ( $product ) {
			$parent_id    = WCT_PRODUCT_DATA_STORE()->get_parent_product_id( $product );
			$variation_id = WCT_PRODUCT_DATA_STORE()->get_product_id( $product );

			// If this is a variation, return the Taxify Tax Class if not empty.
			if ( ! empty( $variation_id ) ) {
				$taxify_tax_class_variation = WCT_PRODUCT_DATA_STORE()->get_meta( $variation_id, '_taxify_tax_class' );

				if ( ! empty( $taxify_tax_class_variation ) ) {
					return $taxify_tax_class_variation;
				}
			}

			// If this is Variable product that is a parent of a variation, return the Taxify Tax Class if not empty.
			if ( ! empty( $parent_id ) ) {
				$taxify_tax_class_parent = WCT_PRODUCT_DATA_STORE()->get_meta( $parent_id, '_taxify_tax_class' );

				if ( ! empty( $taxify_tax_class_parent ) ) {
					return $taxify_tax_class_parent;
				}
			}

			// If this is a Simple product, return the Taxify Tax Class if not empty.
			if ( get_post_type( $product_id ) == 'product' ) {
				$taxify_tax_class = WCT_PRODUCT_DATA_STORE()->get_meta( $product_id, '_taxify_tax_class' );

				if ( ! empty( $taxify_tax_class ) ) {
					return $taxify_tax_class;
				}
			}

			// If the Simple and Variable product Taxify Tax Classes are empty, return the Tax Status.
			$item_tax_status = $product->get_tax_status();
		} else {
			return false;
		}

		return ! empty( $item_tax_status ) ? $this->is_line_item_taxable( $item_tax_status ) : '';
	}

	/**
	 * Is the product tax status set to shipping only?
	 *
	 * @since 1.0
	 *
	 * @param int $product_id
	 *
	 * @return bool
	 */
	public function is_product_tax_status_shipping( $product_id ) {
		if ( get_post_type( $product_id ) == 'product' ) {
			$product = WCT_PRODUCT_DATA_STORE()->get_product_object( $product_id );

			if ( $product ) {
				$item_tax_status = $product->get_tax_status();
			} else {
				return false;
			}
		}

		return ! empty( $item_tax_status ) && $item_tax_status == 'shipping' ? true : false;
	}

	/**
	 * Is line item taxable?
	 *
	 * @since 1.0
	 *
	 * @param string $tax_status
	 *
	 * @return string
	 */
	public function is_line_item_taxable( $tax_status ) {
		/**
		 * If $tax_status == 'shipping' Taxify will view this as not taxable for the product itself
		 * even though WooCommerce sees the product shipping only as taxable.
		 * Shipping tax is actually controlled by the Tax settings, so this parsing of taxability in
		 * WooCommerce doesn't make sense.
		 */
		if ( $this->prices_include_tax() || $tax_status == 'none' ) {
			return 'none';
		} elseif ( WCTAXIFY()->wc_tax_enabled() && empty( $tax_status ) || $tax_status == 'taxable' ) {
			return 'taxable';
		} elseif ( $tax_status == 'shipping' ) {
			return 'Shipping';
		}

		return $tax_status;
	}

	/**
	 * Return Taxify custom tax rate if tax region, shipping, or billing location, is in the US.
	 *
	 * @since 1.0
	 *
	 * @param array $matched_tax_rates
	 * @param array $args
	 *
	 * @return array
	 */
	public function find_rates( $matched_tax_rates, $args ) {
		if ( ! empty( $matched_tax_rates ) && is_array( $matched_tax_rates ) && ! empty( $args ) && ( $args[ 'country' ] == 'US' || $args[ 'country' ] == 'us' ) ) {
			foreach ( $matched_tax_rates as $rate_id => $rate ) {
				if ( WCTAXIFY()->taxify_rate_id != $rate_id ) {
					return array();
				}
			}
		}

		return $matched_tax_rates;
	}

	/**
	 * Are prices inclusive of tax?
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function prices_include_tax() {
		return WCTAXIFY()->wc_tax_enabled() && get_option( 'woocommerce_prices_include_tax' ) === 'yes';
	}

	/**
	 * Returns true if the order has been filed with Taxify.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return bool
	 */
	public function is_commited_to_taxify( $order_id ) {
		$is_committed = WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_taxify_is_commited' );

		return ! empty( $is_committed ) && $is_committed == 'yes' ? true : false;
	}

	/**
	 * Gets tax exempt status for the order.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function tax_exempt_status( $order_id ) {
		$taxify_exempt = WCT_ORDER_DATA_STORE()->get_meta( $order_id, 'taxify_exempt' );

		return ! empty( $taxify_exempt ) && $taxify_exempt == 'yes' ? 'exempt' : '';
	}

	/**
	 * Returns true if order is tax exempt, else false.
	 *
	 * @since 1.0
	 *
	 * @param int $order_id
	 *
	 * @return string
	 */
	public function is_order_tax_exempt( $order_id ) {
		$taxify_exempt = WCT_ORDER_DATA_STORE()->get_meta( $order_id, 'taxify_exempt' );

		return ! empty( $taxify_exempt ) && $taxify_exempt == 'yes' ? true : false;
	}

	/**
	 * Gets the status of an order.
	 *
	 * @since  1.0
	 *
	 * @param  int $order_id
	 *
	 * @return false|string
	 */
	public function get_order_status( $order_id ) {
		if ( ! empty( $order_id ) ) {
			if ( WCTAXIFY()->wc_version >= 2.2 ) {
				return get_post_status( $order_id );
			} else {
				// < WC 2.2
				$order_status = wp_get_post_terms( $order_id, 'shop_order_status' );

				return $order_status[ 0 ]->slug;
			}
		}

		return false;
	}

	/**
	 * Checks if an order has been completed.
	 *
	 * @since  1.0
	 *
	 * @param  int $order_id
	 *
	 * @return bool
	 */
	public function is_order_status_completed( $order_id ) {
		$order_status = $this->get_order_status( $order_id );

		if ( is_wp_error( $order_status ) || empty( $order_status ) ) {
			// < WC 2.2
			return false;
		} else if ( $order_status == 'wc-completed' ) {
			// >= WC 2.2
			return true;
		} else if ( $order_status == 'completed' ) {
			// < WC 2.2
			return true;
		}

		return false;
	}

	/**
	 * Checks if an order has been refunded.
	 *
	 * @since  1.0
	 *
	 * @param  int $order_id
	 *
	 * @return bool
	 */
	public function is_order_status_refunded( $order_id ) {
		$order_status = $this->get_order_status( $order_id );

		if ( is_wp_error( $order_status ) || empty( $order_status ) ) {
			// < WC 2.2
			return false;
		} else if ( $order_status == 'wc-refunded' ) {
			// >= WC 2.2
			return true;
		} else if ( $order_status == 'refunded' ) {
			// < WC 2.2
			return true;
		}

		return false;
	}

	/**
	 * Get the status of the tax exempt checkbox.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function is_tax_exempt_checkbox_enabled_for_checkout() {
		return get_option( 'wc_taxify_exempt_checkout_checkbox' ) === 'yes';
	}

	/**
	 * Get custom store Taxify Tax Classes from the Options table, formatted for product display.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_taxify_tax_classes() {
		$classes     = get_option( 'wc_taxify_tax_classes' );
		$tax_classes = ! empty( $classes ) ? $classes : $this->get_taxify_tax_classes_from_api();

		return array_filter( array_map( 'trim', explode( "\n", $tax_classes ) ) );
	}

	/**
	 * Get Taxify Tax Classes from API and store them in the Options table.
	 *
	 * @since 1.0
	 *
	 * @return bool|string
	 */
	public function get_taxify_tax_classes_from_api() {
		$result = WCT_SOAP()->get_codes();

		if ( is_object( $result ) && ! empty( $result ) ) {
			if ( $result->GetCodesResult->ResponseStatus == 'Success' ) {
				if ( ! empty( $result->GetCodesResult->Codes->string ) ) {
					$tax_classes = implode( "\n", $result->GetCodesResult->Codes->string );

					if ( ! empty( $tax_classes ) ) {
						update_option( 'wc_taxify_tax_classes', $tax_classes );
					}

					return ! empty( $tax_classes ) ? $tax_classes : false;
				}
			}
		}

		return false;
	}

	/**
	 * Returns a prefix or suffix/postfix.
	 *
	 * @since 1.1
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param int    $position_after_needle
	 *
	 * @return bool|string
	 */
	public function find_needle( $haystack, $needle, $position_after_needle = 0 ) {
		$pos    = stripos( $haystack, $needle ) + $position_after_needle;
		$result = trim( substr( $haystack, 0, $pos ) );

		return ! empty( $result ) ? $result : false;
	}

	/**
	 * Verifies a product exists.
	 *
	 * @since 1.1.3
	 *
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function product_exists( $post_id ) {
		$post = get_post( $post_id );

		return ! empty( $post ) ? true : false;
	}

	/**
	 * Verifies an order exists.
	 *
	 * @since 1.1.3
	 *
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function order_exists( $post_id ) {
		$post = get_post( $post_id );

		return ! empty( $post ) ? true : false;
	}

	/**
	 * Check if post exists and is not in the trash.
	 *
	 * @since 1.1.3
	 *
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function product_is_active( $post_id ) {
		$post_status = get_post_status( $post_id );

		return ! empty( $post_status ) && $post_status != 'trash' ? true : false;
	}

} // End class