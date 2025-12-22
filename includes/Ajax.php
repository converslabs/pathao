<?php
        namespace SpringDevs\Pathao;

        use SpringDevs\Pathao\Facades\PathaoAPI;
        use SpringDevs\Pathao\Services\PathaoApiService;
        use WC_Order;

        if (!defined('ABSPATH')) {
            exit;
        }
        

        class Ajax {

            private static $instance = null;

            public static function init() {
                if (self::$instance === null) {
                    self::$instance = new self();
                }
                return self::$instance;
            }

            private function __construct() {

                add_action('wp_ajax_send_bulk_orders_to_pathao', [$this, 'send_bulk_orders_to_pathao']);
                add_action('wp_ajax_send_order_to_pathao', [$this, 'send_order_to_pathao']);
                add_action('wp_ajax_get_wc_order_info', [$this, 'get_wc_order_info']);

                add_action('wp_ajax_get_cities', [$this, 'get_cities']);
                add_action('wp_ajax_get_city_zones', [$this, 'get_city_zones']);

                add_action('wp_ajax_get_zone_areas', [$this, 'get_zone_areas']);

                add_action('wp_ajax_pathao_sync_order_status', [$this, 'sync_order_status']);

                add_action('wp_ajax_pathao_price_calculation', [$this, 'price_calculation']);



            }

            /* -------------------------------------------------------------------------
            * BULK ORDER HANDLER (Pathao does NOT support true bulk)
            * ---------------------------------------------------------------------- */
            public function send_bulk_orders_to_pathao() {

                if (!current_user_can('manage_woocommerce')) {
                    wp_send_json_error(['message' => 'Permission denied'], 403);
                }

                check_ajax_referer('pathao_nonce', 'nonce');

                if (empty($_POST['order_ids']) || !is_array($_POST['order_ids'])) {
                    wp_send_json_error(['message' => 'No orders selected'], 400);
                }

                error_log('[Pathao Bulk] Order IDs: ' . print_r($_POST['order_ids'], true));

                $api = new PathaoApiService();
                $sent = [];
                $failed = [];

                foreach ($_POST['order_ids'] as $order_id) {

                    $order = wc_get_order((int)$order_id);
                    if (!$order) {
                        $failed[$order_id] = 'Order not found';
                        continue;
                    }

                    if ($order->get_meta('_pathao_consignment_id')) {
                        continue; // already sent
                    }

                    $payload = [
                        'store_id'            => (int) get_option('pathao_store_id'),
                        'merchant_order_id'   => (string) $order->get_id(),
                        'recipient_name'      => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
                        'recipient_phone'     => str_replace('+88', '', $order->get_billing_phone()),
                        'recipient_address'   => $order->get_shipping_address_1(),
                        'delivery_type'       => 48,
                        'item_type'           => 2,
                        'item_quantity'       => max(1, $order->get_item_count()),
                        'item_weight'         => 0.5,
                        'item_description'    => 'WooCommerce Order #' . $order->get_id(),
                        'amount_to_collect'   => $order->is_paid() ? 0 : (float) $order->get_total(),
                    ];

                    $res = $api->send_order($order->get_id(), $payload);

                    if (isset($res->success) && $res->success) {

                        if (!empty($res->data->consignment_id)) {
                            $order->update_meta_data('_pathao_consignment_id', $res->data->consignment_id);
                        }

                        if (!empty($res->data->order_status)) {
                            $order->update_meta_data('_pathao_order_status', $res->data->order_status);
                        }

                        $order->save();
                        $sent[] = $order->get_id();

                    } else {
                        $failed[$order->get_id()] = $res->message ?? 'API failed';
                    }
                }

                wp_send_json_success([
                    'sent_orders'   => $sent,
                    'failed_orders' => $failed,
                ]);
            }


            public function get_wc_order_info() {

                if (!current_user_can('manage_woocommerce')) {
                    wp_send_json_error(['message' => 'Permission denied']);
                }

                $order_id = absint($_POST['order_id'] ?? 0);
                if (!$order_id) {
                    wp_send_json_error(['message' => 'Invalid order id']);
                }

                $order = wc_get_order($order_id);
                if (!$order) {
                    wp_send_json_error(['message' => 'Order not found']);
                }

                $items = [];
                foreach ($order->get_items() as $item) {
                    $items[] = $item->get_name() . ' x ' . $item->get_quantity();
                }

                wp_send_json([
                    'name'           => $order->get_formatted_shipping_full_name(),
                    'phone'          => $order->get_billing_phone(),
                    'address'        => $order->get_shipping_address_1(),
                    'items'          => implode("\n", $items),
                    'weight'         => 0.5,
                    'quantity'       => $order->get_item_count(),
                    'total'          => $order->get_total(),
                    'payment_status' => $order->is_paid() ? 'Paid' : 'Unpaid',
                    'cod_amount'     => $order->is_paid() ? 0 : $order->get_total(),
                    'store_name'     => get_option('pathao_store_name'),
                ]);
            } 


            /* -------------------------------------------------------------------------
            * SINGLE ORDER SEND
            * ---------------------------------------------------------------------- */
            public function send_order_to_pathao() {

                check_ajax_referer('pathao_nonce', 'nonce');

                if (!current_user_can('manage_woocommerce')) {
                    wp_send_json_error(['message' => 'Permission denied'], 403);
                }

                $order_id = absint($_POST['order_id'] ?? 0);
                if (!$order_id) {
                    wp_send_json_error(['message' => 'Invalid order ID']);
                }

                $order = wc_get_order($order_id);
                if (!$order) {
                    wp_send_json_error(['message' => 'Order not found']);
                }

                $api = new PathaoApiService();

                $payload = [
                    'store_id'          => (int) get_option('pathao_store_id'),
                    'merchant_order_id' => (string) $order_id,
                    'recipient_name'    => trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),
                    'recipient_phone'   => str_replace('+88', '', $order->get_billing_phone()),
                    'recipient_address' => $order->get_shipping_address_1(),
                    'delivery_type'     => 48,
                    'item_type'         => 2,
                    'item_quantity'     => max(1, $order->get_item_count()),
                    'item_weight'       => 0.5,
                    'item_description'  => 'WooCommerce Order #' . $order_id,
                    'amount_to_collect' => $order->is_paid() ? 0 : (float) $order->get_total(),
                ];

                $res = $api->send_order($order_id);

                if (!$res || empty($res->success)) {
                    wp_send_json_error(['message' => 'Pathao API failed', 'raw' => $res]);
                }

                wp_send_json_success(['message' => 'Order sent to Pathao']);
            }

            /* -------------------------------------------------------------------------
            * GET CITIES
            * ---------------------------------------------------------------------- */
            public function get_cities() {
                check_ajax_referer('pathao_nonce', 'nonce');

                $res = PathaoAPI::get_cities();

                wp_send_json([
                    'cities' => $res->success ? $res->data : []
                ]);
            }

            public function get_city_zones() {
                check_ajax_referer('pathao_nonce', 'nonce');

                $city_id = absint($_POST['city'] ?? 0);
                if (!$city_id) {
                    wp_send_json_error(['message' => 'Invalid city id']);
                }

                $res = PathaoAPI::get_city_zones($city_id);

                wp_send_json([
                    'zones' => ($res->success ?? false) ? $res->data : []
                ]);
            }
    

            public function get_zone_areas() {
                check_ajax_referer('pathao_nonce', 'nonce');

                $zone = sanitize_text_field($_POST['zone'] ?? '');
                $res = PathaoAPI::get_areas($zone);

                wp_send_json([
                    'areas' => $res->success ? $res->data : []
                ]);
            }
            
             public function price_calculation()
            {
                check_ajax_referer('pathao_nonce', 'nonce');

                $api = new PathaoApiService();

                $args = [
                    'store_id' => (int) ($settings['store'] ?? 0),
                    'item_type' => absint($_POST['item_type'] ?? 2),
                    'delivery_type' => absint($_POST['delivery_type'] ?? 48),
                    'item_weight' => (float) ($_POST['item_weight'] ?? 0.5),
                    'recipient_city' => absint($_POST['city_id'] ?? 0),
                    'recipient_zone' => absint($_POST['zone_id'] ?? 0),
                ];

                error_log('[AJAX Price Args] ' . wp_json_encode($args));

                $res = $api->price_calculation($args);

                if (empty($res->success)) {
                    error_log('[AJAX Price Failed] ' . print_r($res, true));
                    wp_send_json_error(['message' => 'Price calculation failed']);
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

                $api = new \SpringDevs\Pathao\Services\PathaoApiService();
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
