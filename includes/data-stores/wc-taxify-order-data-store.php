<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Order Data Store Class
 *
 * @package     Taxify/Order Data Store
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.2.8
 */
class WC_Taxify_Order_Data_Store {

	/**
	 * This is false until the order object is instantiated.
	 *
	 * @since 1.2.8
	 * @var bool
	 */
	private $order_object = false;

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Order_Data_Store
	 */
	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Return the order object.
	 *
	 * @since 1.2.8
	 *
	 * @param int|mixed $order WC_Order or order ID.
	 *
	 * @return bool|\WC_Order
	 */
	public function get_order_object( $order ) {
		return $this->order_object = is_object( $order ) ? $order : wc_get_order( $order );
	}

	/**
	 * Get order metadata by key. If one key passed, and two or more identical keys exist,
	 * all values for those identical keys will be returned.
	 *
	 * @usage WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_api_new_version' );
	 *
	 * @since 1.2.8
	 *
	 * @param  int|mixed $order WC_Order or order ID.
	 * @param  string    $meta_key
	 * @param  bool      $single
	 *
	 * @return bool|mixed A single value is returned.
	 */
	public function get_meta( $order, $meta_key = '', $single = true ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				return get_post_meta( $order->get_id(), $meta_key, $single );
			}

			if ( $single ) {
				/**
				 * @usage returns a single value for a single key. A single value for the single order.
				 * echo WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_api_new_version' );
				 */
				return $order->get_meta( $meta_key, $single );
			} else {
				/**
				 * @usage returns multiple values if there are multiple keys. One value for each order.
				 * $o = WCT_ORDER_DATA_STORE()->get_meta( $order_id, '_api_new_version', false );
				 * echo $o['_api_new_version'];
				 */
				return WCT_FORMAT()->flatten_meta_object( $order->get_meta( $meta_key, $single ) );
			}
		}

		return false;
	}

	/**
	 * Get all order metadata.
	 *
	 * @since 1.2.8
	 *
	 * @param  int|mixed $order WC_Order or order ID.
	 *
	 * @return array|bool|mixed If data exists an array is returned.
	 */
	public function get_meta_data( $order ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				return WC_AM_ARRAY()->flatten_array( get_post_meta( $order->get_id(), '', false ) );
			}

			return array_merge( array(
				                    'id'     => $order->get_id(),
				                    'number' => $order->get_order_number(),
			                    ), $order->get_data(), array(
				                    'meta_data' => WCT_FORMAT()->flatten_meta_object( $order->get_meta_data() ),
			                    ) );
		}

		return false;
	}

	/**
	 * Get all order data, including metadata.
	 *
	 * @param int|mixed $order WC_Order or order ID.
	 *
	 * @return array|bool
	 */
	public function get_order_data( $order ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			return array_merge( array(
				                    'id' => $order->get_id(),
			                    ), $order->get_data(), array(
				                    'number'         => $order->get_order_number(),
				                    'meta_data'      => WCT_FORMAT()->flatten_meta_object( $order->get_meta_data() ),
				                    'line_items'     => $order->get_items( 'line_item' ),
				                    'tax_lines'      => $order->get_items( 'tax' ),
				                    'shipping_lines' => $order->get_items( 'shipping' ),
				                    'fee_lines'      => $order->get_items( 'fee' ),
				                    'coupon_lines'   => $order->get_items( 'coupon' ),
			                    ) );
		}

		return false;
	}

	/**
	 * Update order metadata.
	 *
	 * @since 1.2.8
	 *
	 * @param  int|mixed $order WC_Order or order ID.
	 * @param  string    $meta_key
	 * @param  mixed     $meta_value
	 */
	public function update_meta( $order, $meta_key, $meta_value ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				update_post_meta( $order->get_id(), $meta_key, $meta_value );
			} else {
				$this->delete_meta( $order, $meta_key );
				$order->update_meta_data( $meta_key, $meta_value );
				$order->save_meta_data();
			}
		}
	}

	/**
	 * Delete order metadata.
	 *
	 * @since 1.2.8
	 *
	 * @param  int|WC_Product $order
	 * @param  string         $meta_key
	 */
	public function delete_meta( $order, $meta_key ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				delete_post_meta( $order->get_id(), $meta_key );
			} else {
				delete_post_meta( $order->get_id(), $meta_key );
				$order->delete_meta_data( $meta_key );
				$order->save_meta_data();
			}
		}
	}

	/**
	 * Return the customer/user ID.
	 *
	 * @since 1.2.8
	 *
	 * @param int|mixed $order WC_Order or order ID.
	 *
	 * @return bool|int|mixed
	 */
	public function get_customer_id( $order ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			return WCTAXIFY()->wc_version < '3.0' ? $order->get_user_id() : $order->get_customer_id();
		}

		return false;
	}

	/**
	 * Return the order key.
	 *
	 * @since 1.2.8
	 *
	 * @param int|mixed $order WC_Order or order ID.
	 *
	 * @return bool|mixed|string
	 */
	public function get_order_key( $order ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			if ( WCTAXIFY()->wc_version < '3.0' ) {
				return ! empty( $order->order_key ) ? $order->order_key : '';
			}

			return $order->get_order_key();
		}

		return false;
	}

	/**
	 * Return the order number/order ID.
	 *
	 * @since 1.2.8
	 *
	 * @param int|mixed $order WC_Order or order ID.
	 *
	 * @return bool|string
	 */
	public function get_order_number( $order ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			// Pre 3.0 $order->id or $order->get_order_number()
			return $order->get_order_number();
		}

		return false;
	}

	/**
	 * Return the refunded quantity for an order item.
	 *
	 * @since 1.2.8
	 *
	 * @param int|mixed $order WC_Order or order ID.
	 * @param int       $item_id
	 *
	 * @return int
	 */
	public function get_qty_refunded_for_item( $order, $item_id ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		if ( $order ) {
			return $order->get_qty_refunded_for_item( $item_id );
		}

		return 0;
	}

	/**
	 * Return true if the order status is completed.
	 *
	 * @since 1.2.8
	 *
	 * @param int|mixed $order WC_Order or order ID.
	 *
	 * @return bool
	 */
	public function has_status_completed( $order ) {
		$order = is_object( $this->order_object ) ? $this->order_object : $this->get_order_object( $order );

		return $order->has_status( 'completed' ) ? true : false;
	}
}