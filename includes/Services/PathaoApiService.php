<?php

namespace SpringDevs\Pathao\Services;

use stdClass;

/**
 * Pathao API service class.
 */
class PathaoApiService {

	/**
	 * Return base url based on sandmode?.
	 *
	 * @return string
	 */
	private function get_base_url(): string {
		return get_option( 'pathao_sandbox_mode' ) ? 'https://courier-api-sandbox.pathao.com/' : 'https://api-hermes.pathao.com/';
	}

	/**
	 * Get pathao access token from option.
	 *
	 * @return string
	 */
	private function get_access_token(): string {
		return get_option( 'pathao_access_token' );
	}

	/**
	 * Process request.
	 *
	 * @param callable $func wp_remote functions.
	 * @param string   $path path.
	 * @param array    $args args.
	 *
	 * @return array|\WP_Error
	 */
	private function request( callable $func, string $path, $args = array() ) {
		return $func(
			$this->get_base_url() . $path,
			array_merge(
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->get_access_token(),
						'Accept'        => 'application/json',
					),
				),
				$args
			)
		);
	}

	/**
	 * Handle response errors.
	 *
	 * @param array $res Response.
	 *
	 * @return false|\stdClass
	 */
	private function has_errors( $res ): false|\stdClass {
		$res_code = wp_remote_retrieve_response_code( $res );
		$data     = new \stdClass();
		if ( 401 === $res_code ) {
			$data->success  = false;
			$data->messages = array( __( 'Invalid access token !', 'sdevs_pathao' ) );
			return $data;
		} elseif ( 422 === $res_code ) {
			$res_body = wp_remote_retrieve_body( $res );
			$errors   = json_decode( $res_body )->errors;
			$messages = array();
			array_walk_recursive(
				get_object_vars( $errors ),
				function ( $msg ) use ( &$messages ) {
					$messages[] = $msg;
				}
			);

			$data->success  = false;
			$data->messages = $messages;
			return $data;
		} elseif ( 400 === $res_code ) {
			$data->success  = false;
			$data->messages = array( __( 'The user credentials were incorrect.', 'sdevs_pathao' ) );
			return $data;
		} elseif ( 200 > $res_code || 299 < $res_code ) {
			$data->success  = false;
			$data->messages = array( __( 'Something went wrong! Try again', 'sdevs_pathao' ) );
			return $data;
		}

		return false;
	}

	/**
	 * Check if there any transient saved.
	 *
	 * @param string $key Transient Key.
	 *
	 * @return false|\stdClass
	 */
	private function has_transient( $key ): false|\stdClass {
		$has_transient = get_transient( $key );

		if ( $has_transient ) {
			$data          = new \stdClass();
			$data->success = true;
			$data->data    = $has_transient;
			return $data;
		}

		return false;
	}

	/**
	 * Get cities from pathao server.
	 *
	 * @return \stdClass
	 */
	public function get_cities(): \stdClass {
		$transient_key = '_sdevs_pathao_cities';

		$has_transient = $this->has_transient( $transient_key );
		if ( $has_transient ) {
			return $has_transient;
		}

		$res = $this->request( 'wp_remote_get', 'aladdin/api/v1/city-list' );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$encoded_body = wp_remote_retrieve_body( $res );

		$body          = json_decode( $encoded_body );
		$data          = new \stdClass();
		$data->success = true;
		$data->data    = array();
		foreach ( $body->data->data as $city ) {
			$data->data[] = (object) array(
				'id'   => $city->city_id,
				'name' => $city->city_name,
			);
		}

		// set transient for 12-hrs.
		set_transient( $transient_key, $data->data, 720 * 60 );

		return $data;
	}

	/**
	 * Get zones from pathao server.
	 *
	 * @param int $city_id City Id.
	 *
	 * @return \stdClass
	 */
	public function get_zones( int $city_id ): \stdClass {
		$transient_key = "_sdevs_pathao_city_{$city_id}_zones";

		$has_transient = $this->has_transient( $transient_key );
		if ( $has_transient ) {
			return $has_transient;
		}

		$res = $this->request( 'wp_remote_get', "/aladdin/api/v1/cities/$city_id/zone-list" );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$encoded_body = wp_remote_retrieve_body( $res );

		$body          = json_decode( $encoded_body );
		$data          = new \stdClass();
		$data->success = true;
		$data->data    = array();
		foreach ( $body->data->data as $zone ) {
			$data->data[] = (object) array(
				'id'   => $zone->zone_id,
				'name' => $zone->zone_name,
			);
		}

		// set transient for 12-hrs.
		set_transient( $transient_key, $data->data, 720 * 60 );

		return $data;
	}

	/**
	 * Get areas from pathao server.
	 *
	 * @param int $zone_id Zone Id.
	 *
	 * @return \stdClass
	 */
	public function get_areas( int $zone_id ): \stdClass {
		$transient_key = "_sdevs_pathao_zone_{$zone_id}_areas";

		$has_transient = $this->has_transient( $transient_key );
		if ( $has_transient ) {
			return $has_transient;
		}

		$res = $this->request( 'wp_remote_get', "/aladdin/api/v1/zones/$zone_id/area-list" );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$encoded_body = wp_remote_retrieve_body( $res );

		$body          = json_decode( $encoded_body );
		$data          = new \stdClass();
		$data->success = true;
		$data->data    = array();
		foreach ( $body->data->data as $area ) {
			$data->data[] = (object) array(
				'id'   => $area->area_id,
				'name' => $area->area_name,
			);
		}

		// set transient for 12-hrs.
		set_transient( $transient_key, $data->data, 720 * 60 );

		return $data;
	}

	/**
	 * Get stores from pathao server.
	 *
	 * @return \stdClass
	 */
	public function get_stores(): \stdClass {
		$transient_key = '_sdevs_pathao_stores';

		$has_transient = $this->has_transient( $transient_key );
		if ( $has_transient ) {
			return $has_transient;
		}

		$res = $this->request( 'wp_remote_get', 'aladdin/api/v1/stores' );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$encoded_body = wp_remote_retrieve_body( $res );

		$body          = json_decode( $encoded_body );
		$data          = new \stdClass();
		$data->success = true;
		$data->data    = array();
		foreach ( $body->data->data as $store ) {
			$data->data[] = (object) array(
				'id'   => $store->store_id,
				'name' => $store->store_name,
			);
		}

		// set transient for 5-min.
		set_transient( $transient_key, $data->data, 5 * 60 );

		return $data;
	}

	/**
	 * Send order to pathao.
	 *
	 * @param int   $order_id Order Id.
	 * @param array $args data for body.
	 *
	 * @return \stdClass
	 */
	public function send_order( int $order_id, $args = array() ) {
		$order             = wc_get_order( $order_id );
		$recipient_name    = $order->get_formatted_shipping_full_name() !== ' ' ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name();
		$recipient_phone   = $order->get_shipping_phone() !== '' ? $order->get_shipping_phone() : $order->get_billing_phone();
		$recipient_phone   = substr( $recipient_phone, 0, 3 ) === '+88' ? str_replace( '+88', '', $recipient_phone ) : $recipient_phone;
		$recipient_address = $order->get_formatted_shipping_address() !== '' ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address();

		$item_weight = sdevs_pathao_get_totals_from_items( $order );

		$body = wp_parse_args(
			$args,
			array(
				'store_id'          => sdevs_pathao_store_id(),
				'merchant_order_id' => $order_id,
				'recipient_name'    => $recipient_name,
				'recipient_phone'   => $recipient_phone,
				'recipient_address' => $recipient_address,
				'delivery_type'     => apply_filters( 'sdevs_pathao_default_delivery_type', 48 ),
				'item_type'         => 2,
				'item_description'  => $item_weight->item_description,
				'item_quantity'     => $item_weight->quantity,
				'item_weight'       => $item_weight->weight,
				'amount_to_collect' => $order->has_status( 'paid' ) ? 0 : round( (float) $order->get_total() ),
			)
		);

		$res = $this->request( 'wp_remote_post', 'aladdin/api/v1/orders', array( 'body' => $body ) );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$body     = wp_remote_retrieve_body( $res );
		$res_data = json_decode( $body )->data;

		$data          = new \stdClass();
		$data->success = true;
		$data->data    = (object) array(
			'consignment_id'    => $res_data->consignment_id,
			'merchant_order_id' => $res_data->merchant_order_id,
			'order_status'      => $res_data->order_status,
			'delivery_fee'      => $res_data->delivery_fee,
		);

		return $data;
	}

	/**
	 * Price calulation.
	 *
	 * @param array $args Arguments.
	 *
	 * @return \stdClass
	 */
	public function price_calculation( $args ) {
		$body = wp_parse_args(
			$args,
			array(
				'store_id'      => sdevs_pathao_store_id(),
				'item_type'     => 2,
				'delivery_type' => apply_filters( 'sdevs_pathao_default_delivery_type', 48 ),
			)
		);

		$res = $this->request( 'wp_remote_post', 'aladdin/api/v1/merchant/price-plan', array( 'body' => $body ) );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$encoded_data = wp_remote_retrieve_body( $res );
		$decoded_data = json_decode( $encoded_data );

		$data          = new \stdClass();
		$data->success = true;
		$data->data    = (object) array(
			'price'       => $decoded_data->data->price,
			'cod_enabled' => $decoded_data->data->cod_enabled,
		);

		return $data;
	}

	/**
	 * Generate Token from pathao server.
	 *
	 * @param array $args Args.
	 *
	 * @return \stdClass
	 */
	public function generate_tokens( $args ) {
		$body = wp_parse_args(
			$args,
			array(
				'grant_type' => 'password',
			)
		);

		$res = $this->request( 'wp_remote_post', 'aladdin/api/v1/issue-token', array( 'body' => $body ) );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$body     = wp_remote_retrieve_body( $res );
		$res_data = json_decode( $body );

		$data          = new \stdClass();
		$data->success = true;
		$data->data    = (object) array(
			'access_token'  => $res_data->access_token,
			'refresh_token' => $res_data->refresh_token,
		);

		return $data;
	}

	/**
	 * Refresh tokens.
	 *
	 * @return \stdClass
	 */
	public function refresh_tokens() {
		$client_id     = get_option( 'pathao_client_id' );
		$client_secret = get_option( 'pathao_client_secret' );
		$refresh_token = get_option( 'pathao_refresh_token' );

		$data = new stdClass();
		if ( ! $client_id || ! $client_secret || ! $refresh_token ) {
			$data->success  = false;
			$data->messages = array( __( 'Please generate tokens at first!', 'sdevs_pathao' ) );
			return;
		}

		$body = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'refresh_token' => $refresh_token,
			'grant_type'    => 'refresh_token',
		);

		$res = $this->request( 'wp_remote_post', 'aladdin/api/v1/issue-token', array( 'body' => $body ) );

		$has_errors = $this->has_errors( $res );
		if ( $has_errors ) {
			return $has_errors;
		}

		$body     = wp_remote_retrieve_body( $res );
		$res_data = json_decode( $body );

		$data->success = true;
		$data->data    = (object) array(
			'access_token'  => $res_data->access_token,
			'refresh_token' => $res_data->refresh_token,
		);

		return $data;
	}
}
