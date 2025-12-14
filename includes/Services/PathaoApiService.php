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
            error_log("[Pathao Debug] No access token found — refreshing...");
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
            error_log("[Pathao Debug] TOKEN ERROR: cannot refresh token");
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
            error_log("[Pathao Debug] 401 detected — refreshing token");

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
            error_log("[Pathao Debug] get_cities(): Using cached");
            return $cached;
        }

        $res = $this->request('wp_remote_get', 'aladdin/api/v1/cities');

        if ($err = $this->has_errors($res)) {
            error_log("[Pathao Debug] /cities ERROR: " . print_r($err, true));
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        error_log("[Pathao Debug] /cities RAW: " . json_encode($body));

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
        $key = '_sdevs_pathao_stores';

        if ($c = $this->has_transient($key)) {
            error_log("[Pathao Debug] get_stores(): Using cached");
            return $c;
        }

        $res = $this->request('wp_remote_get', 'aladdin/api/v1/stores');

        if ($err = $this->has_errors($res)) {
            error_log("[Pathao Debug] Stores ERROR: " . print_r($err, true));
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        error_log("[Pathao Debug] /stores RAW: " . json_encode($body));

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

        error_log("[Pathao Debug] Parsed Stores: " . print_r($list, true));

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
            'recipient_phone',
            'recipient_address',
            'delivery_type',
            'item_type',
            'item_quantity',
            'item_weight',
            'amount_to_collect'
        ];

        error_log("[Pathao Debug] VALIDATING PAYLOAD: " . json_encode($p));

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
        error_log("-----------------------------------------------------------");
        error_log("[Pathao Debug] send_order(): ORDER ID {$order_id}");

        if (!function_exists('wc_get_order')) {
            return (object)['success' => false, 'messages' => ['WooCommerce missing']];
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return (object)['success' => false, 'messages' => ['Invalid order']];
        }

        /* Extract Fields */
        $store_id = get_option('sdevs_pathao_store_id');
        $recipient_name = trim($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name());
        $recipient_phone = $order->get_billing_phone();
        $recipient_address = $order->get_shipping_address_1() . ', ' . $order->get_shipping_city();

        $payload = [
            'store_id'          => (int)$store_id,
            'merchant_order_id' => (string)$order_id,
            'recipient_name'    => $recipient_name,
            'recipient_phone'   => $recipient_phone,
            'recipient_address' => $recipient_address,
            'delivery_type'     => 48,
            'item_type'         => 2,
            'special_instruction' => $order->get_customer_note() ?: '',
            'item_quantity'     => $order->get_item_count(),
            'item_weight'       => "0.5",
            'item_description'  => "WooCommerce Order #{$order_id}",
            'amount_to_collect' =>
                $order->get_payment_method() === 'cod'
                ? (int)$order->get_total()
                : 0
        ];

        /* Validate */
        $validate = $this->validate_order_payload($payload);
        if ($validate !== true) {
            error_log("[Pathao Debug] PAYLOAD FAILED: {$validate}");
            return (object)['success' => false, 'messages' => [$validate]];
        }

        error_log("[Pathao Debug] PAYLOAD OK: " . json_encode($payload));

        /* Send */
        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/orders',
            ['body' => json_encode($payload)]
        );

        if ($err = $this->has_errors($res)) {
            error_log("[Pathao Debug] API ERR: " . print_r($err, true));
            return $err;
        }

        $body = json_decode(wp_remote_retrieve_body($res));

        error_log("[Pathao Debug] ORDER RESPONSE: " . json_encode($body));

        return (object)['success' => true, 'data' => $body];
    }

    /*-----------------------------------------
    | PRICE CALCULATION
    ------------------------------------------*/
    public function price_calculation($args)
    {
        $body = wp_parse_args($args, [
            'store_id'      => sdevs_pathao_store_id(),
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

        $body = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type'    => 'refresh_token',
        ];

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
	
}

