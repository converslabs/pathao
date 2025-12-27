<?php

namespace ConversLabs\Pathao;

use ConversLabs\Pathao\Facades\PathaoAPI;
use ConversLabs\Pathao\Services\PathaoApiService;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax {

    private static $instance = null;
    private $api_service;

    public static function init() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->api_service = new PathaoApiService();

        // Register Hooks
        $actions = [
            'send_order_to_pathao',
            'get_wc_order_info',
            'get_cities',
            'get_city_zones',
            'get_zone_areas',
            'pathao_sync_order_status' => 'sync_order_status',
            'pathao_price_calculation' => 'price_calculation',
            'pathao_setup_generate_token' => 'handle_setup_generate_token',
            'send_bulk_orders_to_pathao',
            'get_bulk_orders_data'
        ];

        foreach ( $actions as $hook => $method ) {
            $action = is_numeric( $hook ) ? $method : $hook;
            add_action( "wp_ajax_{$action}", [ $this, $method ] );
        }
    }

    /**
     * Helper to verify permissions and nonces
     */
    private function verify_request( $nonce_action = 'pathao_nonce', $capability = 'manage_woocommerce' ) {
        if ( ! current_user_can( $capability ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'integration-of-pathao-for-woocommerce' ) ], 403 );
        }
        check_ajax_referer( $nonce_action, 'nonce' );
    }

    /**
     * Generate API Tokens
     */
    public function handle_setup_generate_token() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'integration-of-pathao-for-woocommerce' ) ], 403 );
        }

        if ( empty( $_POST['_wp_setup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wp_setup_nonce'] ) ), '_pathao_setup_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed', 'integration-of-pathao-for-woocommerce' ) ], 400 );
        }

        $client_id     = sanitize_text_field( $_POST['client_id'] ?? '' );
        $client_secret = sanitize_text_field( $_POST['client_secret'] ?? '' );
        $username      = sanitize_text_field( $_POST['username'] ?? '' );
        $password      = sanitize_text_field( $_POST['password'] ?? '' );
        $sandbox_mode  = ! empty( $_POST['sandbox_mode'] ) ? 1 : 0;

        if ( ! $client_id || ! $client_secret || ! $username || ! $password ) {
            wp_send_json_error( [ 'message' => __( 'All fields are required', 'integration-of-pathao-for-woocommerce' ) ] );
        }

        update_option( 'pathao_client_id', $client_id );
        update_option( 'pathao_client_secret', $client_secret );
        update_option( 'pathao_sandbox_mode', $sandbox_mode );

        $res = PathaoAPI::generate_tokens( [
            'username'   => $username,
            'password'   => $password,
            'grant_type' => 'password',
        ] );

        if ( empty( $res->success ) ) {
            wp_send_json_error( [ 'message' => $res->messages[0] ?? __( 'Token generation failed', 'integration-of-pathao-for-woocommerce' ) ] );
        }

        wp_send_json_success( [
            'message'      => __( 'Token generated successfully', 'integration-of-pathao-for-woocommerce' ),
            'access_token' => $res->data->access_token ?? '',
            'sandbox_mode' => (int) $sandbox_mode,
        ] );
    }

    /**
     * Get Bulk Orders HTML Table Rows
     */
    public function get_bulk_orders_data() {
        $this->verify_request();

        $order_ids = array_map( 'absint', (array) ( $_POST['order_ids'] ?? [] ) );
        if ( empty( $order_ids ) ) {
            wp_send_json_error( [ 'message' => 'No orders selected' ] );
        }

        $cities_res = $this->api_service->get_cities();
        $cities     = ( ! empty( $cities_res->success ) ) ? $cities_res->data : [];

        ob_start();
        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) continue;

            $this->render_bulk_row( $order, $cities );
        }
        wp_send_json_success( [ 'html' => ob_get_clean() ] );
    }

    private function render_bulk_row( $order, $cities ) {
        $order_id        = $order->get_id();
        $existing_city   = $order->get_meta( '_pathao_city' );
        $existing_zone   = $order->get_meta( '_pathao_zone' );
        $existing_area   = $order->get_meta( '_pathao_area' );
        $recipient_name  = $order->get_formatted_shipping_full_name() ?: ( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
        $is_sent         = ! empty( $order->get_meta( '_pathao_consignment_id' ) );
        ?>
        <tr data-order-id="<?php echo esc_attr( $order_id ); ?>" <?php echo $is_sent ? 'style="opacity:.6"' : ''; ?>>
            <td>
                <strong>#<?php echo esc_html( $order_id ); ?></strong>
                <input type="hidden" name="orders[<?php echo $order_id; ?>][order_id]" value="<?php echo $order_id; ?>">
                <?php if ( $is_sent ) : ?><br><small style="color:orange;">Already sent</small><?php endif; ?>
            </td>
            <td><input type="text" name="orders[<?php echo $order_id; ?>][recipient_name]" value="<?php echo esc_attr( $recipient_name ); ?>" required></td>
            <td><input type="text" name="orders[<?php echo $order_id; ?>][recipient_phone]" value="<?php echo esc_attr( $order->get_billing_phone() ); ?>" required></td>
            <td>
                <select name="orders[<?php echo $order_id; ?>][recipient_city]" class="pathao-bulk-city" data-selected-city="<?php echo esc_attr( $existing_city ); ?>" required>
                    <option value="">City</option>
                    <?php foreach ( $cities as $city ) : ?>
                        <option value="<?php echo esc_attr( $city->id ); ?>" <?php selected( $existing_city, $city->id ); ?>><?php echo esc_html( $city->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="orders[<?php echo $order_id; ?>][recipient_zone]" class="pathao-bulk-zone" data-order-id="<?php echo $order_id; ?>" data-selected-zone="<?php echo esc_attr( $existing_zone ); ?>" required>
                    <option value="">Zone</option>
                </select>
            </td>
            <td>
                <select name="orders[<?php echo $order_id; ?>][recipient_area]" class="pathao-bulk-area" data-order-id="<?php echo $order_id; ?>" data-selected-area="<?php echo esc_attr( $existing_area ); ?>">
                    <option value="">Area</option>
                </select>
            </td>
            <td><textarea name="orders[<?php echo $order_id; ?>][recipient_address]" rows="2" required><?php echo esc_textarea( $order->get_shipping_address_1() ?: $order->get_billing_address_1() ); ?></textarea></td>
            <td>
                <select name="orders[<?php echo $order_id; ?>][delivery_type]">
                    <option value="48">Normal</option>
                    <option value="12">Express</option>
                </select>
            </td>
            <td>
                <input type="number" name="orders[<?php echo $order_id; ?>][item_weight]" value="<?php echo esc_attr( $order->get_meta( '_pathao_weight' ) ?: 0.5 ); ?>" step="0.1" min="0.5" required>
            </td>
            <td>
                <input type="number" name="orders[<?php echo $order_id; ?>][amount_to_collect]" value="<?php echo esc_attr( $order->is_paid() ? 0 : $order->get_total() ); ?>" step="1" min="0" required>
            </td>
        </tr>
        <?php
    }

    /**
     * Single Order Send
     */
    public function send_order_to_pathao() {
        $this->verify_request();

        parse_str( $_POST['form'] ?? '', $form );
        $order_id = absint( $_POST['order_id'] ?? 0 );
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_send_json_error( [ 'message' => 'Order not found' ] );
        }

        $settings = get_option( 'woocommerce_pathao_settings' );
        $store_id = absint( $settings['store'] ?? 0 );

        if ( ! $store_id ) {
            wp_send_json_error( [ 'message' => 'Store not configured' ] );
        }

        $payload = [
            'store_id'            => $store_id,
            'merchant_order_id'   => (string) $order_id,
            'recipient_name'      => sanitize_text_field( $form['recipient_name'] ?? '' ),
            'recipient_phone'     => sanitize_text_field( $form['recipient_phone'] ?? '' ),
            'recipient_address'   => sanitize_textarea_field( $form['recipient_address'] ?? '' ),
            'recipient_city'      => absint( $form['recipient_city'] ?? 0 ),
            'recipient_zone'      => absint( $form['recipient_zone'] ?? 0 ),
            'recipient_area'      => absint( $form['recipient_area'] ?? 0 ),
            'delivery_type'       => absint( $form['delivery_type'] ?? 48 ),
            'item_type'           => absint( $form['item_type'] ?? 2 ),
            'item_weight'         => (float) ( $form['item_weight'] ?? 0.5 ),
            'item_quantity'       => absint( $form['item_quantity'] ?? 1 ),
            'amount_to_collect'   => (float) ( $form['amount_to_collect'] ?? 0 ),
            'special_instruction' => sanitize_textarea_field( $form['special_instruction'] ?? '' ),
            'item_description'    => 'Order #' . $order_id,
        ];

        $res = $this->api_service->send_order_with_payload( $payload );

        if ( ! empty( $res->success ) ) {
            $order->update_meta_data( '_pathao_consignment_id', $res->data->consignment_id );
            $order->update_meta_data( '_pathao_order_status', $res->data->order_status ?? 'Pending' );
            $order->save();
            wp_send_json_success( [ 'message' => 'Order sent successfully' ] );
        }

        wp_send_json_error( [ 'message' => $res->messages[0] ?? 'API Error' ] );
    }

    /**
     * Get Cities/Zones/Areas
     */
    public function get_cities() {
        $this->verify_request();
        $res = PathaoAPI::get_cities();
        if ( ! empty( $res->success ) ) {
            wp_send_json_success( [ 'cities' => $res->data ] );
        }
        wp_send_json_error( [ 'message' => 'Failed to load cities' ] );
    }

    public function get_city_zones() {
        $this->verify_request();
        $city_id = absint( $_POST['city'] ?? 0 );
        $res = PathaoAPI::get_city_zones( $city_id );
        if ( ! empty( $res->success ) ) {
            wp_send_json_success( [ 'zones' => $res->data ] );
        }
        wp_send_json_error( [ 'message' => 'Failed to load zones' ] );
    }

    public function get_zone_areas() {
        $this->verify_request();
        $zone_id = absint( $_POST['zone'] ?? 0 );
        $res = PathaoAPI::get_areas( $zone_id );
        if ( ! empty( $res->success ) ) {
            wp_send_json_success( [ 'areas' => $res->data ] );
        }
        wp_send_json_error( [ 'message' => 'Failed to load areas' ] );
    }

    public function get_wc_order_info() {
    $this->verify_request();

    $order_id = absint($_POST['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
    }

    $items = [];
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        $items[] = [
            'name'  => $item->get_name(),
            'image' => $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '',
        ];
    }

    wp_send_json_success([
        'name'            => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
        'phone'           => $order->get_billing_phone(),
        'address'         => $order->get_billing_address_1(),
        'total'           => (float) $order->get_total(),
        'payment_status'  => wc_get_order_status_name($order->get_status()),
        'quantity'        => $order->get_item_count(),
        'weight'          => 1,
        'cod_amount'      => $order->get_payment_method() === 'cod'
                                ? (float) $order->get_total()
                                : 0,
        'items'           => $items,
    ]);
}

    /**
     * Price Calculation
     */
    public function price_calculation() {
        $this->verify_request();
        $settings = get_option( 'woocommerce_pathao_settings' );
        
        $args = [
            'store_id'       => absint( $settings['store'] ?? 0 ),
            'item_type'      => absint( $_POST['item_type'] ?? 2 ),
            'delivery_type'  => absint( $_POST['delivery_type'] ?? 48 ),
            'item_weight'    => (float) ( $_POST['item_weight'] ?? 0.5 ),
            'recipient_city' => absint( $_POST['recipient_city'] ?? 0 ),
            'recipient_zone' => absint( $_POST['recipient_zone'] ?? 0 ),
        ];

        $res = $this->api_service->price_calculation( $args );
        if ( ! empty( $res->success ) ) {
            wp_send_json_success( $res->data );
        }
        wp_send_json_error( [ 'message' => 'Calculation failed' ] );
    }

    /**
     * Sync Order Status
     */
    public function sync_order_status() {
        $this->verify_request();

        $orders = wc_get_orders( [
            'limit'        => 20,
            'meta_key'     => '_pathao_consignment_id',
            'meta_compare' => 'EXISTS',
        ] );

        $updated = 0;
        foreach ( $orders as $order ) {
            $consignment_id = $order->get_meta( '_pathao_consignment_id' );
            $res = $this->api_service->get_order_info( $consignment_id );

            if ( ! empty( $res->success ) && ! empty( $res->data->order_status_slug ) ) {
                $order->update_meta_data( '_pathao_order_status', $res->data->order_status_slug );
                $order->save();
                $updated++;
            }
        }

        wp_send_json_success( [ 'message' => sprintf( '%d orders synced', $updated ) ] );
    }
}