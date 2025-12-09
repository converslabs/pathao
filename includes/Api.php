<?php
namespace SpringDevs\Pathao;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Api {

    private $logger;

    public function __construct() {
        $this->logger = wc_get_logger();
        add_action('rest_api_init', array($this, 'register_api'));
    }

    public function register_api() {
        $this->logger->info('Pathao API initialized.');
        // error_log('[Pathao Debug] API initialized.');

        register_rest_route(
            'api/v1',
            'pathao-status-endpoint',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'pathao_status_changed'),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function pathao_status_changed(WP_REST_Request $request) {
        error_log('[Pathao Debug] Webhook hit at: ' . current_time('mysql'));

        // Log headers
        $signature = $request->get_header('X-PATHAO-Signature');
        $client_secret = get_option('pathao_client_secret');
        error_log("[Pathao Debug] Received signature: $signature");
        error_log("[Pathao Debug] Client secret: " . ($client_secret ? 'set' : 'not set'));

        if (! $client_secret) {
            error_log('[Pathao Debug] Client secret not configured.');
            return new WP_REST_Response(['success' => false, 'message' => 'Server misconfiguration'], 500);
        }

        // Raw body and signature
        $body = $request->get_body();
        $computed_signature = hash_hmac('sha256', $body, $client_secret);
        error_log("[Pathao Debug] Raw body: $body");
        error_log("[Pathao Debug] Computed signature: $computed_signature");

        if ($signature !== $computed_signature) {
            error_log('[Pathao Debug] Invalid signature detected.');
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        // Parse params
        $params = $request->get_params();
        $consignment_id = sanitize_text_field($request->get_param('consignment_id'));
        $order_id       = absint($request->get_param('merchant_order_id'));
        $status         = sanitize_text_field($request->get_param('order_status_slug'));

        error_log("[Pathao Debug] Parsed webhook params - order_id: $order_id, consignment_id: $consignment_id, status: $status");
        error_log('[Pathao Debug] Params array: ' . print_r($params, true));
		
        // Get order
        $order = wc_get_order($order_id);
		error_log("ORDER", print_r($order, true));
        if (! $order) {
            error_log("[Pathao Debug] Order not found for ID: $order_id");
            return new WP_REST_Response(['success' => false, 'message' => 'Order not found'], 404);
        }
        // Log order meta
        $order_consignment_id = $order->get_meta('_pathao_consignment_id', true);
        $order_status_meta = $order->get_meta('_pathao_order_status', true);
        // error_log("[Pathao Debug] Existing order meta - consignment_id: $order_consignment_id, status: $order_status_meta");

        // Validate consignment ID
        if ($consignment_id !== $order_consignment_id) {
            error_log("[Pathao Debug] Consignment ID mismatch. Webhook: $consignment_id, DB: $order_consignment_id");
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid consignment ID'], 400);
        }

        // Update order status mapping
        $status_map = [
            'Delivered'        => 'completed',
            'Pickup_Failed'    => 'failed',
            'Pickup_Cancelled' => 'failed',
            'Delivery_Failed'  => 'failed',
        ];

        if (! is_sdevs_pathao_pro_activated() && isset($status_map[$status])) {
            $old_status = $order->get_status();
            $order->update_status($status_map[$status]);
            error_log("[Pathao Debug] Order status updated from $old_status to {$status_map[$status]}");
        }

        // Update meta and save
        $order->update_meta_data('_pathao_order_status', $status);
        $order->save();
        error_log("[Pathao Debug] Updated _pathao_order_status to $status and saved order.");

        do_action('pathao_process_webhook', $status, $params);

        error_log("[Pathao Debug] Webhook processing complete for order $order_id.");

        return new WP_REST_Response(['success' => true], 200);
    }
}
