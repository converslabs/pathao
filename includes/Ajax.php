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

        add_action('wp_ajax_send_bulk_orders_to_pathao', [$this, 'send_bulk_orders_to_pathao']);

        add_action('wp_ajax_get_bulk_orders_data', [$this, 'get_bulk_orders_data']);

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

    /* -------------------------------------------------------------------------
     * BULK ORDER HANDLER (Pathao does NOT support true bulk - process one-by-one)
     * ---------------------------------------------------------------------- */
    public function send_bulk_orders_to_pathao()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Permission denied'], 403);
        }

        check_ajax_referer('pathao_nonce', 'nonce');

        // Parse form data (orders[ORDER_ID][field_name] format)
        $form_data = $_POST['form_data'] ?? '';
        if (empty($form_data)) {
            wp_send_json_error(['message' => 'No form data received']);
        }

        parse_str($form_data, $parsed_data);
        $orders = $parsed_data['orders'] ?? [];

        if (empty($orders) || ! is_array($orders)) {
            wp_send_json_error(['message' => 'No orders data received']);
        }

        // Get store
        $settings = get_option('woocommerce_pathao_settings');
        $store_id = absint($settings['store'] ?? 0);

        if (! $store_id) {
            wp_send_json_error(['message' => 'Pathao store not configured']);
        }

        // Track results
        $sent = [];
        $skipped = [];
        $failed = [];

        $api = new \ConversLabs\Pathao\Services\PathaoApiService();

        // Process each order individually (Pathao doesn't support true bulk)
        foreach ($orders as $order_id_str => $row) {
            $order_id = absint($order_id_str);
            if (! $order_id) {
                continue;
            }

            // Validate order exists
            $order = wc_get_order($order_id);
            if (! $order) {
                $failed[$order_id] = 'Order not found';
                continue;
            }

            // Skip already sent orders
            $existing_consignment_id = $order->get_meta('_pathao_consignment_id');
            if (! empty($existing_consignment_id)) {
                $skipped[] = $order_id;
                continue;
            }

            // Validate and sanitize recipient name (3-100 characters per API docs)
            $recipient_name = sanitize_text_field($row['recipient_name'] ?? '');
            if (empty($recipient_name)) {
                $recipient_name = $order->get_formatted_shipping_full_name() ?: $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            }
            $recipient_name = trim($recipient_name);
            if (empty($recipient_name)) {
                $failed[$order_id] = 'Recipient name is required';
                continue;
            }
            if (strlen($recipient_name) < 3 || strlen($recipient_name) > 100) {
                $failed[$order_id] = 'Recipient name must be between 3 and 100 characters';
                continue;
            }

            // Validate and sanitize phone
            $phone = preg_replace('/\D+/', '', $row['recipient_phone'] ?? '');
            if (empty($phone)) {
                $phone = preg_replace('/\D+/', '', $order->get_billing_phone());
            }
            if (strlen($phone) !== 11) {
                $failed[$order_id] = 'Invalid phone number (must be 11 digits)';
                continue;
            }

            // Validate and sanitize address (10-220 characters per API docs)
            $recipient_address = sanitize_textarea_field($row['recipient_address'] ?? '');
            if (empty($recipient_address)) {
                $recipient_address = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
            }
            $recipient_address = trim($recipient_address);
            if (empty($recipient_address) || strlen($recipient_address) < 10) {
                $failed[$order_id] = 'Recipient address is required (minimum 10 characters)';
                continue;
            }
            if (strlen($recipient_address) > 220) {
                $failed[$order_id] = 'Recipient address must not exceed 220 characters';
                continue;
            }

            // Validate city and zone (required)
            $city = absint($row['recipient_city'] ?? 0);
            $zone = absint($row['recipient_zone'] ?? 0);
            $area = absint($row['recipient_area'] ?? 0);

            if (! $city || ! $zone) {
                $failed[$order_id] = 'City and Zone are required';
                continue;
            }

            // Sanitize other fields
            $area = absint($row['recipient_area'] ?? 0);
            $weight = max(0.5, min(10, (float) ($row['item_weight'] ?? 0.5))); // Min 0.5, Max 10 kg per API docs
            $quantity = max(1, absint($row['item_quantity'] ?? 1));
            $delivery_type = absint($row['delivery_type'] ?? 48);
            $item_type = absint($row['item_type'] ?? 2);
            $amount_to_collect = (float) ($row['amount_to_collect'] ?? ($order->is_paid() ? 0 : $order->get_total()));
            $recipient_secondary_phone = preg_replace('/\D+/', '', $row['recipient_secondary_phone'] ?? '');
            $special_instruction = sanitize_textarea_field($row['special_instruction'] ?? '');

            // Validate secondary phone if provided
            if (! empty($recipient_secondary_phone) && strlen($recipient_secondary_phone) !== 11) {
                $recipient_secondary_phone = ''; // Clear invalid secondary phone
            }

            // Build payload
            $payload = [
                'store_id'                  => (int) $store_id,
                'merchant_order_id'         => (string) $order_id,
                'recipient_name'            => (string) $recipient_name,
                'recipient_phone'           => (string) $phone,
                'recipient_secondary_phone' => (string) $recipient_secondary_phone,
                'recipient_address'         => (string) $recipient_address,
                'recipient_city'            => (int) $city,
                'recipient_zone'            => (int) $zone,
                'recipient_area'            => (int) $area,
                'delivery_type'             => (int) $delivery_type,
                'item_type'                 => (int) $item_type,
                'item_quantity'             => (int) $quantity,
                'item_weight'               => number_format((float)$weight, 2, '.', ''), // Strict format 0.50
                'amount_to_collect'         => (int) $amount_to_collect, // Pathao usually expects Int for COD
                'special_instruction'       => (string) $special_instruction,
                'item_description'          => 'WooCommerce Order #' . $order_id,
            ];

            // Send order individually to Pathao API
            $response = $api->send_order_with_payload($payload);

            if (! empty($response->success) && ! empty($response->data->consignment_id)) {
                // Success - save consignment ID and meta
                $order->update_meta_data('_pathao_consignment_id', $response->data->consignment_id);
                $order->update_meta_data('_pathao_order_status', $response->data->order_status ?? 'pending');
                $order->update_meta_data('_pathao_city', $city);
                $order->update_meta_data('_pathao_zone', $zone);
                $order->update_meta_data('_pathao_area', $area);
                $order->update_meta_data('_pathao_weight', $weight);
                $order->save();

                $sent[] = $order_id;
            } else {
                // Failed - record error
                $error_message = ! empty($response->messages) && is_array($response->messages)
                    ? implode(', ', $response->messages)
                    : ($response->message ?? 'Unknown error');
                $failed[$order_id] = $error_message;
            }
        }

        // Build response
        $response_data = [
            'sent'    => $sent,
            'skipped' => $skipped,
            'failed'  => $failed,
        ];

        // If all failed or skipped, return error
        if (empty($sent) && ! empty($failed)) {
            wp_send_json_error([
                'message' => 'All orders failed to send',
                'sent'    => $sent,
                'skipped' => $skipped,
                'failed'  => $failed,
            ]);
        }

        // Return success with results
        wp_send_json_success([
            'message' => sprintf(
                '%d sent, %d skipped, %d failed',
                count($sent),
                count($skipped),
                count($failed)
            ),
            'sent'    => $sent,
            'skipped' => $skipped,
            'failed'  => $failed,
        ]);
    }


     public function get_bulk_orders_data()
{
    if (! current_user_can('manage_woocommerce')) {
        wp_send_json_error(['message' => 'Permission denied'], 403);
    }

    check_ajax_referer('pathao_nonce', 'nonce');

    $order_ids = isset($_POST['order_ids'])
        ? array_map('absint', (array) $_POST['order_ids'])
        : [];

    if (empty($order_ids)) {
        wp_send_json_error(['message' => 'No orders selected']);
    }

    // Load cities once
    $api = new \ConversLabs\Pathao\Services\PathaoApiService();
    $cities_res = $api->get_cities();
    $cities = (!empty($cities_res->success) && !empty($cities_res->data))
        ? $cities_res->data
        : [];

    ob_start();

    foreach ($order_ids as $order_id) :
        $order = wc_get_order($order_id);
        if (! $order) {
            continue;
        }

        $existing_city   = $order->get_meta('_pathao_city');
        $existing_zone   = $order->get_meta('_pathao_zone');
        $existing_area   = $order->get_meta('_pathao_area');
        $existing_weight = $order->get_meta('_pathao_weight');

        $recipient_name = $order->get_formatted_shipping_full_name()
            ?: trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());

        $recipient_phone   = $order->get_billing_phone();
        $recipient_address = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
        $item_quantity     = $order->get_item_count();
        $item_weight       = $existing_weight ?: 0.5;
        $amount_to_collect = $order->is_paid() ? 0 : $order->get_total();

        $is_sent = ! empty($order->get_meta('_pathao_consignment_id'));
        ?>

        <tr data-order-id="<?php echo esc_attr($order_id); ?>" <?php echo $is_sent ? 'style="opacity:.6"' : ''; ?>>

            <!-- Order -->
            <td>
                #<?php echo esc_html($order_id); ?>
                <input type="hidden" name="orders[<?php echo $order_id; ?>][order_id]" value="<?php echo $order_id; ?>">
                <?php if ($is_sent) : ?>
                    <br><small style="color:orange;">Already sent</small>
                <?php endif; ?>
            </td>

            <!-- Name -->
            <td>
                <input type="text" name="orders[<?php echo $order_id; ?>][recipient_name]"
                       value="<?php echo esc_attr($recipient_name); ?>" required>
            </td>

            <!-- Phone -->
            <td>
                <input type="text" name="orders[<?php echo $order_id; ?>][recipient_phone]"
                       value="<?php echo esc_attr($recipient_phone); ?>" required>
            </td>

            <!-- City -->
            <td>
                <select name="orders[<?php echo $order_id; ?>][recipient_city]"
                        class="pathao-bulk-city"
                        data-selected-city="<?php echo esc_attr($existing_city); ?>" required>
                    <option value="">City</option>
                    <?php foreach ($cities as $city) : ?>
                        <option value="<?php echo esc_attr($city->id); ?>" <?php selected($existing_city, $city->id); ?>>
                            <?php echo esc_html($city->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>

            <!-- Zone -->
            <td>
                <select name="orders[<?php echo $order_id; ?>][recipient_zone]"
                        class="pathao-bulk-zone"
                        data-order-id="<?php echo $order_id; ?>"
                        data-selected-zone="<?php echo esc_attr($existing_zone); ?>" required>
                    <option value="">Zone</option>
                </select>
            </td>

            <!-- Area -->
            <td>
                <select name="orders[<?php echo $order_id; ?>][recipient_area]"
                        class="pathao-bulk-area"
                        data-order-id="<?php echo $order_id; ?>"
                        data-selected-area="<?php echo esc_attr($existing_area); ?>">
                    <option value="">Area</option>
                </select>
            </td>

            <!-- Address -->
            <td>
                <textarea name="orders[<?php echo $order_id; ?>][recipient_address]"
                          rows="2" required><?php echo esc_textarea($recipient_address); ?></textarea>
            </td>

            <!-- Delivery -->
            <td>
                <select name="orders[<?php echo $order_id; ?>][delivery_type]">
                    <option value="48">Normal</option>
                    <option value="12">Express</option>
                </select>
            </td>

            <!-- Item Type -->
            <td>
                <select name="orders[<?php echo $order_id; ?>][item_type]">
                    <option value="2">Parcel</option>
                    <option value="1">Document</option>
                </select>
            </td>

            <!-- Weight -->
            <td>
                <input type="number" name="orders[<?php echo $order_id; ?>][item_weight]"
                       value="<?php echo esc_attr($item_weight); ?>" step="0.01" min="0.5" required>
            </td>

            <!-- Qty -->
            <td>
                <input type="number" name="orders[<?php echo $order_id; ?>][item_quantity]"
                       value="<?php echo esc_attr($item_quantity); ?>" min="1" required>
            </td>

            <!-- COD -->
            <td>
                <input type="number" name="orders[<?php echo $order_id; ?>][amount_to_collect]"
                       value="<?php echo esc_attr($amount_to_collect); ?>" step="0.01" min="0" required>
            </td>

            <!-- Instruction -->
            <td>
                <textarea name="orders[<?php echo $order_id; ?>][special_instruction]" rows="2"></textarea>
            </td>

        </tr>

    <?php endforeach;

    wp_send_json_success([
        'html' => ob_get_clean()
    ]);
}




    public function get_wc_order_info()
    {

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
            $product = $item->get_product();
            if (! $product) continue;

            $items[] = [
                'name'  => $product->get_name(),
                'image' => $product->get_image_id()
                    ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail')
                    : wc_placeholder_img_src(),
            ];
        }

        wp_send_json([
            'name'           => $order->get_formatted_shipping_full_name(),
            'phone'          => $order->get_billing_phone(),
            'address'        => $order->get_shipping_address_1(),
            'items'          => $items,
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
