<?php

namespace SpringDevs\Pathao\Services; 
 
use stdClass;

class PathaoApiService
{

	
    /*-----------------------------------------
    | BASE URL
    ------------------------------------------*/
    private function get_base_url(): string
    {
        return get_option('pathao_sandbox_mode')
            ? 'https://courier-api-sandbox.pathao.com/'
            : 'https://api-hermes.pathao.com/';
    }

    /*-----------------------------------------
    | ACCESS & REFRESH TOKENS
    ------------------------------------------*/
    private function ensure_access_token()
    {
        $token = get_option('pathao_access_token');
        if (!$token) {
            $new = $this->refresh_tokens();
            if ($new && $new->success) {
                update_option('pathao_access_token', $new->data->access_token);
                update_option('pathao_refresh_token', $new->data->refresh_token);
                return $new->data->access_token;
            }
            return false;
        }

        return $token;
    }

    /*-----------------------------------------
    | REQUEST WRAPPER (AUTO TOKEN REFRESH)
    ------------------------------------------*/
    private function request($func, string $path, array $args = [])
    {
        $token = $this->ensure_access_token();

        if (!$token) {
            return ['error' => 'token_missing'];
        }

        $default_headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        $final_args = array_merge(['headers' => $default_headers], $args);

        $url = $this->get_base_url() . ltrim($path, '/');

        $response = $func($url, $final_args);

        // Pathao expired token → 401
        if (wp_remote_retrieve_response_code($response) === 401) {

            $new = $this->refresh_tokens();
            if ($new && $new->success) {
                update_option('pathao_access_token', $new->data->access_token);
                update_option('pathao_refresh_token', $new->data->refresh_token);

                $final_args['headers']['Authorization'] = 'Bearer ' . $new->data->access_token;

                $response = $func($url, $final_args);
            }
        }

        return $response;
    }

    /*-----------------------------------------
    | ERROR HANDLER
    ------------------------------------------*/
    private function has_errors($res)
    {
        $code = wp_remote_retrieve_response_code($res);
        $data = new stdClass();

        if ($code === 401) {
            $data->success = false;
            $data->messages = ['Unauthorized access token'];
            return $data;
        }

        if ($code === 422) {
            $body = json_decode(wp_remote_retrieve_body($res));
            $messages = [];

            if (!empty($body->errors)) {
                foreach ($body->errors as $err) {
                    if (is_array($err)) $messages = array_merge($messages, $err);
                    else $messages[] = $err;
                }
            }

            $data->success = false;
            $data->messages = $messages;
            return $data;
        }

        if ($code < 200 || $code > 299) {
            $data->success = false;
            $data->messages = ['Something went wrong'];
            return $data;
        }

        return false;
    }

    /*-----------------------------------------
    | TRANSIENT CHECK
    ------------------------------------------*/
    private function has_transient($key)
    {
        $saved = get_transient($key);

        if ($saved) {
            return (object)[
                'success' => true,
                'data'    => $saved
            ];
        }

        return false;
    }

    /*-----------------------------------------
    | GET CITIES
    ------------------------------------------*/
    public function get_cities()
    {
        $transient_key = '_sdevs_pathao_cities';

        if ($cached = $this->has_transient($transient_key)) {
            return $cached;
        }

        $res = $this->request('wp_remote_get', 'aladdin/api/v1/cities');

        if ($err = $this->has_errors($res)) {
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));


        if (!isset($body->data->data)) {
            return (object)[
                'success' => false,
                'messages' => ['Cities data malformed']
            ];
        }

        $list = [];
        foreach ($body->data->data as $c) {
            $list[] = (object)[
                'id'   => $c->city_id,
                'name' => $c->city_name
            ];
        }

        set_transient($transient_key, $list, 12 * HOUR_IN_SECONDS);

        return (object)['success' => true, 'data' => $list];
    }

    /*-----------------------------------------
    | GET ZONES
    ------------------------------------------*/
    public function get_zones($city_id)
    {
        $res = $this->request(
            'wp_remote_get',
            "aladdin/api/v1/zones?city_id={$city_id}"
        );

        if ($err = $this->has_errors($res)) {
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        if (!isset($body->data->data)) {
            return [];
        }

        $out = [];
        foreach ($body->data->data as $z) {
            $out[] = [
                'id'   => $z->zone_id,
                'name' => $z->zone_name,
            ];
        }

        return $out;
    }

    /*-----------------------------------------
    | GET AREAS
    ------------------------------------------*/
    public function get_areas(int $zone_id): stdClass
    {
        $key = "_sdevs_pathao_zone_{$zone_id}_areas";

        if ($c = $this->has_transient($key)) {
            return $c;
        }

        $res = $this->request(
            'wp_remote_get',
            "aladdin/api/v1/zones/{$zone_id}/area-list"
        );

        if ($err = $this->has_errors($res)) return $err;

        $body = json_decode(wp_remote_retrieve_body($res));

        $list = [];
        foreach ($body->data->data as $a) {
            $list[] = (object)[
                'id'   => $a->area_id,
                'name' => $a->area_name,
            ];
        }

        set_transient($key, $list, 12 * HOUR_IN_SECONDS);

        return (object)['success' => true, 'data' => $list];
    }

    /*-----------------------------------------
    | GET STORES
    ------------------------------------------*/
    public function get_stores(): stdClass
    {
        $key = '_pathao_store_id';

        if ($c = $this->has_transient($key)) {
            return $c;
        }

        $res = $this->request('wp_remote_get', 'aladdin/api/v1/stores');

        if ($err = $this->has_errors($res)) {
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));


        if (!isset($body->data->data) || !is_array($body->data->data)) {
            return (object)[
                'success' => false,
                'messages' => ['Stores malformed']
            ];
        }

        $list = [];
        foreach ($body->data->data as $s) {
            $list[] = (object)[
                'id'   => $s->store_id,
                'name' => $s->store_name
            ];
        }


        set_transient($key, $list, 5 * MINUTE_IN_SECONDS);

        return (object)['success' => true, 'data' => $list];
    }

    /*-----------------------------------------
    | ORDER PAYLOAD VALIDATOR
    ------------------------------------------*/
    private function validate_order_payload($p)
    {
        $required = [
            'store_id',
            'recipient_name', 
            'recipient_address',
            'delivery_type',
            'item_type',
            'item_quantity',
            'item_weight',
            'amount_to_collect'
        ]; 


        foreach ($required as $f) {
            if (!isset($p[$f]) || $p[$f] === '') {
                return "Missing required field: {$f}";
            }
        }

        if (strlen($p['recipient_phone']) != 11) {
            return "Recipient phone must be 11 digits";
        }

        if (strlen($p['recipient_address']) < 10) {
            return "Recipient address must be at least 10 characters";
        }

        return true;
    }

    /*-----------------------------------------
    | SEND ORDER TO PATHAO
    ------------------------------------------*/
    public function send_order(int $order_id): stdClass
    { 

        if (!function_exists('wc_get_order')) {
            error_log('[Pathao] WooCommerce missing');
            return (object)['success' => false, 'messages' => ['WooCommerce missing']];
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('[Pathao] Invalid order ID');
            return (object)['success' => false, 'messages' => ['Invalid order']];
        }

        /* Prevent duplicate Pathao order */
        $existing = $order->get_meta('_pathao_consignment_id');
        if (!empty($existing)) {
            error_log('[Pathao] Consignment already exists: ' . $existing);
            return (object)[
                'success' => true,
                'messages' => ['Already sent to Pathao'],
                'consignment_id' => $existing
            ];
        }

        /* Extract fields */
        $settings = get_option('woocommerce_pathao_settings');
        $store_id = (int)($settings['store'] ?? 0);

        $payload = [
            'store_id'           => $store_id,
            'merchant_order_id'  => (string)$order_id,
            'recipient_name'     => trim(
                $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()
            ),
            'recipient_phone'    => $order->get_billing_phone() ?: '01700000000',
            'recipient_address'  => $order->get_shipping_address_1() . ', ' . $order->get_shipping_city(),
            'delivery_type'      => 48,
            'item_type'          => 2,
            'item_quantity'      => $order->get_item_count(),
            'item_weight'        => '0.5',
            'item_description'   => "WooCommerce Order #{$order_id}",
            'amount_to_collect'  => $order->get_payment_method() === 'cod'
                ? (int)$order->get_total()
                : 0,
        ];

        error_log('[Pathao] Payload: ' . print_r($payload, true));

        /* Validate payload */
        $validate = $this->validate_order_payload($payload);
        if ($validate !== true) {
            error_log('[Pathao] Validation failed: ' . $validate);
            return (object)['success' => false, 'messages' => [$validate]];
        }

        /* Send API request */
        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/orders',
            ['body' => json_encode($payload)]
        );

        if ($err = $this->has_errors($res)) {
            error_log('[Pathao] API error: ' . print_r($err, true));
            return $err;
        }

        /* Decode response */
        $body = json_decode(wp_remote_retrieve_body($res));

        // error_log('[Pathao] Raw response: ' . print_r($body, true));

        /* SAFELY check consignment_id */
        if (
            empty($body->data) ||
            empty($body->data->consignment_id)
        ) {
            error_log('[Pathao] consignment_id missing in response');
            return (object)[
                'success' => false,
                'messages' => ['Consignment ID not returned'],
                'raw_response' => $body
            ];
        }

        $consignment_id = sanitize_text_field($body->data->consignment_id);
        $order_status   = sanitize_text_field($body->data->order_status ?? 'Pending');

        /* Save to order meta */
        $order->update_meta_data('_pathao_consignment_id', $consignment_id);
        $order->update_meta_data('_pathao_order_status', $order_status);
        $order->save();

        error_log('[Pathao] Consignment saved: ' . $consignment_id);

        /* Fire hook for logging table */
        do_action('pathao_order_created', (object)[
            'merchant_order_id' => $order_id,
            'consignment_id'    => $consignment_id,
            'order_status'      => $order_status,
        ]);

        return (object)[
            'success' => true,
            'consignment_id' => $consignment_id,
            'order_status' => $order_status,
            'data' => $body
        ];
    }

    /*-----------------------------------------
    | SEND BULK ORDERS TO PATHAO
    ------------------------------------------*/
    public function send_bulk_orders(array $orders): stdClass
    {
        if (empty($orders)) {
            return (object)[
                'success' => false,
                'messages' => ['No orders provided']
            ];
        }

        // Validate each order
        foreach ($orders as $o) {
            $check = $this->validate_bulk_order($o);
            if ($check !== true) {
                return (object)[
                    'success' => false,
                    'messages' => [$check],
                ];
            }
        }

        $payload = [
            'orders' => $orders
        ];

        error_log('[Pathao Bulk] Payload: ' . wp_json_encode($payload));

        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/orders/bulk',
            [
                'body' => wp_json_encode($payload)
            ]
        );

        if ($err = $this->has_errors($res)) {
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        return (object)[
            'success' => true,
            'code'    => $body->code ?? 202,
            'data'    => true,
            'message' => $body->message ?? 'Bulk order request accepted',
        ];
    }
 
    public function get_order_by_merchant_order_id(string $merchant_order_id): stdClass
    {
        $res = $this->request(
        'wp_remote_get',
        "aladdin/api/v1/orders?merchant_order_id={$merchant_order_id}"
        );

        if ($err = $this->has_errors($res)) {
        return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        if (empty($body->data->data[0])) {
        return (object)[
        'success' => false,
        'messages' => ['Order not created yet']
        ];
        }

        return (object)[
        'success' => true,
        'data' => $body->data->data[0]
        ];
    }
    private function validate_bulk_order(array $o)
    {
        $required = [
            'store_id',
            'recipient_name',
            'recipient_phone',
            'recipient_address',
            'delivery_type',
            'item_type',
            'item_quantity',
            'item_weight',
            'amount_to_collect',
        ];

        foreach ($required as $field) {
            if (!isset($o[$field]) || $o[$field] === '') {
                return "Missing field: {$field}";
            }
        }

        if (strlen($o['recipient_phone']) !== 11) {
            return 'Recipient phone must be 11 digits';
        }

        if (strlen($o['recipient_address']) < 10) {
            return 'Recipient address too short';
        }

        if ($o['item_weight'] < 0.5 || $o['item_weight'] > 10) {
            return 'Invalid item weight';
        }

        return true;
    }


    /*-----------------------------------------
    | GET ORDER SHORT INFO
    ------------------------------------------*/
    public function get_order_info(string $consignment_id): \stdClass
    {
        if (empty($consignment_id)) {
            return (object) [
                'success' => false,
                'messages' => ['Consignment ID is required'],
            ];
        }

        $response = $this->request(
            'wp_remote_get',
            "aladdin/api/v1/orders/{$consignment_id}/info"
        );

        if (is_wp_error($response)) {
            return (object) [
                'success' => false,
                'messages' => [$response->get_error_message()],
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response));

        error_log('[Pathao Order Info] HTTP ' . $status_code);
        error_log(print_r($body, true));

        if ($status_code !== 200 || empty($body->data)) {
            return (object) [
                'success' => false,
                'messages' => ['Invalid response from Pathao'],
                'raw' => $body,
            ];
        }

        return (object) [
            'success' => true,
            'data' => (object) [
                'consignment_id' => $body->data->consignment_id ?? '',
                'merchant_order_id' => $body->data->merchant_order_id ?? '',
                'order_status' => $body->data->order_status ?? '',
                'order_status_slug' => $body->data->order_status_slug ?? '',
                'updated_at' => $body->data->updated_at ?? '',
                'invoice_id' => $body->data->invoice_id ?? null,
            ],
        ];
    }
 
    /*-----------------------------------------
    | PRICE CALCULATION
    ------------------------------------------*/
    public function price_calculation($args)
    {
        $body = wp_parse_args($args, [
            'store_id'      => pathao_store_id(),
            'item_type'     => 2,
            'delivery_type' => 48,
        ]);

        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/merchant/price-plan',
            ['body' => json_encode($body)]
        );

        if ($err = $this->has_errors($res)) return $err;

        $d = json_decode(wp_remote_retrieve_body($res));

        return (object)[
            'success' => true,
            'data' => (object)[
                'price'       => $d->data->price,
                'cod_enabled' => $d->data->cod_enabled
            ]
        ];
    }

    /*-----------------------------------------
    | TOKEN GENERATION
    ------------------------------------------*/
    public function generate_tokens($args)
    {
        $body = wp_parse_args($args, ['grant_type' => 'password']);

        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/issue-token',
            ['body' => json_encode($body)]
        );

        if ($err = $this->has_errors($res)) return $err;

        $d = json_decode(wp_remote_retrieve_body($res));

        return (object)[
            'success' => true,
            'data' => (object)[
                'access_token'  => $d->access_token,
                'refresh_token' => $d->refresh_token
            ]
        ];
    }

    /*-----------------------------------------
    | TOKEN REFRESH
    ------------------------------------------*/
    public function refresh_tokens()
    {
        $client_id     = get_option('pathao_client_id');
        $client_secret = get_option('pathao_client_secret');
        $refresh_token = get_option('pathao_refresh_token');

        if (!$client_id || !$client_secret || !$refresh_token) {
            return (object)[
                'success'  => false,
                'messages' => ['Token info missing']
            ];
        }

       $url = $this->get_base_url() . 'aladdin/api/v1/issue-token';

        $body = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type'    => 'refresh_token',
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return (object)[
                'success' => false,
                'messages' => [$response->get_error_message()]
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return (object)[
                'success' => false,
                'messages' => ['Token refresh failed'],
            ];
        }

        $data = json_decode(wp_remote_retrieve_body($response));

        return (object)[
            'success' => true,
            'data' => (object)[
                'access_token'  => $data->access_token,
                'refresh_token' => $data->refresh_token,
            ]
        ];
    }
}

