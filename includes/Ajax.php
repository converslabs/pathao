<?php

namespace SpringDevs\Pathao;

use SpringDevs\Pathao\Facades\PathaoAPI;

if (! defined('ABSPATH')) exit;

class Ajax {

    private static $instance = null;

    /**
     * Initialize the Ajax class (singleton)
     */
    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        error_log("[Pathao Debug] Ajax class initialized");

        // Setup Ajax hooks for both logged-in and guest users
        add_action('wp_ajax_nopriv_setup_pathao', [$this, 'setup_pathao']);
        add_action('wp_ajax_setup_pathao', [$this, 'setup_pathao']);

        add_action('wp_ajax_nopriv_get_cities', [$this, 'get_cities']);
        add_action('wp_ajax_get_cities', [$this, 'get_cities']);

        add_action('wp_ajax_nopriv_get_city_zones', [$this, 'get_city_zones']);
        add_action('wp_ajax_get_city_zones', [$this, 'get_city_zones']);

        add_action('wp_ajax_nopriv_get_zone_areas', [$this, 'get_zone_areas']);
        add_action('wp_ajax_get_zone_areas', [$this, 'get_zone_areas']);

        add_action('wp_ajax_nopriv_send_order_to_pathao', [$this, 'send_order_to_pathao']);
        add_action('wp_ajax_send_order_to_pathao', [$this, 'send_order_to_pathao']);
    }

	/**
	 * Get cities.
	 *
	 * @return void
	 */
	public function get_cities()
	{
		error_log("[Pathao Debug] get_cities ajax called");

		$order_id = sanitize_text_field(wp_unslash($_POST['order_id']));
		$cities   = PathaoAPI::get_cities();

		wp_send_json(
			array(
				'cities' => $cities->data,
				'value'  => apply_filters('pathao_selected_order_city_value', null, $order_id),
			)
		);
	}

	/**
	 * Generate token & save it.
	 *
	 * @return void
	 */
	public function setup_pathao()
	{
		error_log("[Pathao Debug] setup_pathao ajax called");

		if (! isset($_POST['client_id'], $_POST['client_secret'], $_POST['client_username'], $_POST['_wpnonce'], $_POST['client_password'], $_POST['sandbox_mode']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), '_pathao_setup_nonce') || ! current_user_can('manage_options')) {
			return;
		}

		$client_id     = sanitize_text_field(wp_unslash($_POST['client_id']));
		$client_secret = sanitize_text_field(wp_unslash($_POST['client_secret']));
		$data          = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'username'      => sanitize_email(wp_unslash($_POST['client_username'])),
			'password'      => sanitize_text_field(wp_unslash($_POST['client_password'])),
		);

		// Log submitted form data for debugging. Mask sensitive values.
		$submitted_password = $data['password'];
		$masked_password = '';
		if (null !== $submitted_password) {
			$len = strlen($submitted_password);
			if ($len <= 2) {
				$masked_password = str_repeat('*', $len);
			} else {
				$masked_password = substr($submitted_password, 0, 1) . str_repeat('*', $len - 2) . substr($submitted_password, -1);
			}
		}

		$masked_secret = $client_secret;
		if ($masked_secret && strlen($masked_secret) > 4) {
			$masked_secret = str_repeat('*', strlen($masked_secret) - 4) . substr($masked_secret, -4);
		}

		$raw_sandbox = isset($_POST['sandbox_mode']) ? (bool) sanitize_text_field(wp_unslash($_POST['sandbox_mode'])) : false;


		// error_log( 'Pathao json setup form submitted: ' . wp_json_encode( array(
		// 	'client_id' => $client_id,
		// 	'client_secret_masked' => $masked_secret,
		// 	'username' => $data['username'],
		// 	'password_masked' => $masked_password,
		// 	'sandbox_mode_raw' => $raw_sandbox,
		// ) ) );

		update_option('pathao_sandbox_mode', 'true' === $_POST['sandbox_mode'] ? true : false);

		$res = PathaoAPI::generate_tokens($data);

		if ($res->success) {
			update_option('pathao_client_id', $client_id);
			update_option('pathao_client_secret', $client_secret);
			update_option('pathao_access_token', $res->data->access_token);
			update_option('pathao_refresh_token', $res->data->refresh_token);
			wp_send_json(
				array(
					'success'       => true,
					'access_token'  => $res->data->access_token,
					'refresh_token' => $res->data->refresh_token,
				)
			);
		}

		wp_send_json($res);
	}

	/**
	 * Get zones from pathao server.
	 *
	 * @return void
	 */
	public function get_city_zones()
	{
		error_log("[Pathao Debug] get_city_zones ajax called");
		if (! isset($_POST['nonce'], $_POST['order_id'], $_POST['city']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pathao_send_order')) {
			return;
		}

		$order_id = sanitize_text_field(wp_unslash($_POST['order_id']));
		$city     = sanitize_text_field(wp_unslash($_POST['city']));

		$zones = PathaoAPI::get_zones($city);
		$zones = $zones->success ? $zones->data : array();

		wp_send_json(
			array(
				'zones' => $zones,
				'value' => apply_filters('pathao_selected_order_zone_value', null, $order_id),
			)
		);
	}

	/**
	 * Get areas from pathao server.
	 *
	 * @return void
	 */
	public function get_zone_areas()
	{
		error_log("[Pathao Debug] get_zone_areas ajax called");
		if (! isset($_POST['nonce'], $_POST['order_id'], $_POST['zone']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'pathao_send_order')) {
			return;
		}

		$order_id = sanitize_text_field(wp_unslash($_POST['order_id']));
		$zone     = sanitize_text_field(wp_unslash($_POST['zone']));
		$areas    = PathaoAPI::get_areas($zone);
		$areas    = $areas->success ? $areas->data : array();

		wp_send_json(
			array(
				'areas' => $areas,
				'value' => apply_filters('pathao_selected_order_area_value', null, $order_id),
			)
		);
	}

	/**
	 * Send order to Pathao.
	 */
	// error_log("[Pathao Debug] _ajax_class_called");

	public function send_order_to_pathao()
	{

		error_log("[Pathao Debug] send_order_to_pathao called");

		$order_id = 163; // test order ID
		$order = wc_get_order($order_id);
		$consignment_id = $order->get_meta('_pathao_consignment_id', true);
		error_log("[DEBUG] Order ID $order_id - consignment_id meta: " . var_export($consignment_id, true));

		$order_id = $_POST['order_id'] ?? 'N/A';
		error_log('[Pathao Debug] AJAX send_order_to_pathao triggered for order_id=' . $order_id);

		// Check required POST fields
		$required_fields = ['nonce', 'order_id', 'item_type', 'delivery_type', 'amount', 'item_weight'];
		foreach ($required_fields as $field) {
			if (!isset($_POST[$field])) {
				error_log("[Pathao Debug] Missing POST field: $field");
			}
		}

		// Verify nonce
		if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'pathao_send_order')) {
			error_log('[Pathao Debug] Invalid nonce');
			wp_send_json([
				'success' => false,
				'errors' => ['Invalid nonce'],
			]);
		}

		$order_id = sanitize_text_field(wp_unslash($_POST['order_id']));
		$body = [];

		// Build API request body
		$fields = [
			'recipient_city'       => 'city',
			'recipient_zone'       => 'zone',
			'recipient_area'       => 'area',
			'item_description'     => 'item_description',
			'special_instruction'  => 'special_instruction',
			'delivery_type'        => 'delivery_type',
			'item_type'            => 'item_type',
			'item_weight'          => 'item_weight',
			'amount_to_collect'    => 'amount',
		];

		foreach ($fields as $key => $post_key) {
			if (!empty($_POST[$post_key])) {
				$body[$key] = sanitize_text_field(wp_unslash($_POST[$post_key]));
			} else {
				error_log("[Pathao Debug] POST field empty: $post_key");
			}
		}

		error_log('[Pathao Debug] Sending request to PathaoAPI::send_order. Body: ' . print_r($body, true));

		$res_data = PathaoAPI::send_order($order_id, $body);
		error_log("chcking_consignment_id", print_r($res_data, true));

		// Log API response
		error_log('[Pathao Debug] PathaoAPI::send_order result: ' . print_r($res_data, true));

		if (!$res_data || !isset($res_data->success) || !$res_data->success) {
			error_log('[Pathao Debug] PathaoAPI send_order failed');
			wp_send_json([
				'success' => false,
				'errors' => $res_data->messages ?? ['Unknown error from Pathao API'],
			]);
		}

		// Ensure consignment_id exists
		if (empty($res_data->data->consignment_id)) {
			error_log('[Pathao Debug] Consignment ID missing in response');
		} else {
			error_log('[Pathao Debug] Consignment ID received: ' . $res_data->data->consignment_id);
		}

		// Save meta
		$order = wc_get_order($order_id);
		if (!$order) {
			error_log('[Pathao Debug] Order not found for ID: ' . $order_id);
			wp_send_json(['success' => false, 'errors' => ['Order not found']]);
		}

		$order->update_meta_data('_pathao_consignment_id', $res_data->data->consignment_id ?? '');
		$order->update_meta_data('_pathao_delivery_fee', $res_data->data->delivery_fee ?? '');
		$order->update_meta_data('_pathao_order_status', $res_data->data->order_status ?? '');
		$order->save();
		error_log('[Pathao Debug] Order meta updated for order_id=' . $order_id);

		do_action('pathao_order_created', $res_data->data);

		wp_send_json([
			'success' => true,
			'message' => 'Order sent to Pathao successfully.',
		]);
	}
}
