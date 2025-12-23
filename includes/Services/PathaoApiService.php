<?php

namespace SpringDevs\Pathao\Services;

use stdClass;

class PathaoApiService
{

	
	/*-----------------------------------------
	| BASE URL
	------------------------------------------*/
	private function get_base_url(): string {
		return get_option( 'pathao_sandbox_mode' )
			? 'https://courier-api-sandbox.pathao.com/'
			: 'https://api-hermes.pathao.com/';
	}

	/*-----------------------------------------
	| ACCESS & REFRESH TOKENS
	| - Used for authenticated merchant calls
	| - Does NOT call authenticated wrapper when
	|   (re)issuing tokens to avoid recursion.
	------------------------------------------*/
	private function ensure_access_token() {
		$token = get_option( 'pathao_access_token' );

		if ( ! empty( $token ) ) {
			return $token;
		}

		// Try to refresh using stored refresh_token.
		$client_id     = get_option( 'pathao_client_id' );
		$client_secret = get_option( 'pathao_client_secret' );
		$refresh_token = get_option( 'pathao_refresh_token' );

		if ( ! $client_id || ! $client_secret || ! $refresh_token ) {
			return false;
		}

		$refreshed = $this->refresh_tokens();

		if ( $refreshed && ! empty( $refreshed->success ) ) {
			update_option( 'pathao_access_token', $refreshed->data->access_token );
			update_option( 'pathao_refresh_token', $refreshed->data->refresh_token );

			return $refreshed->data->access_token;
		}

		return false;
	}

	/*-----------------------------------------
	| REQUEST WRAPPER (AUTO TOKEN REFRESH)
	| - For endpoints that REQUIRE Bearer token
	------------------------------------------*/
	private function request( $func, string $path, array $args = [] ) {
		$token = $this->ensure_access_token();

		if ( ! $token ) {
			return array( 'error' => 'token_missing' );
		}

		$default_headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		);

		$final_args = array_merge( array( 'headers' => $default_headers ), $args );

		$url = $this->get_base_url() . ltrim( $path, '/' );

		$response = $func( $url, $final_args );

		// Pathao expired token → 401, try once with refreshed token.
		if ( wp_remote_retrieve_response_code( $response ) === 401 ) {
			$new = $this->refresh_tokens();
			if ( $new && ! empty( $new->success ) ) {
				update_option( 'pathao_access_token', $new->data->access_token );
				update_option( 'pathao_refresh_token', $new->data->refresh_token );

				$final_args['headers']['Authorization'] = 'Bearer ' . $new->data->access_token;

				$response = $func( $url, $final_args );
			}
		}

		return $response;
	}

	/*-----------------------------------------
	| ERROR HANDLER
	| - Reused for both token and data endpoints
	------------------------------------------*/
	private function has_errors( $res ) {
		if ( is_wp_error( $res ) ) {
			$data            = new stdClass();
			$data->success   = false;
			$data->messages  = array( $res->get_error_message() );
			return $data;
		}

		$code = wp_remote_retrieve_response_code( $res );
		$data = new stdClass();

		if ( 401 === $code ) {
			$data->success  = false;
			$data->messages = array( 'Unauthorized access token' );
			return $data;
		}

		if ( 422 === $code ) {
			$body     = json_decode( wp_remote_retrieve_body( $res ) );
			$messages = array();

			if ( ! empty( $body->errors ) ) {
				foreach ( $body->errors as $err ) {
					if ( is_array( $err ) ) {
						$messages = array_merge( $messages, $err );
					} else {
						$messages[] = $err;
					}
				}
			}

			$data->success  = false;
			$data->messages = $messages;
			return $data;
		}

		if ( $code < 200 || $code > 299 ) {
			$data->success  = false;
			$data->messages = array( 'Something went wrong' );
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
	public function get_cities() {
		$transient_key = '_sdevs_pathao_cities';

		if ( $cached = $this->has_transient( $transient_key ) ) {
			return $cached;
		}

		$res = $this->request( 'wp_remote_get', 'aladdin/api/v1/cities' );

		if ( $err = $this->has_errors( $res ) ) {
			return $err;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ) );

		if ( ! isset( $body->data->data ) ) {
			return (object) array(
				'success'  => false,
				'messages' => array( 'Cities data malformed' ),
			);
		}

		$list = array();
		foreach ( $body->data->data as $c ) {
			$list[] = (object) array(
				'id'   => $c->city_id,
				'name' => $c->city_name,
			);
		}

		set_transient( $transient_key, $list, 12 * HOUR_IN_SECONDS );

		return (object) array(
			'success' => true,
			'data'    => $list,
		);
	}

    /*-----------------------------------------
    | GET ZONES
    ------------------------------------------*/
	public function get_zones( $city_id ) {
		$res = $this->request(
			'wp_remote_get',
			"aladdin/api/v1/zones?city_id={$city_id}"
		);

		if ( $err = $this->has_errors( $res ) ) {
			return $err;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ) );

		if ( ! isset( $body->data->data ) ) {
			return array();
		}

		$out = array();
		foreach ( $body->data->data as $z ) {
			$out[] = array(
				'id'   => $z->zone_id,
				'name' => $z->zone_name,
			);
		}

		return $out;
	}

    /*-----------------------------------------
    | GET AREAS
    ------------------------------------------*/
	public function get_areas( int $zone_id ): stdClass {
		$key = "_sdevs_pathao_zone_{$zone_id}_areas";

		if ( $c = $this->has_transient( $key ) ) {
			return $c;
		}

		$res = $this->request(
			'wp_remote_get',
			"aladdin/api/v1/zones/{$zone_id}/area-list"
		);

		if ( $err = $this->has_errors( $res ) ) {
			return $err;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ) );

		$list = array();
		foreach ( $body->data->data as $a ) {
			$list[] = (object) array(
				'id'   => $a->area_id,
				'name' => $a->area_name,
			);
		}

		set_transient( $key, $list, 12 * HOUR_IN_SECONDS );

		return (object) array(
			'success' => true,
			'data'    => $list,
		);
	}

    /*-----------------------------------------
    | GET STORES
    ------------------------------------------*/
	public function get_stores(): stdClass {
		$key = '_pathao_store_id';

		if ( $c = $this->has_transient( $key ) ) {
			return $c;
		}

		$res = $this->request( 'wp_remote_get', 'aladdin/api/v1/stores' );

		if ( $err = $this->has_errors( $res ) ) {
			return $err;
		}

		$body = json_decode( wp_remote_retrieve_body( $res ) );

		if ( ! isset( $body->data->data ) || ! is_array( $body->data->data ) ) {
			return (object) array(
				'success'  => false,
				'messages' => array( 'Stores malformed' ),
			);
		}

		$list = array();
		foreach ( $body->data->data as $s ) {
			$list[] = (object) array(
				'id'   => $s->store_id,
				'name' => $s->store_name,
			);
		}

		set_transient( $key, $list, 5 * MINUTE_IN_SECONDS );

		return (object) array(
			'success' => true,
			'data'    => $list,
		);
	}

    /*-----------------------------------------
    | ORDER PAYLOAD VALIDATOR
    ------------------------------------------*/
    private function validate_order_payload($p)
    {
        return true;
    }

    /*-----------------------------------------
    | SEND ORDER TO PATHAO
    ------------------------------------------*/
    public function send_order(int $order_id): stdClass
    { 

        if (!function_exists('wc_get_order')) { 
            return (object)['success' => false, 'messages' => ['WooCommerce missing']];
        }

        $order = wc_get_order($order_id);
        if (!$order) { 
            return (object)['success' => false, 'messages' => ['Invalid order']];
        }

        /* Prevent duplicate Pathao order */
        $existing = $order->get_meta('_pathao_consignment_id');
        if (!empty($existing)) { 
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
        /* Validate payload */
        $validate = $this->validate_order_payload($payload);
        if ($validate !== true) { 
            return (object)['success' => false, 'messages' => [$validate]];
        }

        /* Send API request */
        $res = $this->request(
            'wp_remote_post',
            'aladdin/api/v1/orders',
            ['body' => json_encode($payload)]
        );

        if ($err = $this->has_errors($res)) { 
            return $err;
        }

        /* Decode response */
        $body = json_decode(wp_remote_retrieve_body($res));  
        /* SAFELY check consignment_id */
        if (
            empty($body->data) ||
            empty($body->data->consignment_id)
        ) { 
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
	| PRICE CALCULATION
	------------------------------------------*/
	public function price_calculation( $args ) {
		$body = wp_parse_args(
			$args,
			array(
				'store_id'      => pathao_store_id(),
				'item_type'     => 2,
				'delivery_type' => 48,
			)
		);

		$res = $this->request(
			'wp_remote_post',
			'aladdin/api/v1/merchant/price-plan',
			array(
				'body' => wp_json_encode( $body ),
			)
		);

		if ( $err = $this->has_errors( $res ) ) {
			return $err;
		}

		$d = json_decode( wp_remote_retrieve_body( $res ) );

		return (object) array(
			'success' => true,
			'data'    => (object) array(
				'price'       => $d->data->price,
				'cod_enabled' => $d->data->cod_enabled,
			),
		);
	}

	/*-----------------------------------------
	| TOKEN GENERATION (grant_type=password)
	| Calls /aladdin/api/v1/issue-token WITHOUT
	| Authorization header, per Pathao docs.
	------------------------------------------*/
	public function generate_tokens( $args ) {
		$body = wp_parse_args(
			$args,
			array(
				'grant_type' => 'password',
			)
		);

		$url = $this->get_base_url() . 'aladdin/api/v1/issue-token';

		$res = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( $err = $this->has_errors( $res ) ) {
			return $err;
		}

		$d = json_decode( wp_remote_retrieve_body( $res ) );

		return (object) array(
			'success' => true,
			'data'    => (object) array(
				'access_token'  => $d->access_token,
				'refresh_token' => $d->refresh_token,
			),
		);
	}

	/*-----------------------------------------
	| TOKEN REFRESH (grant_type=refresh_token)
	| Calls /aladdin/api/v1/issue-token WITHOUT
	| Authorization header, per Pathao docs.
	------------------------------------------*/
	public function refresh_tokens() {
		$client_id     = get_option( 'pathao_client_id' );
		$client_secret = get_option( 'pathao_client_secret' );
		$refresh_token = get_option( 'pathao_refresh_token' );

		if ( ! $client_id || ! $client_secret || ! $refresh_token ) {
			return (object) array(
				'success'  => false,
				'messages' => array( 'Token info missing' ),
			);
		}

		$body = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'grant_type'    => 'refresh_token',
			'refresh_token' => $refresh_token,
		);

		$url = $this->get_base_url() . 'aladdin/api/v1/issue-token';

		$res = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( $err = $this->has_errors( $res ) ) {
			return $err;
		}

		$d = json_decode( wp_remote_retrieve_body( $res ) );

		return (object) array(
			'success' => true,
			'data'    => (object) array(
				'access_token'  => $d->access_token,
				'refresh_token' => $d->refresh_token,
			),
		);
	}
	
}

