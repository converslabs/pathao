<?php

namespace SpringDevs\Pathao;

use SpringDevs\Pathao\Facades\PathaoAPI;

if (! defined('ABSPATH')) exit;

class Ajax
{

	private static $instance = null;

	/**
	 * Initialize the Ajax class (singleton)
	 */
	public static function init()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{

		// Setup Ajax hooks for both logged-in and guest users
		add_action('wp_ajax_nopriv_setup_pathao', [$this, 'setup_pathao']);
		add_action('wp_ajax_setup_pathao', [$this, 'setup_pathao']);

		add_action('wp_ajax_nopriv_get_cities', [$this, 'get_cities']);
		add_action('wp_ajax_get_cities', [$this, 'get_cities']);

		// add_action('wp_ajax_nopriv_get_city_zones', [$this, 'get_city_zones']);
		// add_action('wp_ajax_get_city_zones', [$this, 'get_city_zones']);

		add_action('wp_ajax_nopriv_get_zone_areas', [$this, 'get_zone_areas']);
		add_action('wp_ajax_get_zone_areas', [$this, 'get_zone_areas']);

		add_action('wp_ajax_get_wc_order_info', [$this, 'get_wc_order_info']);
		add_action('wp_ajax_nopriv_send_order_to_pathao', [$this, 'send_order_to_pathao']);
		add_action('wp_ajax_send_order_to_pathao', [$this, 'send_order_to_pathao']);
		add_action('admin_enqueue_scripts', [$this, 'sncue_admin']);
	}


	public function sncue_admin($hook)
	{

		// Check if we are on ANY WooCommerce order pag
			wp_enqueue_script(
				'pathao-popup',
				plugin_dir_url(__FILE__) . 'assets/js/popup.js',
				['jquery'],
				'2.0',
				true
			);

			wp_localize_script('pathao-popup', 'pathao_vars', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce('pathao_send_order'),
			]);
		

		// Load admin.js ONLY on Pathao settings
		if ($hook === 'toplevel_page_pathao') {
			wp_enqueue_script(
				'pathao-admin',
				plugin_dir_url(__FILE__) . 'assets/js/admin.js',
				['jquery'],
				'2.0',
				true
			);
			wp_localize_script('pathao-admin', 'pathao_admin_obj', [
				'ajax_url' => admin_url('admin-ajax.php'),
				'order_id' => isset($_GET['order_id']) ? intval($_GET['order_id']) : 0,
			]);
		}
	}

	/**
	 * Get cities.
	 *
	 * @return void
	 */
	public function get_cities()
	{  
		if ( ! isset($_POST['order_id']) ) {
		wp_send_json_error(['message' => 'Missing order_id']);
		}

		$order_id = sanitize_text_field(wp_unslash($_POST['order_id']));

		$response = PathaoAPI::get_cities();


		$cities = [];
		if (is_object($response) && isset($response->success) && $response->success && isset($response->data)) {
			$cities = $response->data;
		} 
		wp_send_json([
				'cities' => $cities,
				'value'  => apply_filters('pathao_selected_order_city_value', null, $order_id),
		]);
	}

	/**
	 * Generate token & save it.
	 *
	 * @return void
	 */
	public function setup_pathao()
	{
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

		// $raw_sandbox = isset($_POST['sandbox_mode']) ? (bool) sanitize_text_field(wp_unslash($_POST['sandbox_mode'])) : false;
		$raw_sandbox = false;

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


	public function get_wc_order_info()
	{
		$order_id = absint($_POST['order_id']);
		$order = wc_get_order($order_id);

		$items = [];
		foreach ($order->get_items() as $item) {
			$items[] = $item->get_name() . " x " . $item->get_quantity();
		}

		wp_send_json([
			'name' => $order->get_formatted_billing_full_name(),
			'phone' => $order->get_billing_phone(),
			'address' => $order->get_shipping_address_1(),
			'items' => implode("\n", $items),
			'weight' => 0.5,
			'quantity' => $order->get_item_count(),
			'total' => $order->get_total(),
			'payment_status' => $order->is_paid() ? 'Paid' : 'Unpaid',
			'cod_amount' => $order->is_paid() ? 0 : $order->get_total(),
			'store_name' => get_option('pathao_store_name'),
		]);
	}




	public function send_order_to_pathao($maybe_order_id = null)
	{
		// If called via AJAX, read POST params & verify nonce
		if (defined('DOING_AJAX') && DOING_AJAX) {
			// nonce field name is 'nonce' in your Order->pathao_shipping_form; adapt if different
			$nonce_name = isset($_POST['nonce']) ? 'nonce' : '_pathao_order_nonce';
			if (! isset($_POST[$nonce_name]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_name])), 'pathao_send_order')) {
				wp_send_json_error(array('message' => 'Security check failed.'));
			}

			if (! isset($_POST['order_id'])) {
				wp_send_json_error(array('message' => 'Missing order_id'));
			}

			$order_id = absint($_POST['order_id']);
			// optional: form posted as serialized string (from popup)
			$form_raw = isset($_POST['form']) ? wp_unslash($_POST['form']) : '';
			$form = array();
			if ($form_raw) {
				parse_str($form_raw, $form);
			}
		} else {
			// Called programmatically
			$order_id = absint($maybe_order_id);
			$form     = array();
		}

		if (! $order_id) {
			if (defined('DOING_AJAX') && DOING_AJAX) {
				wp_send_json_error(array('message' => 'Invalid order id'));
			}
			return false;
		}

		$order = wc_get_order($order_id);

		if (! $order) {
			if (defined('DOING_AJAX') && DOING_AJAX) {
				wp_send_json_error(array('message' => 'Order not found'));
			}
			return false;
		}

		$store_id = pathao_store_id();

		if (! $store_id) {
			if (defined('DOING_AJAX') && DOING_AJAX) {
				wp_send_json_error(array('message' => 'Pathao store_id not configured'));
			}
			return false;
		}

		// Build payload - prefer form values (popup) then fallback to order data
		$recipient_name    = ! empty($form['recipient_name']) ? sanitize_text_field($form['recipient_name']) : trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
		$recipient_phone = ! empty($form['recipient_phone'])
			? sanitize_text_field($form['recipient_phone'])
			: $order->get_billing_phone();

		$recipient_phone = str_replace('+88', '', $recipient_phone);

		$recipient_phone   = str_replace('+88', '', $recipient_phone);
		$recipient_address = ! empty($form['recipient_address']) ? sanitize_textarea_field($form['recipient_address']) : $order->get_shipping_address_1();

		$totals = sdevs_pathao_get_totals_from_items($order);
		$item_weight      = ! empty($form['item_weight']) ? floatval($form['item_weight']) : $totals->weight;
		$item_description = ! empty($form['item_description']) ? sanitize_textarea_field($form['item_description']) : $totals->item_description;
		$item_quantity    = ! empty($form['item_quantity']) ? intval($form['item_quantity']) : max(1, $totals->quantity);
		$amount_to_collect = isset($form['amount_to_collect']) ? floatval($form['amount_to_collect']) : ($order->has_status('paid') ? 0 : round((float) $order->get_total()));

		// Prepare args for Pathao API facade/service
		$args = array(
			'store_id'           => intval($store_id),
			'merchant_order_id'  => (string) $order_id,
			'recipient_name'     => $recipient_name,
			'recipient_phone'    => $recipient_phone,
			'recipient_address'  => $recipient_address,
			'delivery_type'      => apply_filters('sdevs_pathao_default_delivery_type', 48),
			'item_type'          => 2,
			'item_quantity'      => $item_quantity,
			'item_weight'        => $item_weight,
			'item_description'   => $item_description,
			'amount_to_collect'  => $amount_to_collect,
		);

		// Use PathaoAPI facade if available (preferred)
		if (method_exists(PathaoAPI::class, 'send_order')) {
			// PathaoAPI::send_order should handle token and request JSON encoding
			$res = PathaoAPI::send_order($order_id, $args);
		} else {
			// Fallback: call the service directly (mirror of PathaoApiService::send_order)
			$service = new \SpringDevs\Pathao\Services\PathaoApiService();
			$res = $service->send_order($order_id, $args);
		}

		// If error object returned by your has_errors wrapper -> return as AJAX error
		if (is_object($res) && isset($res->success) && ! $res->success) {
			 
			if (defined('DOING_AJAX') && DOING_AJAX) {
				wp_send_json_error(array('message' => $res->messages ?? 'Pathao API error', 'details' => $res));
			}
			return false;
		}

		// Always work with stdClass
		$result = json_decode(json_encode($res));


		// If success, save consignment id and status to order meta and fire your hook
		$consignment_id = null;
		$order_status   = null;
		$delivery_fee   = null;

		if (isset($result->data->consignment_id)) {
            $consignment_id = sanitize_text_field($result->data->consignment_id);
       } elseif (is_object($res) && isset($res->data->consignment_id)) {
			$consignment_id = sanitize_text_field($res->data->consignment_id);
		}

		if (isset($result->data->order_status)) { 

			$order_status = sanitize_text_field($result['data']['order_status']);
		} elseif (is_object($res) && isset($res->data->order_status)) {
			$order_status = sanitize_text_field($res->data->order_status);
		}

		if (isset($result->data->delivery_fee))  {


			$delivery_fee = $result['data']['delivery_fee'];
		} elseif (is_object($res) && isset($res->data->delivery_fee)) {
			$delivery_fee = $res->data->delivery_fee;
		}

		if ($consignment_id) {
			$order->update_meta_data('_pathao_consignment_id', $consignment_id);
		}

		if ($order_status) {
			$order->update_meta_data('_pathao_order_status', $order_status);
		}

		if (! is_null($delivery_fee)) {
			$order->update_meta_data('_pathao_delivery_fee', $delivery_fee);
		}

		$order->save();

		// Log and trigger hook for DB logging
		$hook_payload = (object) array(
			'merchant_order_id' => (string) $order_id,
			'consignment_id'    => $consignment_id,
			'order_status'      => $order_status,
			'delivery_fee'      => $delivery_fee,
		);

		do_action('pathao_order_created', $hook_payload);

		// Return to caller
		if (defined('DOING_AJAX') && DOING_AJAX) {
			wp_send_json_success(array('message' => 'Order sent to Pathao', 'raw' => $result));
		}

		return $result;
	}
}
