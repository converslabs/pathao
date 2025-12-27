<?php
namespace ConversLabs\Pathao;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    exit;
}

class Api {

    private $logger;
    const WEBHOOK_SECRET_HEADER = 'f3992ecc-59da-4cbe-a049-a13da2018d51';

    public function __construct() {
        $this->logger = wc_get_logger();
        add_action('rest_api_init', array($this, 'register_api'));
    }

    public function register_api() {
        // Webhook endpoint for Pathao status updates
        register_rest_route(
            'pathao/v1',
            '/webhook',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_webhook'),
                'permission_callback' => '__return_true',
            )
        );
    }

    /**
     * Handle Pathao webhook requests
     * 
     * Requirements per Pathao docs:
     * - Must return status code 202
     * - Must include header X-Pathao-Merchant-Webhook-Integration-Secret: f3992ecc-59da-4cbe-a049-a13da2018d51
     * - Must respond within 10 seconds
     * - Must validate X-PATHAO-Signature header
     */
    public function handle_webhook(WP_REST_Request $request) {
        $start_time = microtime(true);

        // Get webhook secret from options (user configured)
        $webhook_secret = get_option('pathao_webhook_secret', '');
        
        // Get signature from header
        $signature = $request->get_header('X-PATHAO-Signature');
        
        // Get raw body for signature verification
        $raw_body = $request->get_body();
        
        // Verify signature if webhook secret is configured
        if (! empty($webhook_secret) && ! empty($signature)) {
            $computed_signature = hash_hmac('sha256', $raw_body, $webhook_secret);
            if (! hash_equals($computed_signature, $signature)) {
                $this->logger->error('Pathao webhook: Invalid signature', array('source' => 'pathao-webhook'));
                return new WP_REST_Response(
                    array('success' => false, 'message' => 'Invalid signature'),
                    403,
                    array('X-Pathao-Merchant-Webhook-Integration-Secret' => self::WEBHOOK_SECRET_HEADER)
                );
            }
        }

        // Parse JSON body
        $body = json_decode($raw_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Pathao webhook: Invalid JSON', array('source' => 'pathao-webhook'));
            return new WP_REST_Response(
                array('success' => false, 'message' => 'Invalid JSON'),
                400,
                array('X-Pathao-Merchant-Webhook-Integration-Secret' => self::WEBHOOK_SECRET_HEADER)
            );
        }

        // Handle webhook integration test
        if (! empty($body['event']) && $body['event'] === 'webhook_integration') {
            $this->logger->info('Pathao webhook: Integration test received', array('source' => 'pathao-webhook'));
            return new WP_REST_Response(
                array('success' => true, 'message' => 'Webhook integration verified'),
                202,
                array('X-Pathao-Merchant-Webhook-Integration-Secret' => self::WEBHOOK_SECRET_HEADER)
            );
        }

        // Get event type
        $event = ! empty($body['event']) ? sanitize_text_field($body['event']) : '';
        $consignment_id = ! empty($body['consignment_id']) ? sanitize_text_field($body['consignment_id']) : '';
        $merchant_order_id = ! empty($body['merchant_order_id']) ? sanitize_text_field($body['merchant_order_id']) : '';

        if (empty($event) || empty($consignment_id)) {
            $this->logger->warning('Pathao webhook: Missing required fields', array('source' => 'pathao-webhook', 'body' => $body));
            return new WP_REST_Response(
                array('success' => false, 'message' => 'Missing required fields'),
                400,
                array('X-Pathao-Merchant-Webhook-Integration-Secret' => self::WEBHOOK_SECRET_HEADER)
            );
        }

        // Log webhook received
        $this->logger->info('Pathao webhook received', array(
            'source' => 'pathao-webhook',
            'event' => $event,
            'consignment_id' => $consignment_id,
            'merchant_order_id' => $merchant_order_id
        ));

        // Get WooCommerce order by merchant_order_id
        $order_id = absint($merchant_order_id);
        if (! $order_id) {
            $this->logger->warning('Pathao webhook: Invalid merchant_order_id', array('source' => 'pathao-webhook', 'merchant_order_id' => $merchant_order_id));
            return new WP_REST_Response(
                array('success' => false, 'message' => 'Invalid merchant_order_id'),
                400,
                array('X-Pathao-Merchant-Webhook-Integration-Secret' => self::WEBHOOK_SECRET_HEADER)
            );
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            $this->logger->warning('Pathao webhook: Order not found', array('source' => 'pathao-webhook', 'order_id' => $order_id));
            return new WP_REST_Response(
                array('success' => false, 'message' => 'Order not found'),
                404,
                array('X-Pathao-Merchant-Webhook-Integration-Secret' => self::WEBHOOK_SECRET_HEADER)
            );
        }

        // Verify consignment_id matches
        $stored_consignment_id = $order->get_meta('_pathao_consignment_id');
        if ($stored_consignment_id !== $consignment_id) {
            $this->logger->warning('Pathao webhook: Consignment ID mismatch', array(
                'source' => 'pathao-webhook',
                'received' => $consignment_id,
                'stored' => $stored_consignment_id
            ));
            // Don't fail - might be a different consignment
        }

        // Update delivery fee if provided
        if (isset($body['delivery_fee'])) {
            $order->update_meta_data('_pathao_delivery_fee', (float) $body['delivery_fee']);
        }

        // Update store_id if provided
        if (isset($body['store_id'])) {
            $order->update_meta_data('_pathao_store_id', absint($body['store_id']));
        }

        // Handle different event types
        $this->handle_event($order, $event, $body);

        // Save order
        $order->save();

        // Trigger action for other plugins/themes
        do_action('pathao_webhook_event', $event, $order, $body);

        // Check if we're within 10 seconds (should be, but log if not)
        $execution_time = microtime(true) - $start_time;
        if ($execution_time > 10) {
            $this->logger->warning('Pathao webhook: Execution time exceeded 10 seconds', array(
                'source' => 'pathao-webhook',
                'execution_time' => $execution_time
            ));
        }

        // Return 202 Accepted with required header
        return new WP_REST_Response(
            array('success' => true, 'message' => 'Webhook processed'),
            202,
            array('X-Pathao-Merchant-Webhook-Integration-Secret' => self::WEBHOOK_SECRET_HEADER)
        );
    }

    /**
     * Handle specific webhook events
     */
    private function handle_event($order, $event, $body) {
        // Map event to order status slug
        $status_slug_map = array(
            'order.created' => 'Pending',
            'order.updated' => 'Pending',
            'order.pickup-requested' => 'Pickup Requested',
            'order.assigned-for-pickup' => 'Assigned For Pickup',
            'order.picked' => 'Picked',
            'order.pickup-failed' => 'Pickup Failed',
            'order.pickup-cancelled' => 'Pickup Cancelled',
            'order.at-sorting-hub' => 'At Sorting Hub',
            'order.in-transit' => 'In Transit',
            'order.received-at-last-mile-hub' => 'Received at Last Mile Hub',
            'order.assigned-for-delivery' => 'Assigned for Delivery',
            'order.delivered' => 'Delivered',
            'order.partial-delivery' => 'Partial Delivery',
            'order.return' => 'Return',
            'order.delivery-failed' => 'Delivery Failed',
            'order.on-hold' => 'On Hold',
            'order.payment-invoice' => 'Payment Invoice',
            'order.paid-return' => 'Paid Return',
            'order.exchange' => 'Exchange',
        );

        // Get status slug
        $status_slug = isset($status_slug_map[$event]) ? $status_slug_map[$event] : 'Unknown';

        // Update order meta
        $order->update_meta_data('_pathao_order_status', $status_slug);
        $order->update_meta_data('_pathao_last_webhook_event', $event);
        $order->update_meta_data('_pathao_last_webhook_time', current_time('mysql'));

        // Update WooCommerce order status based on event (optional - can be disabled)
        $update_wc_status = apply_filters('pathao_webhook_update_wc_order_status', true, $event, $order);
        
        if ($update_wc_status) {
            $wc_status_map = array(
                'order.delivered' => 'completed',
                'order.pickup-failed' => 'failed',
                'order.pickup-cancelled' => 'cancelled',
                'order.delivery-failed' => 'failed',
                'order.return' => 'refunded',
            );

            if (isset($wc_status_map[$event])) {
                $new_status = $wc_status_map[$event];
                $order->update_status($new_status, sprintf(__('Pathao webhook: %s', 'integration-of-pathao-for-woocommerce'), $status_slug));
            }
        }

        // Log event
        $this->logger->info('Pathao webhook event processed', array(
            'source' => 'pathao-webhook',
            'order_id' => $order->get_id(),
            'event' => $event,
            'status_slug' => $status_slug
        ));
    }
}
