<?php

namespace ConversLabs\Pathao;

use ConversLabs\Pathao\Facades\PathaoAPI;
use ConversLabs\Pathao\Services\PathaoApiService;
use WC_Order;

if (! defined('ABSPATH')) {
    exit;
}

class Ajax
{

    private static $instance = null;

    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {

        add_action('wp_ajax_send_order_to_pathao', [$this, 'send_order_to_pathao']);
        add_action('wp_ajax_get_wc_order_info', [$this, 'get_wc_order_info']);

        add_action('wp_ajax_get_cities', [$this, 'get_cities']);
        add_action('wp_ajax_get_city_zones', [$this, 'get_city_zones']);

        add_action('wp_ajax_get_zone_areas', [$this, 'get_zone_areas']);

        add_action('wp_ajax_pathao_sync_order_status', [$this, 'sync_order_status']);

        add_action('wp_ajax_pathao_price_calculation', [$this, 'price_calculation']);
 
        add_action('wp_ajax_pathao_setup_generate_token', [$this, 'handle_setup_generate_token']);  

    }

    /**
     * Handle admin "Pathao Setup" generate token form.
     *
     * - Saves client_id, client_secret and sandbox mode.
     * - Calls PathaoAPI::generate_tokens() with username/password.
     */
    public function handle_setup_generate_token()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'integration-of-pathao-for-woocommerce')], 403);
        }

        // Verify nonce from setup.php: wp_nonce_field( '_pathao_setup_nonce', '_wp_setup_nonce' );
        if (empty($_POST['_wp_setup_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wp_setup_nonce'])), '_pathao_setup_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'integration-of-pathao-for-woocommerce')], 400);
        }

        $client_id     = isset($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
        $client_secret = isset($_POST['client_secret']) ? sanitize_text_field(wp_unslash($_POST['client_secret'])) : '';
        $username      = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $password      = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';
        $sandbox_mode  = ! empty($_POST['sandbox_mode']) ? 1 : 0;

        if (! $client_id || ! $client_secret || ! $username || ! $password) {
            wp_send_json_error(['message' => __('All fields are required', 'integration-of-pathao-for-woocommerce')]);
        }

        // Persist credentials & sandbox flag.
        update_option('pathao_client_id', $client_id);
        update_option('pathao_client_secret', $client_secret);
        update_option('pathao_sandbox_mode', $sandbox_mode);

        // Generate tokens using the service (password grant).
        $res = PathaoAPI::generate_tokens(
            [
                'username'   => $username,
                'password'   => $password,
                'grant_type' => 'password',
            ]
        );

        if (empty($res->success)) {
            $message = ! empty($res->messages[0]) ? $res->messages[0] : __('Token generation failed', 'integration-of-pathao-for-woocommerce');

            wp_send_json_error(
                [
                    'message' => $message,
                ]
            );
        }

        wp_send_json_success(
            [
                'message'       => __('Token generated successfully', 'integration-of-pathao-for-woocommerce'),
                'access_token'  => $res->data->access_token ?? '',
                'refresh_token' => $res->data->refresh_token ?? '',
                'sandbox_mode'  => (int) $sandbox_mode,
            ]
        );
    }

   



    public function get_wc_order_info() {
        check_ajax_referer( 'pathao_nonce', 'nonce' );

        $order_id = absint( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => 'Invalid order ID' ] );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( [ 'message' => 'Order not found' ] );
        }

        // Payment status
        $payment_status = $order->is_paid() ? 'Paid' : 'Unpaid';

        // COD logic
        $cod_amount = $order->is_paid() ? 0 : (float) $order->get_total();

        // Order items
        $items = [];
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $items[] = [
                'name'  => $item->get_name(),
                'image' => $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '',
            ];
        }

        wp_send_json_success( [
            'order_number'   => $order->get_order_number(),
            'total'          => number_format((float) $order->get_total(), 2),
            'payment_status' => $payment_status,
            'cod_amount'     => $cod_amount,
            'name'           => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'phone'          => $order->get_billing_phone(),
            'address'        => $order->get_billing_address_1(),
            'quantity'       => $order->get_item_count(),
            'weight'         => 0.5, // default
            'items'          => $items,
        ] );
    }


    /* -------------------------------------------------------------------------
            * SINGLE ORDER SEND
            * ---------------------------------------------------------------------- */
    public function send_order_to_pathao()
    {

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        check_ajax_referer('pathao_nonce', 'nonce');

        parse_str($_POST['form'] ?? '', $form);

        $order_id = absint($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $settings = get_option('woocommerce_pathao_settings');
        $store_id = absint($settings['store'] ?? 0);
        if (!$store_id) {
            wp_send_json_error(['message' => 'Pathao store not configured']);
        }

        foreach (['recipient_city', 'recipient_zone', 'recipient_area'] as $key) {
            if (empty($form[$key])) {
                wp_send_json_error(['message' => ucfirst(str_replace('_', ' ', $key)) . ' is required']);
            }
        }

        $phone = sanitize_text_field($form['recipient_phone'] ?? $order->get_billing_phone());
        if (strlen($phone) !== 11) {
            wp_send_json_error(['message' => 'Valid phone required']);
        }

        $item_weight   = max(0.5, (float) ($form['item_weight'] ?? 0.5));
        $item_quantity = max(1, absint($form['item_quantity'] ?? $order->get_item_count()));

        $amount_to_collect = $order->is_paid() ? 0 : (float) $order->get_total();

        /* SAVE LOCATION FOR BULK USE */
        $order->update_meta_data('_pathao_city', absint($form['recipient_city']));
        $order->update_meta_data('_pathao_zone', absint($form['recipient_zone']));
        $order->update_meta_data('_pathao_area', absint($form['recipient_area']));
        $order->update_meta_data('_pathao_weight', $item_weight);
        $order->save();

        $payload = [
            'store_id'           => $store_id,
            'merchant_order_id'  => (string) $order_id,
            'recipient_name'     => sanitize_text_field($form['recipient_name'] ?: $order->get_formatted_shipping_full_name()),
            'recipient_phone'    => $phone,
            'recipient_address'  => sanitize_textarea_field($form['recipient_address'] ?: $order->get_shipping_address_1()),
            'recipient_city'     => absint($form['recipient_city']),
            'recipient_zone'     => absint($form['recipient_zone']),
            'recipient_area'     => absint($form['recipient_area']),
            'delivery_type'      => absint($form['delivery_type'] ?? 48),
            'item_type'          => absint($form['item_type'] ?? 2),
            'item_weight'        => $item_weight,
            'item_quantity'      => $item_quantity,
            'amount_to_collect'  => $amount_to_collect,
            'special_instruction' => sanitize_textarea_field($form['special_instruction'] ?? ''),
            'item_description'   => 'WooCommerce Order #' . $order_id,
        ];

        $api = new PathaoApiService();
        $res = $api->send_order_with_payload($payload);

        if (!empty($res->success) && !empty($res->data->consignment_id)) {
            $order->update_meta_data('_pathao_consignment_id', $res->data->consignment_id);
            $order->update_meta_data('_pathao_order_status', $res->data->order_status ?? 'Pending');
            $order->save();

            wp_send_json_success(['message' => 'Order sent to Pathao']);
        }

        wp_send_json_error(['message' => $res->messages[0] ?? 'Pathao failed']);
    }




    /* -------------------------------------------------------------------------
            * GET CITIES
            * ---------------------------------------------------------------------- */
    public function get_cities()
    {
        check_ajax_referer('pathao_nonce', 'nonce');

        $res = PathaoAPI::get_cities();

        if (! empty($res->success) && ! empty($res->data)) {
            wp_send_json_success([
                'cities' => $res->data
            ]);
        }

        wp_send_json_error([
            'message' => ! empty($res->messages) ? implode(', ', $res->messages) : 'Failed to load cities',
            'cities' => []
        ]);
    }

    public function get_city_zones()
    {
        check_ajax_referer('pathao_nonce', 'nonce');

        $city_id = absint($_POST['city'] ?? 0);
        if (! $city_id) {
            wp_send_json_error(['message' => 'Invalid city id']);
        }

        $res = PathaoAPI::get_city_zones($city_id);

        if (! empty($res->success) && ! empty($res->data)) {
            wp_send_json_success([
                'zones' => $res->data
            ]);
        }

        // Log error for debugging
        error_log('[Pathao Zones AJAX] Failed: ' . print_r($res, true));

        wp_send_json_error([
            'message' => ! empty($res->messages) ? implode(', ', $res->messages) : 'Failed to load zones',
            'zones' => []
        ]);
    }


    public function get_zone_areas()
    {
        check_ajax_referer('pathao_nonce', 'nonce');

        $zone_id = absint($_POST['zone'] ?? 0);
        if (! $zone_id) {
            wp_send_json_error(['message' => 'Invalid zone id']);
        }

        $res = PathaoAPI::get_areas($zone_id);

        if (! empty($res->success) && ! empty($res->data)) {
            wp_send_json_success([
                'areas' => $res->data
            ]);
        }

        // Log error for debugging
        error_log('[Pathao Areas AJAX] Failed: ' . print_r($res, true));

        wp_send_json_error([
            'message' => ! empty($res->messages) ? implode(', ', $res->messages) : 'Failed to load areas',
            'areas' => []
        ]);
    }

    public function price_calculation()
    {
        check_ajax_referer('pathao_nonce', 'nonce');

        $api = new \ConversLabs\Pathao\Services\PathaoApiService();

        // WooCommerce Pathao settings
        $settings = get_option('woocommerce_pathao_settings');
        $store_id = (int) ($settings['store'] ?? 0);

        $args = [
            'store_id'        => $store_id,
            'item_type'       => absint($_POST['item_type'] ?? 2),
            'delivery_type'   => absint($_POST['delivery_type'] ?? 48),
            'item_weight'     => (float) ($_POST['item_weight'] ?? 0.5),
            'recipient_city'  => absint($_POST['recipient_city'] ?? 0),
            'recipient_zone'  => absint($_POST['recipient_zone'] ?? 0),
            'recipient_area' => absint($_POST['recipient_area'] ?? 0),
        ];

        // error_log('[AJAX Price Args] ' . wp_json_encode($args));

        // Basic validation
        if (!$args['store_id']) {
            wp_send_json_error(['message' => 'Store ID missing']);
        }

        if (!$args['recipient_city'] || !$args['recipient_zone']) {
            wp_send_json_error(['message' => 'City and zone are required']);
        }

        $res = $api->price_calculation($args);

        if (empty($res->success)) {
            error_log('[AJAX Price Failed] ' . wp_json_encode($res));
            wp_send_json_error([
                'message' => $res->messages[0] ?? 'Price calculation failed'
            ]);
        }

        wp_send_json_success($res->data);
    }


    public function sync_order_status()
    {
        check_ajax_referer('pathao_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        $orders = wc_get_orders([
            'limit'     => 20,
            'meta_key'  => '_pathao_consignment_id',
            'meta_compare' => 'EXISTS',
        ]);

        if (empty($orders)) {
            wp_send_json_success(['message' => 'No Pathao orders to sync']);
        }

        $api = new \ConversLabs\Pathao\Services\PathaoApiService();
        $updated = 0;

        foreach ($orders as $order) {
            $consignment_id = $order->get_meta('_pathao_consignment_id');

            if (!$consignment_id) {
                continue;
            }

            $res = $api->get_order_info($consignment_id);

            if (!empty($res->success) && !empty($res->data->order_status_slug)) {
                $order->update_meta_data('_pathao_order_status', $res->data->order_status_slug);
                $order->save();
                $updated++;
            }
        }

        wp_send_json_success([
            'message' => sprintf('%d orders synced successfully', $updated)
        ]);
    }
}