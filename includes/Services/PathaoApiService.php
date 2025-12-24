<?php

namespace ConversLabs\Pathao\Services;

use stdClass;

class PathaoApiService
{


    /*-----------------------------------------
    | BASE URL
    ------------------------------------------*/
    // Base URL must follow official Pathao Courier Merchant API docs:
    // Sandbox:   https://courier-api-sandbox.pathao.com
    // Production: https://api-hermes.pathao.com
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
            'Content-Type'  => 'application/json; charset=UTF-8',
            'Accept'        => 'application/json',
        ];

        $final_args = $args;

        // ✅ SAFE header merge (DO NOT overwrite Authorization)
        $final_args['headers'] = array_merge(
            $default_headers,
            $args['headers'] ?? []
        );

        $url = $this->get_base_url() . ltrim($path, '/');
        // error_log("[Everything URL]: ". $url);
        // error_log("[Everything]: ". print_r($final_args, true));

        // Always call the requested endpoint; previously a hardcoded URL broke all requests.
        $response = $func($url, $final_args);

        // Token expired → refresh
        if (wp_remote_retrieve_response_code($response) === 401) {
            $new = $this->refresh_tokens();
            if ($new && $new->success) {
                update_option('pathao_access_token', $new->data->access_token);
                update_option('pathao_refresh_token', $new->data->refresh_token);

                $final_args['headers']['Authorization'] =
                    'Bearer ' . $new->data->access_token;

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

        $res = $this->request('wp_remote_get', 'aladdin/api/v1/city-list');

        if ($err = $this->has_errors($res)) {
            return $err;
        }
        $body = json_decode(wp_remote_retrieve_body($res));

        if (!isset($body->data->data) || !is_array($body->data->data)) {
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
    | GET ZONES BY CITY (NEW ENDPOINT)
    | /aladdin/api/v1/cities/{city_id}/zone-list
    ------------------------------------------*/
    public function get_city_zones(int $city_id): stdClass
    {
        $key = "_sdevs_pathao_city_{$city_id}_zones";

        if ($c = $this->has_transient($key)) {
            return $c;
        }

        $res = $this->request(
            'wp_remote_get',
            "aladdin/api/v1/cities/{$city_id}/zone-list"
        );

        if ($err = $this->has_errors($res)) {
            error_log('[Pathao Zone Error] ' . print_r($err, true));
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        if (
            empty($body->data->data) ||
            !is_array($body->data->data)
        ) {
            return (object)[
                'success' => false,
                'messages' => ['Zone data malformed']
            ];
        }

        $list = [];
        foreach ($body->data->data as $z) {
            $list[] = (object)[
                'id'   => $z->zone_id,
                'name' => $z->zone_name,
            ];
        }

        set_transient($key, $list, 12 * HOUR_IN_SECONDS);

        return (object)[
            'success' => true,
            'data'    => $list
        ];
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
    | Docs: GET {base_url}/aladdin/api/v1/stores
    ------------------------------------------*/
    public function get_stores(): stdClass
    {
        $key = '_pathao_store_id';

        if ($c = $this->has_transient($key)) {
            return $c;
        }

        $res = $this->request('wp_remote_get', 'aladdin/api/v1/stores');

        error_log('[Pathao Stores] Response: ' . print_r($res, true));
        if ($err = $this->has_errors($res)) {
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        if (!isset($body->data->data) || !is_array($body->data->data)) {
            return (object)[
                'success'  => false,
                'messages' => ['Stores malformed'],
            ];
        }

        $list = [];
        foreach ($body->data->data as $s) {
            $list[] = (object)[
                'id'   => $s->store_id,
                'name' => $s->store_name,
            ];
        }

        set_transient($key, $list, 5 * MINUTE_IN_SECONDS);

        return (object)[
            'success' => true,
            'data'    => $list,
        ];
    }

    /*-----------------------------------------
    | CREATE STORE
    | Docs: POST {base_url}/aladdin/api/v1/stores
    ------------------------------------------*/
    public function create_store(array $payload): stdClass
    {
        // Required fields from official docs.
        $required = [
            'name',
            'contact_name',
            'contact_number',
            'address',
            'city_id',
            'zone_id',
            'area_id',
        ];

        foreach ($required as $field) {
            if (! isset($payload[$field]) || $payload[$field] === '') {
                return (object) [
                    'success'  => false,
                    'messages' => ["Missing required field: {$field}"],
                ];
            }
        }

        // Basic length validations to match docs.
        if (strlen($payload['name']) < 3 || strlen($payload['name']) > 50) {
            return (object) [
                'success'  => false,
                'messages' => ['Store name length must be between 3 and 50 characters'],
            ];
        }

        if (strlen($payload['contact_name']) < 3 || strlen($payload['contact_name']) > 50) {
            return (object) [
                'success'  => false,
                'messages' => ['Contact name length must be between 3 and 50 characters'],
            ];
        }

        if (strlen($payload['contact_number']) !== 11) {
            return (object) [
                'success'  => false,
                'messages' => ['Contact number must be 11 characters'],
            ];
        }

        if (! empty($payload['secondary_contact']) && strlen($payload['secondary_contact']) !== 11) {
            return (object) [
                'success'  => false,
                'messages' => ['Secondary contact number must be 11 characters'],
            ];
        }

        if (strlen($payload['address']) < 15 || strlen($payload['address']) > 120) {
            return (object) [
                'success'  => false,
                'messages' => ['Address length must be between 15 and 120 characters'],
            ];
        }

        // Send request.
        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/stores',
            [
                'body' => wp_json_encode($payload),
            ]
        );

        if ($err = $this->has_errors($res)) {
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        // Expecting data.store_name per docs.
        $store_name = $body->data->store_name ?? null;

        return (object) [
            'success' => true,
            'data'    => (object) [
                'store_name' => $store_name,
                'raw'        => $body,
            ],
        ];
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


    private function map_delivery_type(\WC_Order $order): int
    {
        foreach ($order->get_shipping_methods() as $method) {
            $method_id = $method->get_method_id();

            // Adjust if you add more mappings later
            if ($method_id === 'local_pickup') {
                return 12; // Same city
            }
        }

        return 48; // Normal delivery (default)
    }


    private function calculate_order_weight(\WC_Order $order): float
    {
        $weight = 0.0;

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $product_weight = (float) $product->get_weight();
            $qty            = (int) $item->get_quantity();

            $weight += ($product_weight * $qty);
        }

        // Pathao rule: minimum 0.5 KG
        return max(0.5, round($weight, 2));
    }


    private function get_pathao_location(): array
    {
        return [
            'city' => (int) get_option('pathao_city_id', 0),
            'zone' => (int) get_option('pathao_zone_id', 0),
        ];
    }

    private function get_item_type(): int
    {
        $settings = get_option('woocommerce_pathao_settings');

        // 1 = Document, 2 = Parcel
        return (int) ($settings['item_type'] ?? 2);
    }




    /*-----------------------------------------
    | SEND ORDER TO PATHAO
    ------------------------------------------*/
    public function send_order(int $order_id): \stdClass
    {
        if (!function_exists('wc_get_order')) {
            return (object)['success' => false, 'messages' => ['WooCommerce missing']];
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return (object)['success' => false, 'messages' => ['Invalid order']];
        }

        // Prevent duplicate Pathao order
        $existing = $order->get_meta('_pathao_consignment_id');
        if ($existing) {
            return (object)[
                'success' => true,
                'messages' => ['Already sent to Pathao'],
                'consignment_id' => $existing
            ];
        }

        $settings = get_option('woocommerce_pathao_settings');
        $store_id = (int) ($settings['store'] ?? 0);

        if (!$store_id) {
            return (object)['success' => false, 'messages' => ['Store not configured']];
        }

        $location = $this->get_pathao_location();

        if (!$location['city'] || !$location['zone']) {
            return (object)[
                'success' => false,
                'messages' => ['Recipient city and zone must be configured']
            ];
        }

        $payload = [
            'store_id'          => $store_id,
            'merchant_order_id' => (string) $order_id,

            'recipient_name' => trim(
                $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()
            ),
            'recipient_phone' => $order->get_billing_phone() ?: '01700000000',
            'recipient_address' => trim(
                $order->get_shipping_address_1() . ', ' . $order->get_shipping_city()
            ),

            'delivery_type'  => $this->map_delivery_type($order),
            'item_type'      => $this->get_item_type(),
            'item_quantity'  => $order->get_item_count(),
            'item_weight'    => $this->calculate_order_weight($order),

            'recipient_city' => $location['city'],
            'recipient_zone' => $location['zone'],

            'item_description' => "WooCommerce Order #{$order_id}",

            'amount_to_collect' => $order->get_payment_method() === 'cod'
                ? (int) $order->get_total()
                : 0,
        ];

        // Validate
        $validate = $this->validate_order_payload($payload);
        if ($validate !== true) {
            return (object)['success' => false, 'messages' => [$validate]];
        }

        // Send request
        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/orders',
            ['body' => wp_json_encode($payload)]
        );

        if ($err = $this->has_errors($res)) {
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        if (empty($body->data->consignment_id)) {
            return (object)[
                'success' => false,
                'messages' => ['Consignment ID not returned'],
                'raw' => $body
            ];
        }

        // Save meta
        $order->update_meta_data('_pathao_consignment_id', $body->data->consignment_id);
        $order->update_meta_data('_pathao_order_status', $body->data->order_status ?? 'Pending');
        $order->save();

        do_action('pathao_order_created', (object)[
            'merchant_order_id' => $order_id,
            'consignment_id'    => $body->data->consignment_id,
            'order_status'      => $body->data->order_status ?? '',
        ]);

        return (object)[
            'success' => true,
            'consignment_id' => $body->data->consignment_id,
            'order_status' => $body->data->order_status ?? '',
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

        // error_log(print_r($body, true));

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
 | PRICE CALCULATION (MERCHANT)
 ------------------------------------------*/
    public function price_calculation($args)
    {
        error_log('[Pathao Price] price_calculation called');

        // Get store ID from settings
        $settings = get_option('woocommerce_pathao_settings');
        $store_id = (int) ($settings['store'] ?? 0);

        if (!$store_id) {
            return (object)[
                'success' => false,
                'messages' => ['Store ID not configured']
            ];
        }

        $body = wp_parse_args($args, [
            'store_id'        => $store_id,
            'item_type'       => 2,
            'delivery_type'   => 48,
            'item_weight'     => 0.5,
            'recipient_city'  => 0,
            'recipient_zone'  => 0,
        ]);

        //  Hard validation (Pathao requirement)
        if (!$body['recipient_city'] || !$body['recipient_zone']) {
            return (object)[
                'success' => false,
                'messages' => ['City and zone are required']
            ];
        }

        error_log('[Pathao Price] Request payload: ' . wp_json_encode($body));

        // Merchant endpoint + SOURCE header
        $token = $this->ensure_access_token();
        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/merchant/price-plan',
            [
                'body' => wp_json_encode($body),
            ]
        );


        // Transport / API errors
        if ($err = $this->has_errors($res)) {
            error_log('[Pathao Price] API error: ' . wp_json_encode($res, true));
            return $err;
        }

        $raw_body = wp_remote_retrieve_body($res);
        error_log('[Pathao Price] Raw response: ' . $raw_body);

        $d = json_decode($raw_body);

        if (empty($d->data)) {
            return (object)[
                'success' => false,
                'messages' => ['Price data missing'],
                'raw' => $d,
            ];
        }

        return (object)[
            'success' => true,
            'data' => (object)[
                'price'              => $d->data->price ?? 0,
                'discount'           => $d->data->discount ?? 0,
                'promo_discount'     => $d->data->promo_discount ?? 0,
                'plan_id'            => $d->data->plan_id ?? null,
                'cod_enabled'        => $d->data->cod_enabled ?? 0,
                'cod_percentage'     => $d->data->cod_percentage ?? 0,
                'additional_charge'  => $d->data->additional_charge ?? 0,
                'final_price'        => $d->data->final_price ?? 0,
            ],
        ];
    }

    /*-----------------------------------------
    | TOKEN GENERATION (PASSWORD GRANT)
    | Docs: POST {base_url}/aladdin/api/v1/issue-token
    ------------------------------------------*/
    public function generate_tokens($args)
    {
        $client_id     = get_option('pathao_client_id');
        $client_secret = get_option('pathao_client_secret');

        $username = $args['username'] ?? '';
        $password = $args['password'] ?? '';
        $grant    = $args['grant_type'] ?? 'password';

        if (!$client_id || !$client_secret || !$username || !$password) {
            return (object)[
                'success'  => false,
                'messages' => ['client_id, client_secret, username and password are required to issue token'],
            ];
        }

        $url = $this->get_base_url() . 'aladdin/api/v1/issue-token';

        $body = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => $grant,
            'username'      => $username,
            'password'      => $password,
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
                'success'  => false,
                'messages' => [$response->get_error_message()],
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return (object)[
                'success'  => false,
                'messages' => ['Token issue failed'],
            ];
        }

        $d = json_decode(wp_remote_retrieve_body($response));

        // Persist tokens for later use (as per docs: save access token & refresh token)
        if (!empty($d->access_token)) {
            update_option('pathao_access_token', $d->access_token);
        }
        if (!empty($d->refresh_token)) {
            update_option('pathao_refresh_token', $d->refresh_token);
        }

        return (object)[
            'success' => true,
            'data'    => (object)[
                'access_token'  => $d->access_token ?? '',
                'refresh_token' => $d->refresh_token ?? '',
            ],
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
