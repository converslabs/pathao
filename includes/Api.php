<?php
/**
 * API Class
 *
 * @package SpringDevs\Pathao\API
 */

namespace SpringDevs\Pathao;

use WP_Error;
use WP_REST_Request;

/**
 * API Class
 */
class Api {


	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_api' ) );
	}

	/**
	 * Register the API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_api() {
		register_rest_route(
			'api/v1',
			'pathao-status-endpoint/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'pathao_status_changed' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Receive webhook from pathao.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function pathao_status_changed( WP_REST_Request $request ) {
		$signature     = $request->get_header( 'X-PATHAO-Signature' );
		$client_secret = get_option( 'pathao_client_secret' );
		if ( ! $client_secret || $signature !== $client_secret ) {
			return array(
				'success' => false,
				'message' => 'Invalid signature',
			);
		}

		$consignment_id = sanitize_text_field( $request->get_param( 'consignment_id' ) );
		$order_id       = sanitize_text_field( $request->get_param( 'merchant_order_id' ) );
		$status         = sanitize_text_field( $request->get_param( 'order_status_slug' ) );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order_consignment_id = $order->get_meta( '_pathao_consignment_id', true );

		if ( $consignment_id !== $order_consignment_id ) {
			return new WP_Error( 'invalid_consignment_id', 'Invalid consignment id.', array( 'status' => 400 ) );
		}

		if ( ! is_sdevs_pathao_pro_activated() ) {
			if ( 'Delivered' === $status ) {
				$order = wc_get_order( $order_id );
				$order->update_status( 'completed' );
			}

			if ( in_array( $status, array( 'Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed' ), true ) ) {
				$order = wc_get_order( $order_id );
				$order->update_status( 'failed' );
			}
		}

		$order->update_meta_data( '_pathao_order_status', $status );
		$order->save();

		do_action(
			'pathao_process_webhook',
			$status,
			$request->get_params()
		);

		return array( 'success' => true );
	}
}
