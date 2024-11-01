<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Taxify Scheduler Class
 *
 * @package     Taxify/includes/Scheduler
 * @author      Todd Lahman LLC
 * @copyright   Copyright (c) Taxify
 * @since       1.0
 *
 */
class WC_Taxify_Scheduler {

	/**
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * @static
	 * @return null|\WC_Taxify_Scheduler
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public $scheduler_hooks = array(
		'wc_taxify_scheduled_bulk_orders'  => 'file_orders',
		'wc_taxify_scheduled_missed_order' => 'file_orders',
	);

	public function __construct() {
		if ( WCTAXIFY()->taxify_enabled() ) {
			add_action( 'wc_taxify_scheduled_bulk_orders', array( $this, 'file_orders' ) );
			add_action( 'wc_taxify_scheduled_missed_order', array( $this, 'file_orders' ) );
		}
	}

	/**
	 * Schedule an action/event
	 *
	 * @since 1.0
	 *
	 * @param int    $timestamp
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 *
	 * @return bool
	 */
	public function schedule( $timestamp, $hook, $args = array(), $group = '' ) {
		// prevent duplicates
		if ( ! $this->get_scheduled_event_timestamp( $hook, $args, $group ) ) {
			return is_numeric( wc_schedule_single_action( $timestamp, $hook, $args, $group ) ) ? true : false;
		}

		return false;
	}

	/**
	 * Get scheduled event timestamp
	 *
	 * @since 1.0
	 *
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 *
	 * @return bool|int
	 */
	public function get_scheduled_event_timestamp( $hook, $args = null, $group = '' ) {
		if ( ! empty( $hook ) && ! empty( $args ) ) {
			return wc_next_scheduled_action( $hook, array( 'id' => (int) $args ), $group );
		}

		return false;
	}

	/**
	 * Unschedule possibly previously scheduled task(s)
	 *
	 * @since 1.0
	 *
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 *
	 * @return void
	 */
	public function unschedule( $hook, $args = array(), $group = '' ) {
		if ( ! empty( $hook ) && ! empty( $args ) ) {
			wc_unschedule_action( $hook, $args, $group );
		}
	}

	/**
	 * Unschedule multiple events
	 *
	 * @since 1.0
	 *
	 * @param array  $hooks
	 * @param array  $args
	 * @param string $group
	 *
	 */
	public function unschedule_multiple( $hooks, $args, $group = '' ) {
		if ( ! empty( $hooks ) && is_array( $hooks ) && ! empty( $args ) ) {
			foreach ( $hooks as $hook ) {
				$this->unschedule( $hook, array( 'id' => (int) $args ), $group );
			}
		}
	}

	/**
	 * Unschedule all defined events
	 *
	 * @since 1.0
	 *
	 * @param array  $args
	 * @param string $group
	 *
	 * @return void
	 */
	public function unschedule_all( $args, $group = '' ) {
		if ( ! empty( $args ) && is_array( $this->scheduler_hooks ) ) {
			foreach ( $this->scheduler_hooks as $hook => $callable ) {
				$this->unschedule( $hook, array( 'id' => (int) $args ), $group );
			}
		}
	}

	/**
	 * Get all scheduled events' timestamps
	 *
	 * @since 1.0
	 *
	 * @param int $id
	 *
	 * @return array
	 */
	public function get_scheduled_events_timestamps( $id ) {
		$events = array();

		if ( is_array( $this->scheduler_hooks ) && ! empty( $id ) ) {
			foreach ( $this->scheduler_hooks as $hook => $callable ) {
				$timestamp = $this->get_scheduled_event_timestamp( $hook, $id );

				if ( $timestamp ) {
					$events[] = array(
						'hook'      => $hook,
						'timestamp' => $timestamp,
					);
				}
			}
		}

		return $events;
	}

	/**
	 * Get scheduled event datetime
	 *
	 * @since 1.0
	 *
	 * @param string $hook
	 * @param array  $args
	 * @param string $group
	 *
	 * @return string|boolean
	 */
	public function get_scheduled_event_datetime( $hook, $args = null, $group = '' ) {
		if ( ! empty( $hook ) && ! empty( $args ) ) {
			// Get timestamp of the scheduled event
			$timestamp = $this->get_scheduled_event_timestamp( $hook, $args, $group );

			if ( ! $timestamp ) {
				return false;
			}

			return WCT_FORMAT()->get_adjusted_datetime( $timestamp, null, $hook );
		}

		return false;
	}

	/**
	 * Schedule all complted orders from 13 months ago to be filed with Taxify.
	 * This only runs once when the Taxify API Key is activated.
	 *
	 * @since 1.0
	 */
	public function schedule_bulk_orders() {
		$time_for_comparison = WCT_FORMAT()->get_mysql_time_13_months_ago();
		$order_ids           = WCT_ORDER()->get_order_completed_ids( $time_for_comparison );
		$time                = 5;

		if ( ! empty( $order_ids ) && is_array( $order_ids ) ) {
			foreach ( $order_ids as $order_id ) {
				if ( get_post_status( $order_id ) == 'wc-refunded' ) {
					$order = wc_get_order( $order_id );

					if ( WCTAXIFY()->wc_version >= '3.0' ) {
						$order_total = $order->get_total();
					} else {
						$order_total = get_post_meta( $order_id, '_order_total', true );
					}

					// Don't file fully refunded orders
					if ( $order->get_total_refunded() >= $order_total ) {
						continue;
					}
				}

				// Don't file tax exempt orders
				//if ( WCT_TAX()->is_order_tax_exempt( $order_id ) ) {
				//	continue;
				//}

				if ( ! WCT_TAX()->is_commited_to_taxify( $order_id ) ) {
					/**
					 * Orders to be filed with Taxify in 5 second increments.
					 */
					$this->schedule( time() + $time += 5, 'wc_taxify_scheduled_bulk_orders', array( 'id' => (int) $order_id ) );
				}
			}

			add_option( 'wc_taxify_did_schedule_bulk_orders', 'yes', '', 'yes' );

			/**
			 * @since 1.1.2
			 */
			add_option( 'wc_taxify_did_schedule_tax_exempt_bulk_orders', 'yes', '', 'yes' );
		}
	}

	/**
	 * Schedules a single order to be filed with Taxify 24 hours from now
	 *
	 * @since 1.0
	 *
	 * @param int $id
	 */
	public function schedule_missed_order( $id ) {
		if ( WCT_TAX()->order_exists( $id ) ) {
			$this->schedule( time() + HOUR_IN_SECONDS, 'wc_taxify_scheduled_missed_order', array( 'id' => (int) $id ) );
		} else {
			WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: Attempted to schedule order# %d to be filed at Taxify, but the order no longer exists.', 'woocommerce-taxify' ), absint( $id ) ) );
		}
	}

	/**
	 * File orders with Taxify
	 *
	 * @since 1.0
	 *
	 * @param int $id
	 */
	public function file_orders( $id ) {
		// Don't file orders more than once
		if ( ! WCT_TAX()->is_commited_to_taxify( $id ) && WCT_TAX()->is_order_status_completed( $id ) ) {
			if ( WCT_TAX()->order_exists( $id ) ) {
				WCT_ORDER()->order_completed( $id, true );
			} else {
				WCT_LOG()->log( 'taxify', sprintf( __( 'TAXIFY ERROR: Attempted to file order# %d at Taxify, but the order no longer exists.', 'woocommerce-taxify' ), absint( $id ) ) );
			}
		}
	}

} // End class