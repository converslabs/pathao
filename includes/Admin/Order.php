<?php

namespace SpringDevs\Pathao\Admin;

use WC_Order;

/**
 * Admin order related stuffs.
 */
class Order {

	/**
	 * The class contructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'pathao_order_created', array( $this, 'store_log_after_creation' ) );
	}

	/**
	 * Store log after pathao order created.
	 *
	 * @param mixed $res Response.
	 */
	public function store_log_after_creation( $res ) {
		global $wpdb;
		$log_table = $wpdb->prefix . 'pathao_logs';
		$wpdb->insert(
			$log_table,
			array(
				'order_id'          => (int) $res->merchant_order_id,
				'consignment_id'    => $res->consignment_id,
				'order_status'      => $res->order_status,
				'order_status_slug' => $res->order_status,
				'reason'            => __( 'Pathao Order created & it\'s pending.', 'sdevs_pathao' ),
				'updated_at'        => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Register metaboxes under order details page.
	 */
	public function register_meta_boxes() {
		$screen = sdevs_wc_order_hpos_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		// phpcs:ignore
		if (  isset( $_GET['action'] ) && 'edit' === $_GET['action']) {
			add_meta_box(
				'pathao_order_wc',
				__( 'Pathao Shipping', 'sdevs_pathao' ),
				array( $this, 'pathao_shipping' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Display order shipping form and details.
	 */
	public function pathao_shipping() {
		$order = wc_get_order( sdevs_wc_order_hpos_enabled() ? esc_html( $_GET['id'] ) : get_the_ID() );
		if ( ! $order ) {
			return;
		}
		$consignment_id = $order->get_meta( '_pathao_consignment_id' );
		$status         = $order->get_meta( '_pathao_order_status' );

		if ( $consignment_id && ! in_array( $status, array( 'Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed' ), true ) ) {
			$this->display_pathao_details( $order );
		} elseif ( $consignment_id && in_array( $status, array( 'Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed' ), true ) ) {
			$this->display_pathao_details( $order );
			$this->pathao_shipping_form( $order );
		} else {
			$this->pathao_shipping_form( $order );
		}
	}

	/**
	 *  Display shipping details.
	 *
	 *  @param WC_Order $order Current order.
	 */
	public function display_pathao_details( WC_Order $order ) {
		$consignment_id = $order->get_meta( '_pathao_consignment_id' );
		$delivery_fee   = $order->get_meta( '_pathao_delivery_fee' );
		$order_status   = $order->get_meta( '_pathao_order_status' );
		include 'views/pathao-shipping-details.php';
	}

	/**
	 * Display shipping form.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function pathao_shipping_form( WC_Order $order ) {
		$order_id = $order->get_id();
		wp_localize_script(
			'pathao_admin_script',
			'pathao_admin_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'order_id' => $order_id,
			)
		);
		wp_enqueue_style( 'pathao_toast_styles' );
		wp_enqueue_script( 'pathao_toast_script' );
		wp_enqueue_script( 'pathao_admin_script' );

		$cities = sdevs_get_pathao_data( 'aladdin/api/v1/countries/1/city-list' );
		$cities = $cities && 'success' === $cities->type ? $cities->data->data : array();

		$amount = is_sdevs_pathao_pro_activated() && $order->has_status( substr( sdevs_pathao_settings( 'paid_order_status', 'wc-paid' ), 3 ) ) ? 0 : $order->get_total();

		$total_weight = 0;
		foreach ( $order->get_items() as $order_item ) {
			$product = $order_item->get_product();
			if ( ! $product->is_virtual() ) {
				$total_weight += empty( $product->get_weight() ) ? 0 : intval( $product->get_weight() ) * $order_item['quantity'];
			}
		}
		$total_weight = floatval( max( $total_weight, 0.5 ) );
		$status       = $order->get_meta( '_pathao_order_status' );

		include 'views/pathao-shipping.php';
	}
}
