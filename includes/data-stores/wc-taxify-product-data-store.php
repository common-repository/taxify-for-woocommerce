<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Product Data Store Class
 *
 * @package     Taxify/Product Data Store
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.2.8
 */
class WC_Taxify_Product_Data_Store {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Product_Data_Store
	 */
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Return the product object.
	 *
	 * @since 1.2.8
	 *
	 * @param int|mixed $product WC_Product or order ID.
	 *
	 * @return false|null|\WC_Product
	 */
	public function get_product_object( $product ) {
		return is_object( $product ) ? $product : wc_get_product( $product );
	}

	/**
	 * Get product metadata.
	 *
	 * @since 1.2.8
	 *
	 * @param  int|WC_Product $product
	 * @param  string         $meta_key
	 * @param  bool           $single
	 *
	 * @return bool|mixed
	 */
	public function get_meta( $product, $meta_key = '', $single = true ) {
		$product = $this->get_product_object( $product );

		if ( $product ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				return get_post_meta( $product->get_id(), $meta_key, $single );
			}

			if ( $single ) {
				/**
				 * @usage returns a single value for a single key. A single value for the single order.
				 * echo WC_AM_ORDER_DATA_STORE()->get_meta( $order_id, '_api_new_version' );
				 */
				return $product->get_meta( $meta_key, $single );
			} else {
				/**
				 * @usage returns multiple values if there are multiple keys. One value for each order.
				 * $o = WC_AM_ORDER_DATA_STORE()->get_meta( $order_id, '_api_new_version', false );
				 * echo $o['_api_new_version'];
				 */
				return WCT_FORMAT()->flatten_meta_object( $product->get_meta( $meta_key, $single ) );
			}
		}

		return false;
	}

	/**
	 * Get all product meta data.
	 *
	 * @since 1.2.8
	 *
	 * @param int|WC_Product $product
	 *
	 * @return array|bool
	 */
	public function get_meta_data( $product ) {
		$product = $this->get_product_object( $product );

		if ( $product ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				return WC_AM_ARRAY()->get_meta_query_flattened( 'postmeta', $product->get_id() );
			}

			return $product->get_meta_data();
		}

		return false;
	}

	/**
	 * Return array of flattened metadata.
	 *
	 * @since 1.2.8
	 *
	 * @param int|WC_Product $product
	 *
	 * @return array
	 */
	public function get_meta_flattened( $product ) {
		return WCT_FORMAT()->flatten_meta_object( $this->get_meta_data( $product ) );
	}

	/**
	 * Returns product type, i.e. simple, variable, etc.
	 *
	 * @since 1.2.8
	 *
	 * @param int|WC_Product $product
	 *
	 * @return string|bool
	 */
	public function get_type( $product ) {
		$product = $this->get_product_object( $product );

		return $product ? $product->get_type() : false;
	}

	/**
	 * Update product metadata.
	 *
	 * @since 1.2.8
	 *
	 * @param  int|WC_Product $product
	 * @param  string         $meta_key
	 * @param  mixed          $meta_value
	 */
	public function update_meta( $product, $meta_key, $meta_value ) {
		$product = $this->get_product_object( $product );

		if ( $product ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				update_post_meta( $product->get_id(), $meta_key, $meta_value );
			} else {
				$product->update_meta_data( $meta_key, $meta_value );
				$product->save_meta_data();
			}
		}
	}

	/**
	 * Delete product metadata.
	 *
	 * @since 1.2.8
	 *
	 * @param  int|WC_Product $product
	 * @param  string         $meta_key
	 */
	public function delete_meta( $product, $meta_key ) {
		$product = $this->get_product_object( $product );

		if ( $product ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				delete_post_meta( $product->get_id(), $meta_key );
			} else {
				$product->delete_meta_data( $meta_key );
			}
		}
	}

	/**
	 * Returns a list of product objects.
	 *
	 * @since 1.2.8
	 *
	 * @param array $args
	 *
	 * @return array|\stdClass
	 */
	public function get_products( $args = array() ) {
		if ( WCTAXIFY()->wc_version < '3.0' ) {
			return get_posts( $args );
		} else {
			return wc_get_products( $args );
		}
	}

	/**
	 * Return parent  product ID.
	 *
	 * @since 1.2.8
	 *
	 * @param int|WC_Product $product
	 *
	 * @return bool|int
	 */
	public function get_parent_product_id( $product ) {
		$product = $this->get_product_object( $product );

		if ( $product ) {
			if ( is_callable( array( $product, 'get_parent_id', 'is_type' ) ) ) {
				return $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			} elseif ( is_callable( array( $product, 'get_product_id' ) ) ) {
				return ! empty( $product->get_product_id() ) ? $product->get_product_id() : $product->get_id();
			} else {
				return $product->get_id();
			}
		}

		return false;
	}

	/**
	 * Return parent or variable product ID.
	 *
	 * @since 1.2.8
	 *
	 * @param int|WC_Product $product
	 *
	 * @return bool|int
	 */
	public function get_product_id( $product ) {
		$product = $this->get_product_object( $product );

		if ( $product ) {
			// WC >= 3.0
			if ( is_callable( array( $product, 'get_product_id' ) ) ) {
				$product_id = ! empty( $product->get_product_id() ) ? $product->get_product_id() : $product->get_id();
			} else {
				$product_id = $product->get_id();
			}

			return is_callable( array(
				                    $product,
				                    'get_variation_id'
			                    ) ) && ! empty( $product->get_variation_id() ) ? $product->get_variation_id() : $product_id;
		}

		return false;
	}

}