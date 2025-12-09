<?php

namespace SpringDevs\Pathao\Admin;

use WC_Order;

class Order
{
	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
		add_action('pathao_order_created', array($this, 'store_log_after_creation'));

		add_filter('manage_edit-shop_order_columns', array($this, 'add_custom_columns'));
		add_filter('woocommerce_shop_order_list_table_columns', array($this, 'add_custom_columns'));
		add_action('manage_shop_order_posts_custom_column', array($this, 'add_custom_columns_data'), 10, 2);
		add_action('init', array($this, 'load_hpos_hooks'));
	}

	public function load_hpos_hooks()
	{
		add_action('manage_' . \wc_get_page_screen_id('shop_order') . '_custom_column', array($this, 'add_custom_columns_data'), 10, 2);
	}

	public function add_custom_columns($columns)
	{
		$columns['sdevs_pathao_order_column'] = __('xx Pathao', 'integration-of-pathao-for-woocommerce');
		return $columns;
	}

	public function add_custom_columns_data($column, $order)
	{
		// Ensure $order is a WC_Order object regardless of HPOS status
		if (!($order instanceof WC_Order)) {
			$order_id = absint($order);
			$order = wc_get_order($order_id);
			if (!$order) {
				return; // Exit if order object can't be retrieved
			}
		} else {
			$order_id = $order->get_id();
		}

		// Use a switch or if statement to check specifically for *your* column
		if ($column === 'sdevs_pathao_order_column') {

			// Retrieve the meta data
			// Use get_meta() which works for both HPOS and classic CPT
			$consignment_id = $order->get_meta('_pathao_consignment_id', true);
			$status         = $order->get_meta('_pathao_order_status', true); // Pass 'true' for single     value retrieval

			// Debugging (optional, keep it if you need it)
			error_log("[Pathao Debug] DISPLAY column ={$column}, order_id={$order_id}, consignment_id={$consignment_id}, status={$status}");

			// Output the data in the table cell
			if (!empty($consignment_id)) {
				// Add your HTML output here
				echo esc_html($consignment_id);
				if (!empty($status)) {
					echo ' <br><small>(' . esc_html($status) . ')</small>';
				}
			} else {
				// Output something if the ID is missing
				echo 'N/A';
			}
		}

		// The function implicitly returns nothing for other columns
	}




	// Step-by-step debug in store log
	public function store_log_after_creation($res)
	{
		global $wpdb;
		$log_table = $wpdb->prefix . 'pathao_logs';

		error_log('[Pathao Debug] store_log_after_creation called with: ' . print_r($res, true));

		$order_id = isset($res->merchant_order_id) ? (int) $res->merchant_order_id : 0;
		$consignment_id = isset($res->consignment_id) ? sanitize_text_field($res->consignment_id) : '';
		$order_status = isset($res->order_status) ? sanitize_text_field($res->order_status) : '';

		error_log("[Pathao Debug] Parsed values - order_id: $order_id, consignment_id: $consignment_id, order_status: $order_status");

		$wpdb->insert(
			$log_table,
			array(
				'order_id'          => $order_id,
				'consignment_id'    => $consignment_id,
				'order_status'      => $order_status,
				'order_status_slug' => $order_status,
				'reason'            => 'Pathao Order created & it\'s pending.',
				'updated_at'        => current_time('mysql'),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ($wpdb->last_error) {
			error_log('[Pathao Debug] DB Insert Error: ' . $wpdb->last_error);
		} else {
			error_log('[Pathao Debug] Log inserted successfully.');
		}
	}

	public function register_meta_boxes()
	{
		$screen = sdevs_wc_order_hpos_enabled() ? 'shop_order' : 'shop_order';

		$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

		if ('edit' === $action) {
			add_meta_box(
				'pathao_order_wc',
				__('Pathao Shipping', 'integration-of-pathao-for-woocommerce'),
				array($this, 'pathao_shipping'),
				$screen,
				'side',
				'default'
			);
		}
	}

	public function pathao_shipping()
	{
		$nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
		if (! wp_verify_nonce($nonce, 'pathao_shipping_nonce')) {
			wp_die(esc_html__('Security check failed.', 'integration-of-pathao-for-woocommerce'));
		}

		$order_id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : get_the_ID();
		$order = wc_get_order($order_id);
		if (! $order) return;

		$consignment_id = $order->get_meta('_pathao_consignment_id');
		$status         = $order->get_meta('_pathao_order_status');

		error_log("[Pathao Debug] pathao_shipping - consignment_id: $consignment_id, status: $status");

		if ($consignment_id && ! in_array($status, ['Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed'], true)) {
			$this->display_pathao_details($order);
		} elseif ($consignment_id) {
			$this->display_pathao_details($order);
			$this->pathao_shipping_form($order);
		} else {
			$this->pathao_shipping_form($order);
		}
	}

	public function display_pathao_details(WC_Order $order)
	{
		$consignment_id = $order->get_meta('_pathao_consignment_id');
		$delivery_fee   = $order->get_meta('_pathao_delivery_fee');
		$order_status   = $order->get_meta('_pathao_order_status');

		error_log("[Pathao Debug] display_pathao_details - consignment_id: $consignment_id, status: $order_status");

		include 'views/pathao-shipping-details.php';
	}

	public function pathao_shipping_form(WC_Order $order)
	{
		$order_id = $order->get_id();
		wp_localize_script(
			'pathao_admin_script',
			'pathao_admin_obj',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'order_id' => $order_id,
			)
		);
		wp_enqueue_style('pathao_toast_styles');
		wp_enqueue_script('pathao_toast_script');
		wp_enqueue_script('pathao_admin_script');

		$amount = is_sdevs_pathao_pro_activated() && $order->has_status(substr(sdevs_pathao_settings('paid_order_status', 'wc-paid'), 3)) ? 0 : $order->get_total();
		$all_totals       = sdevs_pathao_get_totals_from_items($order);
		$total_weight     = $all_totals->weight;
		$item_description = $all_totals->item_description;
		$status           = $order->get_meta('_pathao_order_status');

		error_log("[Pathao Debug] pathao_shipping_form - amount: $amount, weight: $total_weight, status: $status");

		include 'views/pathao-shipping.php';
		wp_nonce_field('pathao_order_action', '_pathao_order_nonce');
	}
}
