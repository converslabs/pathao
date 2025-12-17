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

	return $settings && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
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
