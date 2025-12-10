<?php

namespace SpringDevs\Pathao\Services;

use stdClass;

/**
 * Pathao API service class.
 */
class PathaoApiService
{

	/**
	 * Return base url based on sandmode?.
	 *
	 * @return string
	 */
	private function get_base_url(): string
	{
		return get_option('pathao_sandbox_mode') ? 'https://courier-api-sandbox.pathao.com/' : 'https://api-hermes.pathao.com/';
	}

	/**
	 * Get pathao access token from option.
	 *
	 * @return string
	 */
	private function get_access_token(): string
	{
		return get_option('pathao_access_token');
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
	private function request(callable $func, string $path, $args = array())
	{
		$token = $this->get_access_token();

		error_log('[Pathao Debug] Attempting API call to: ' . $this->get_base_url() . $path);
		error_log('[Pathao Debug] Access token present: ' . ($token ? 'Yes' : 'No'));
		error_log('[Pathao Debug] Request args (before merge): ' . print_r($args, true));

		// Required headers for Pathao
		$default_headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json',
		);

		$final_args = array(
			'headers' => $default_headers,
		);

		// Merge in user args (body etc)
		if (!empty($args)) {
			$final_args = array_merge($final_args, $args);
		}

		error_log('[Pathao Debug] Final request args: ' . print_r($final_args, true));

		return $func(
			$this->get_base_url() . $path,
			$final_args
		);
	}


	/**
	 * Handle response errors.
	 *
	 * @param array $res Response.
	 *
	 * @return false|\stdClass
	 */
	private function has_errors($res)
	{
		$res_code = wp_remote_retrieve_response_code($res);
		$data     = new \stdClass();
		if (401 === $res_code) {
			$data->success  = false;
			$data->messages = array(__('Invalid access token !', 'integration-of-pathao-for-woocommerce'));
			return $data;
		} elseif (422 === $res_code) {
			$res_body = wp_remote_retrieve_body($res);
			$errors   = json_decode($res_body)->errors;
			$messages = array();
			array_walk_recursive(
				get_object_vars($errors),
				function ($msg) use (&$messages) {
					$messages[] = $msg;
				}
			);

			$data->success  = false;
			$data->messages = $messages;
			return $data;
		} elseif (400 === $res_code) {
			$data->success  = false;
			$data->messages = array(__('The user credentials were incorrect.', 'integration-of-pathao-for-woocommerce'));
			return $data;
		} elseif (200 > $res_code || 299 < $res_code) {
			$data->success  = false;
			$data->messages = array(__('Something went wrong! Try again', 'integration-of-pathao-for-woocommerce'));
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
	private function has_transient($key)
	{
		$has_transient = get_transient($key);

		if ($has_transient) {
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
		public function get_cities() {
			$transient_key = '_sdevs_pathao_cities';

			// Check cache first
			$has_transient = $this->has_transient($transient_key);
			if ($has_transient) {
				error_log("[Pathao Debug] get_cities(): Using cached data");
				return $has_transient;
			}

			error_log("------------------------------------------------------");
			error_log("[Pathao Debug] get_cities() CALLED");

			// URL with sandbox/base url support
			$url = 'aladdin/api/v1/cities';
			error_log("[Pathao Debug] Request URL: " . $this->get_base_url() . $url);

			// Make API call using existing helper
			$res = $this->request('wp_remote_get', $url);

			error_log("[Pathao Debug] Raw response from /cities: " . print_r($res, true));

			// Catch errors using existing logic
			$has_errors = $this->has_errors($res);
			if ($has_errors) {
				error_log("[Pathao Debug] /cities ERROR parsed: " . print_r($has_errors, true));
				return $has_errors;
			}

			// Parse body
			$body = wp_remote_retrieve_body($res);
			error_log("[Pathao Debug] /cities RAW BODY: " . $body);

			$decoded = json_decode($body);
			error_log("[Pathao Debug] /cities DECODED BODY: " . print_r($decoded, true));

			// Validate structure
			if (!isset($decoded->data->data)) {
				$error = new \stdClass();
				$error->success = false;
				$error->messages = ['Cities data missing or malformed'];

				error_log("[Pathao Debug] ERROR: Missing data field");
				return $error;
			}

			// Format cities
			$data          = new \stdClass();
			$data->success = true;
			$data->data    = array();

			foreach ($decoded->data->data as $city) {
				$data->data[] = (object) [
					'id'   => $city->city_id,
					'name' => $city->city_name,
				];
			}

			error_log("[Pathao Debug] Parsed Cities: " . print_r($data->data, true));

			// Cache for 12 hours
			set_transient($transient_key, $data->data, 720 * 60);

			error_log("[Pathao Debug] get_cities() COMPLETED");
			error_log("------------------------------------------------------");

			return $data;
		}



	/**
	 * Get zones from pathao server.
	 *
	 * @param int $city_id City Id.
	 *
	 * @return \stdClass
	 */
	public function get_zones($city_id) {
		$url = 'https://api-hermes.pathao.com/aladdin/api/v1/zones?city_id=' . intval($city_id);

		error_log("------------------------------------------------------");
		error_log("[Pathao Debug] get_zones() CALLED");
		error_log("[Pathao Debug] City ID: $city_id");
		error_log("[Pathao Debug] Request URL: " . $url);

		$access_token = $this->get_access_token();

		if (!$access_token) {
			error_log("[Pathao Debug] Access token missing");
			return array();
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			),
			'timeout' => 30
		);

		error_log("[Pathao Debug] Final request args: " . print_r($args, true));

		$response = wp_remote_get($url, $args);

		error_log("[Pathao Debug] Raw response from /zones: " . print_r($response, true));

		if (is_wp_error($response)) {
			error_log("[Pathao Debug] ERROR: " . $response->get_error_message());
			return array();
		}

		$body = wp_remote_retrieve_body($response);
		error_log("[Pathao Debug] /zones RAW BODY: " . $body);

		$decoded = json_decode($body);
		error_log("[Pathao Debug] /zones DECODED BODY: " . print_r($decoded, true));

		if (!isset($decoded->data->data)) {
			error_log("[Pathao Debug] ERROR: Missing data field");
			return array();
		}

		$zones_list = array();
		foreach ($decoded->data->data as $zone) {
			$zones_list[] = array(
				'id'   => $zone->zone_id,
				'name' => $zone->zone_name,
			);
		}

		error_log("[Pathao Debug] Parsed Zones: " . print_r($zones_list, true));
		error_log("[Pathao Debug] get_zones() COMPLETED");
		error_log("------------------------------------------------------");

		return $zones_list;
	}


	/**
	 * Get areas from pathao server.
	 *
	 * @param int $zone_id Zone Id.
	 *
	 * @return \stdClass
	 */
	public function get_areas(int $zone_id): \stdClass
	{
		$transient_key = "_sdevs_pathao_zone_{$zone_id}_areas";

		$has_transient = $this->has_transient($transient_key);
		if ($has_transient) {
			return $has_transient;
		}

		$res = $this->request('wp_remote_get', "/aladdin/api/v1/zones/$zone_id/area-list");

		$has_errors = $this->has_errors($res);
		if ($has_errors) {
			return $has_errors;
		}

		$encoded_body = wp_remote_retrieve_body($res);

		$body          = json_decode($encoded_body);
		$data          = new \stdClass();
		$data->success = true;
		$data->data    = array();
		foreach ($body->data->data as $area) {
			$data->data[] = (object) array(
				'id'   => $area->area_id,
				'name' => $area->area_name,
			);
		}

		// set transient for 12-hrs.
		set_transient($transient_key, $data->data, 720 * 60);

		return $data;
	}

	/**
	 * Get stores from pathao server.
	 *
	 * @return \stdClass
	 */

	public function get_stores(): \stdClass
	{
		$transient_key = '_sdevs_pathao_stores';

		// Check cache
		$has_transient = $this->has_transient($transient_key);
		if ($has_transient) {
			error_log("[Pathao Debug] get_stores(): Using cached data");
			return $has_transient;
		}

		$url = 'aladdin/api/v1/stores';

		error_log("------------------------------------------------------");
		error_log("[Pathao Debug] get_stores() CALLED");
		error_log("[Pathao Debug] Request URL: " . $this->get_base_url() . $url);

		// Make API call
		$res = $this->request('wp_remote_get', $url);

		error_log("[Pathao Debug] Raw response from /stores: " . print_r($res, true));

		// Handle errors
		$has_errors = $this->has_errors($res);
		if ($has_errors) {
			error_log("[Pathao Debug] /stores ERROR parsed: " . print_r($has_errors, true));
			return $has_errors;
		}

		// Extract body
		$encoded_body = wp_remote_retrieve_body($res);
		error_log("[Pathao Debug] /stores RAW BODY: " . $encoded_body);

		$body = json_decode($encoded_body);
		error_log("[Pathao Debug] /stores DECODED BODY: " . print_r($body, true));

		// Handle missing data
		if (
			!isset($body->data)
			|| !isset($body->data->data)
			|| !is_array($body->data->data)
		) {
			$err = new \stdClass();
			$err->success  = false;
			$err->messages = array('Stores data missing or malformed.');

			error_log("[Pathao Debug] ERROR: Stores structure is invalid");
			return $err;
		}

		// Build response
		$data          = new \stdClass();
		$data->success = true;
		$data->data    = array();

		foreach ($body->data->data as $store) {
			$data->data[] = (object) array(
				'id'   => $store->store_id ?? null,
				'name' => $store->store_name ?? '',
			);
		}

		error_log("[Pathao Debug] Parsed Stores: " . print_r($data->data, true));

		// Cache for 5 minutes
		set_transient($transient_key, $data->data, 5 * 60);

		error_log("[Pathao Debug] get_stores() COMPLETED");
		error_log("------------------------------------------------------");

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
	public function send_order(int $order_id, $args = array())
	{
		$order             = wc_get_order($order_id);
		$recipient_name    = $order->get_formatted_shipping_full_name() !== ' ' ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name();
		$recipient_phone   = $order->get_shipping_phone() !== '' ? $order->get_shipping_phone() : $order->get_billing_phone();
		$recipient_phone   = substr($recipient_phone, 0, 3) === '+88' ? str_replace('+88', '', $recipient_phone) : $recipient_phone;
		$recipient_address = $order->get_formatted_shipping_address() !== '' ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address();

		$item_weight = sdevs_pathao_get_totals_from_items($order);

		$body = wp_parse_args(
			$args,
			array(
				'store_id'          => sdevs_pathao_store_id(),
				'merchant_order_id' => $order_id,
				'recipient_name'    => $recipient_name,
				'recipient_phone'   => $recipient_phone,
				'recipient_address' => $recipient_address,
				'delivery_type'     => apply_filters('sdevs_pathao_default_delivery_type', 48),
				'item_type'         => 2,
				'item_description'  => $item_weight->item_description,
				'item_quantity'     => $item_weight->quantity,
				'item_weight'       => $item_weight->weight,
				'amount_to_collect' => $order->has_status('paid') ? 0 : round((float) $order->get_total()),
			)
		);

		$res = $this->request('wp_remote_post', 'aladdin/api/v1/orders', array('body' => $body));
// error_log("hello res:",print_r($res, true));


		$has_errors = $this->has_errors($res);
		if ($has_errors) {
			return $has_errors;
		}

		$body     = wp_remote_retrieve_body($res);
		$res_data = json_decode($body)->data;

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
	public function price_calculation($args)
	{
		$body = wp_parse_args(
			$args,
			array(
				'store_id'      => sdevs_pathao_store_id(),
				'item_type'     => 2,
				'delivery_type' => apply_filters('sdevs_pathao_default_delivery_type', 48),
			)
		);

		$res = $this->request('wp_remote_post', 'aladdin/api/v1/merchant/price-plan', array('body' => $body));

		$has_errors = $this->has_errors($res);
		if ($has_errors) {
			return $has_errors;
		}

		$encoded_data = wp_remote_retrieve_body($res);
		$decoded_data = json_decode($encoded_data);

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
   error_log('[Pathao Debug] generate_tokens called with args: ' . print_r( $args, true ));

     $body = wp_parse_args(
         $args,
         array(
             'grant_type' => 'password',
         )
     );

     // JSON encode the body
     $json_body = json_encode( $body );

     // Make the request
     // $res = $this->request(
     //     'wp_remote_post',
     //     'aladdin/api/v1/issue-token',
     //     array( 'body' => $json_body )
     // );

	$res = $this->request(
		'wp_remote_post',
		'aladdin/api/v1/issue-token',
		array(
			'body' => json_encode( $body ), // encode array to JSON
		)
    );

    error_log('[Pathao Debug] Raw response from generate_tokens API: ' . print_r( $res, true ));

     $has_errors = $this->has_errors( $res );
     if ( $has_errors ) {
         error_log('[Pathao Debug] generate_tokens has errors: ' . print_r( $has_errors, true ));
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
	public function refresh_tokens()
	{
		$client_id     = get_option('pathao_client_id');
		$client_secret = get_option('pathao_client_secret');
		$refresh_token = get_option('pathao_refresh_token');

		$data = new stdClass();
		if (! $client_id || ! $client_secret || ! $refresh_token) {
			$data->success  = false;
			$data->messages = array(__('Please generate tokens at first!', 'integration-of-pathao-for-woocommerce'));
			return;
		}

		$body = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'refresh_token' => $refresh_token,
			'grant_type'    => 'refresh_token',
		);

		$res = $this->request('wp_remote_post', 'aladdin/api/v1/issue-token', array('body' => $body));

		$has_errors = $this->has_errors($res);
		if ($has_errors) {
			return $has_errors;
		}

		$body     = wp_remote_retrieve_body($res);
		$res_data = json_decode($body);

		$data->success = true;
		$data->data    = (object) array(
			'access_token'  => $res_data->access_token,
			'refresh_token' => $res_data->refresh_token,
		);

		return $data;
	}
}
