<?php

namespace SpringDevs\Pathao;

use SpringDevs\Pathao\Facades\PathaoAPI;

/**
 * The Ajax class.
 */
class Ajax {

	/**
	 * Initialize the class.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_setup_pathao', array( $this, 'setup_pathao' ) );
		add_action( 'wp_ajax_get_cities', array( $this, 'get_cities' ) );
		add_action( 'wp_ajax_get_city_zones', array( $this, 'get_city_zones' ) );
		add_action( 'wp_ajax_get_zone_areas', array( $this, 'get_zone_areas' ) );
		add_action( 'wp_ajax_send_order_to_pathao', array( $this, 'send_order_to_pathao' ) );
	}

	/**
	 * Get cities.
	 *
	 * @return void
	 */
	public function get_cities() {
		if ( ! isset( $_POST['nonce'], $_POST['order_id'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pathao_send_order' ) ) {
			return;
		}

		$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		$cities   = PathaoAPI::get_cities();

		wp_send_json(
			array(
				'cities' => $cities->data,
				'value'  => apply_filters( 'pathao_selected_order_city_value', null, $order_id ),
			)
		);
	}

	/**
	 * Generate token & save it.
	 *
	 * @return void
	 */
	public function setup_pathao() {
		if ( ! isset( $_POST['client_id'], $_POST['client_secret'], $_POST['client_username'], $_POST['_wpnonce'], $_POST['client_password'], $_POST['sandbox_mode'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), '_pathao_setup_nonce' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$client_id     = sanitize_text_field( wp_unslash( $_POST['client_id'] ) );
		$client_secret = sanitize_text_field( wp_unslash( $_POST['client_secret'] ) );
		$data          = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'username'      => sanitize_email( wp_unslash( $_POST['client_username'] ) ),
			'password'      => sanitize_text_field( wp_unslash( $_POST['client_password'] ) ),
		);

		update_option( 'pathao_sandbox_mode', 'true' === $_POST['sandbox_mode'] ? true : false );

		$res = PathaoAPI::generate_tokens( $data );

		if ( $res->success ) {
			update_option( 'pathao_client_id', $client_id );
			update_option( 'pathao_client_secret', $client_secret );
			update_option( 'pathao_access_token', $res->data->access_token );
			update_option( 'pathao_refresh_token', $res->data->refresh_token );
			wp_send_json(
				array(
					'success'       => true,
					'access_token'  => $res->data->access_token,
					'refresh_token' => $res->data->refresh_token,
				)
			);
		}

		wp_send_json( $res );
	}

	/**
	 * Get zones from pathao server.
	 *
	 * @return void
	 */
	public function get_city_zones() {
		if ( ! isset( $_POST['nonce'], $_POST['order_id'], $_POST['city'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pathao_send_order' ) ) {
			return;
		}

		$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		$city     = sanitize_text_field( wp_unslash( $_POST['city'] ) );

		$zones = PathaoAPI::get_zones( $city );
		$zones = $zones->success ? $zones->data : array();

		wp_send_json(
			array(
				'zones' => $zones,
				'value' => apply_filters( 'pathao_selected_order_zone_value', null, $order_id ),
			)
		);
	}

	/**
	 * Get areas from pathao server.
	 *
	 * @return void
	 */
	public function get_zone_areas() {
		if ( ! isset( $_POST['nonce'], $_POST['order_id'], $_POST['zone'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pathao_send_order' ) ) {
			return;
		}

		$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		$zone     = sanitize_text_field( wp_unslash( $_POST['zone'] ) );
		$areas    = PathaoAPI::get_areas( $zone );
		$areas    = $areas->success ? $areas->data : array();

		wp_send_json(
			array(
				'areas' => $areas,
				'value' => apply_filters( 'pathao_selected_order_area_value', null, $order_id ),
			)
		);
	}

	/**
	 * Send order to Pathao.
	 */
	public function send_order_to_pathao() {
		if ( ! isset( $_POST['nonce'], $_POST['order_id'], $_POST['item_type'], $_POST['delivery_type'], $_POST['amount'], $_POST['item_weight'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pathao_send_order' ) ) {
			wp_send_json(
				array(
					'success' => false,
					'errors'  => array( __( 'Invalid nonce', 'sdevs_pathao' ) ),
				)
			);
		}

		$order_id = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
		$body     = array();

		if ( ! empty( $_POST['city'] ) && ! empty( $_POST['zone'] ) ) {
			$body['recipient_city'] = sanitize_text_field( wp_unslash( $_POST['city'] ) );
			$body['recipient_zone'] = sanitize_text_field( wp_unslash( $_POST['zone'] ) );
			if ( ! empty( $_POST['area'] ) ) {
				$body['recipient_area'] = sanitize_text_field( wp_unslash( $_POST['area'] ) );
			}
		}

		if ( ! empty( $_POST['item_description'] ) ) {
			$body['item_description'] = trim( sanitize_text_field( wp_unslash( $_POST['item_description'] ) ) );
		}

		if ( ! empty( $_POST['special_instruction'] ) ) {
			$body['special_instruction'] = trim( sanitize_text_field( wp_unslash( $_POST['special_instruction'] ) ) );
		}

		if ( ! empty( $_POST['delivery_type'] ) ) {
			$body['delivery_type'] = sanitize_text_field( wp_unslash( $_POST['delivery_type'] ) );
		}

		if ( ! empty( $_POST['item_type'] ) ) {
			$body['item_type'] = sanitize_text_field( wp_unslash( $_POST['item_type'] ) );
		}

		if ( ! empty( $_POST['item_weight'] ) ) {
			$body['item_weight'] = sanitize_text_field( wp_unslash( $_POST['item_weight'] ) );
		}

		if ( ! empty( $_POST['amount'] ) ) {
			$body['amount_to_collect'] = sanitize_text_field( wp_unslash( $_POST['amount'] ) );
		}

		$res_data = PathaoAPI::send_order( $order_id, $body );

		if ( ! $res_data->success ) {
			wp_send_json(
				array(
					'success' => false,
					'errors'  => $res_data->messages,
				)
			);
		}

		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_pathao_consignment_id', $res_data->data->consignment_id );
		$order->update_meta_data( '_pathao_delivery_fee', $res_data->data->delivery_fee );
		$order->update_meta_data( '_pathao_order_status', $res_data->data->order_status );
		$order->save();

		do_action( 'pathao_order_created', $res_data->data );

		wp_send_json(
			array(
				'success' => true,
				'message' => 'Order sent to Pathao successfull.',
			)
		);
	}
}
