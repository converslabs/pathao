<?php

namespace SpringDevs\Pathao;

use SpringDevs\Pathao\Services\PathaoApiService;

if (!defined('ABSPATH')) exit;

class Ajax
{
    private static $instance = null;

    public static function init()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // AJAX actions
        $actions = [
            'setup_pathao',
            'get_cities',
            'get_city_zones',
            'get_zone_areas',
            'send_order_to_pathao',
            'sdevs_send_order',
        ];

        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", [$this, $action]);
            add_action("wp_ajax_nopriv_{$action}", [$this, $action]);
        }
    }

    /**
     * Send order to Pathao
     */


    public function send_order(int $order_id, $args = array())
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            $err = new \stdClass();
            $err->success = false;
            $err->messages = ['Order not found'];
            return $err;
        }

        // Recipient info
        $recipient_name = $order->get_formatted_shipping_full_name() !== ' '
        ? $order->get_formatted_shipping_full_name() : $order->get_formatted_billing_full_name();

        $recipient_phone =
            $order->get_shipping_phone() !== ''
            ? $order->get_shipping_phone()
            : $order->get_billing_phone();

        // Normalize BD numbers
        if (substr($recipient_phone, 0, 3) === '+88') {
            $recipient_phone = str_replace('+88', '', $recipient_phone);
        }

        $recipient_address =
            $order->get_formatted_shipping_address() !== ''
            ? $order->get_formatted_shipping_address()
            : $order->get_formatted_billing_address();

        // Items
        $item_weight = sdevs_pathao_get_totals_from_items($order);

        // Build body
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

        $res = $this->request(
            'wp_remote_post',
            '/aladdin/api/v1/orders',
            array('body' => json_encode($body))
        );


        error_log("send_order Response: " . print_r($res, true));

        $has_errors = $this->has_errors($res);
        if ($has_errors) {
            return $has_errors;
        }

        $decoded_body = wp_remote_retrieve_body($res);
        $res_data = json_decode($decoded_body)->data;

        $data = new \stdClass();
        $data->success = true;
        $data->data = (object) array(
            'consignment_id'    => $res_data->consignment_id,
            'merchant_order_id' => $res_data->merchant_order_id,
            'order_status'      => $res_data->order_status,
            'delivery_fee'      => $res_data->delivery_fee,);

        return $data;
    }




    public function send_order_to_pathao()
    {
        error_log('[Pathao Debug] AJAX: send_order_to_pathao fired');

        // Verify nonce from JS (YOUR NONCE NAME MUST MATCH)
        check_ajax_referer('pathao_send_order', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);

        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid Order ID']);
        }

        // Prepare the data for Pathao API
        $order_data = $this->prepare_order_data($order_id);

        // Call your API function
        $res = pt_hms_create_new_order($order_data);

        error_log('[Pathao Debug] Pathao API Response: ' . print_r($res, true));

        // Look for consignment_id
        if (!empty($res['data']['consignment_id'])) {

            $cid = sanitize_text_field($res['data']['consignment_id']);

            update_post_meta($order_id, '_pathao_consignment_id', $cid);

            // Fire the hook your Order.php listens to
            do_action('pathao_order_created', (object) $res['data']);

            wp_send_json_success([
                'message'        => 'Order sent successfully',
                'consignment_id' => $cid
            ]);
        }

        wp_send_json_error(['message' => 'No consignment ID returned']);
    }



    /**
     * Get cities
     */
    public function get_cities()
    {


        $order_id = sanitize_text_field(wp_unslash($_POST['order_id'] ?? 0));
        $cities   = PathaoAPI::get_cities();

        wp_send_json([
            'cities' => $cities->data ?? [],
            'value'  => apply_filters('pathao_selected_order_city_value', null, $order_id),
        ]);
    }

    /**
     * Setup credentials
     */
    // In your Ajax class
    public function sdevs_send_order()
    {
        check_ajax_referer('pathao_send_order', 'nonce');

        $order_id = intval($_POST['order_id'] ?? 0);
        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid Order ID']);
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        // Recipient info
        $recipient_name = trim($order->get_formatted_shipping_full_name()) ?: trim($order->get_formatted_billing_full_name());
        $recipient_phone = trim($order->get_shipping_phone()) ?: trim($order->get_billing_phone());
        $recipient_address = trim($order->get_formatted_shipping_address()) ?: trim($order->get_formatted_billing_address());

        if (!$recipient_name || !$recipient_phone || !$recipient_address) {
            wp_send_json_error(['message' => 'Recipient information is incomplete.']);
        }

        // Format phone (remove +88)
        $recipient_phone = str_starts_with($recipient_phone, '+88') ? substr($recipient_phone, 3) : $recipient_phone;

        // Get items data
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = [
                'name'     => $product ? $product->get_name() : $item->get_name(),
                'quantity' => $item->get_quantity(),
                'weight'   => $product ? (float) $product->get_weight() : 0,
            ];
        }

        if (empty($items)) {
            wp_send_json_error(['message' => 'No items in order']);
        }

        // Build Pathao API request body
        $body = [
            'store_id'          => sdevs_pathao_store_id(), // your function to get store ID
            'merchant_order_id' => $order_id,
            'recipient_name'    => $recipient_name,
            'recipient_phone'   => $recipient_phone,
            'recipient_address' => $recipient_address,
            'delivery_type'     => apply_filters('sdevs_pathao_default_delivery_type', 48),
            'item_type'         => 2,
            'item_description'  => implode(', ', array_column($items, 'name')),
            'item_quantity'     => array_sum(array_column($items, 'quantity')),
            'item_weight'       => array_sum(array_column($items, 'weight')),
            'amount_to_collect' => $order->has_status('paid') ? 0 : round((float) $order->get_total()),
        ];

        // Send order to Pathao
        $service = new \SpringDevs\Pathao\Services\PathaoApiService();
        $res = $service->send_order($order_id, $body);

        if ($res->success) {
            update_post_meta($order_id, '_pathao_consignment_id', $res->data->consignment_id);
            wp_send_json_success(['consignment_id' => $res->data->consignment_id]);
        } else {
            wp_send_json_error(['message' => $res->messages ?? ['Unknown error']]);
        }
    }


    /**
     * Get zones
     */
    public function get_city_zones()
    {

        //error_log("[Pathao Debug] AJAX: get_city_zones");

        if (
            !isset($_POST['nonce']) ||
            !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'pathao_send_order')
        ) {
            wp_send_json(['success' => false, 'message' => 'Invalid nonce']);
        }

        $city     = sanitize_text_field($_POST['city']);
        $order_id = sanitize_text_field($_POST['order_id']);

        $zones = PathaoAPI::get_zones($city);
        wp_send_json([
            'zones' => $zones->success ? $zones->data : [],
            'value' => apply_filters('pathao_selected_order_zone_value', null, $order_id),
        ]);
    }

    /**
     * Get areas
     */
    public function get_zone_areas()
    {

        //error_log("[Pathao Debug] AJAX: get_zone_areas");

        if (
            !isset($_POST['nonce']) ||
            !wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'pathao_send_order')
        ) {
            wp_send_json(['success' => false, 'message' => 'Invalid nonce']);
        }

        $zone     = sanitize_text_field($_POST['zone']);
        $order_id = sanitize_text_field($_POST['order_id']);

        $areas = PathaoAPI::get_areas($zone);

        wp_send_json([
            'areas' => $areas->success ? $areas->data : [],
            'value' => apply_filters('pathao_selected_order_area_value', null, $order_id),
        ]);
    }
}
