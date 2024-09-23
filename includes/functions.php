<?php
/**
 * All our plugins custom functions.
 *
 * @since 1.0.0
 *
 * phpcs:ignore Squiz.Commenting.FileComment.MissingPackageTag
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Get pathao base URL.
 *
 * @return string
 */
function sdevs_pathao_base_url(): string {
	return get_option( 'pathao_sandbox_mode' ) ? 'https://courier-api-sandbox.pathao.com/' : 'https://api-hermes.pathao.com/';
}

/**
 * Get data from pathao server.
 *
 * @param string $endpoint Endpoint.
 *
 * @return [object]
 */
function sdevs_get_pathao_data( string $endpoint ) {
	$base_url     = sdevs_pathao_base_url();
	$access_token = get_option( 'pathao_access_token' );

	if ( ! $access_token ) {
		return (object) array(
			'type'  => 'failed',
			'error' => 'Please generate access token to use pathao plugin !!',
		);
	}

	$res = wp_remote_get(
		$base_url . $endpoint,
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			),
		)
	);

	$data     = json_decode( wp_remote_retrieve_body( $res ) );
	$res_code = wp_remote_retrieve_response_code( $res );

	if ( 200 === $res_code ) {
		return $data;
	}

	return (object) array(
		'type'     => 'failed',
		'res_code' => $res_code,
		'body'     => $data,
	);
}

/**
 * Send request on pathao server.
 *
 * @param string $endpoint Endpoint.
 * @param array  $body Body.
 *
 * @return mixed|object [object]
 */
function sdevs_send_pathao_data( string $endpoint, array $body ) {
	$base_url     = sdevs_pathao_base_url();
	$access_token = get_option( 'pathao_access_token' );

	if ( ! $access_token ) {
		return (object) array(
			'type'  => 'failed',
			'error' => 'Please generate access token to use pathao plugin !!',
		);
	}

	$res = wp_remote_post(
		$base_url . $endpoint,
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			),
			'body'    => $body,
		)
	);

	$data     = json_decode( wp_remote_retrieve_body( $res ) );
	$res_code = wp_remote_retrieve_response_code( $res );

	if ( 200 === $res_code ) {
		return $data;
	}

	return (object) array(
		'type'     => 'failed',
		'res_code' => $res_code,
		'body'     => $data,
	);
}

/**
 * Check if pathao pro activated.
 *
 * @return bool
 */
function is_sdevs_pathao_pro_activated(): bool {
	return class_exists( 'Sdevs_Pathao_Pro' );
}

/**
 * Check if pathao shipping enabled.
 *
 * @return bool
 */
function is_pathao_shipping_enabled(): bool {
	$settings = get_option( 'woocommerce_pathao_settings' );

	return $settings && isset( $settings['enabled'] ) && 'yes_with_pathao' === $settings['enabled'];
}

/**
 * Get current store ID.
 */
function sdevs_pathao_store_id() {
	$settings = get_option( 'woocommerce_pathao_settings' );

	if ( $settings && isset( $settings['store'] ) ) {
		return $settings['store'];
	}

	return false;
}

/**
 * Get settings by key.
 *
 * @param string $key Key.
 * @param mixed  $default_value Default value.
 *
 * @return mixed
 */
function sdevs_pathao_settings( string $key, $default_value = false ) {
	$settings = get_option( 'woocommerce_pathao_settings' );

	return $settings && is_array( $settings ) && isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
}

/**
 * Get total weight, quantity, description from order.
 *
 * @param \WC_Order $order Order Object.
 *
 * @return object
 */
function sdevs_pathao_get_totals_from_items( \WC_Order $order ) {
	$total_weight     = 0;
	$quantity         = 0;
	$item_description = array();
	foreach ( $order->get_items() as $order_item ) {
		$product = $order_item->get_product();
		if ( ! $product->is_virtual() ) {
			array_push( $item_description, "{$order_item->get_name()}(x{$order_item->get_quantity()})" );
			$quantity     += $order_item['quantity'];
			$total_weight += empty( $product->get_weight() ) ? 0 : intval( $product->get_weight() ) * $order_item['quantity'];
		}
	}
	$total_weight     = floatval( max( $total_weight, 0.5 ) );
	$item_description = implode( ', ', $item_description );

	return (object) array(
		'weight'           => 0 === $total_weight ? apply_filters( 'sdevs_pathao_default_weight', 0.5 ) : $total_weight,
		'item_description' => $item_description,
		'quantity'         => $quantity,
	);
}

/**
 * Check if HPOS enabled.
 */
function sdevs_wc_order_hpos_enabled() {
	return function_exists( 'wc_get_container' ) ? wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() : false;
}
