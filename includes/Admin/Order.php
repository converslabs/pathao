<?php

namespace SpringDevs\Pathao\Admin;

use WC_Order;

/**
 * Admin order related stuffs.
 */
class Order {

	/**
	 * The class contructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'pathao_order_created', array( $this, 'store_log_after_creation' ) );

		// order columns.
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_custom_columns' ) );
		add_filter( 'woocommerce_shop_order_list_table_columns', array( $this, 'add_custom_columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_custom_columns_data' ), 10, 2 );
		add_action( 'init', array( $this, 'load_hpos_hooks' ) );
	}

	/**
	 * Load HPOS hooks here else `wc_get_page_screen_id` isn't available.
	 */
	public function load_hpos_hooks() {
		add_action( 'manage_' . wc_get_page_screen_id( 'shop_order' ) . '_custom_column', array( $this, 'add_custom_columns_data' ), 10, 2 );
	}

	/**
	 * Add Custom Column to Orders Table.
	 *
	 * @param array $columns Columns.
	 *
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		$columns['sdevs_pathao_order_column'] = __( 'Pathao', 'sdevs_pathao' );
		return $columns;
	}

	/**
	 * Add Custom Column Data.
	 *
	 * @param string        $column Column ID.
	 * @param int|\WC_Order $post_id post_id or Order Obj.
	 */
	public function add_custom_columns_data( $column, $post_id ) {
		if ( 'sdevs_pathao_order_column' === $column ) :
			// check if post_id is order object.
			$order = $post_id;
			if ( 'object' !== gettype( $post_id ) ) {
				$order = wc_get_order( $post_id );
			}
			$consignment_id = $order->get_meta( '_pathao_consignment_id' );
			$status         = $order->get_meta( '_pathao_order_status' );

			?>
			<div>
				<?php if ( $consignment_id ) : ?>
					<p>
						<code>
							<?php echo esc_html( $consignment_id ); ?>
						</code>
					</p>
					<p><b>(<?php echo esc_html( $status ); ?>)</b></p>
				<?php else : ?>
					<p>-</p>
				<?php endif; ?>
			</div>
			<?php
		endif;
	}

	/**
	 * Store log after pathao order created.
	 *
	 * @param mixed $res Response.
	 */
	public function store_log_after_creation( $res ) {
		global $wpdb;
		$log_table = $wpdb->prefix . 'pathao_logs';
		$wpdb->insert(
			$log_table,
			array(
				'order_id'          => (int) $res->merchant_order_id,
				'consignment_id'    => $res->consignment_id,
				'order_status'      => $res->order_status,
				'order_status_slug' => $res->order_status,
				'reason'            => 'Pathao Order created & it\'s pending.',
				'updated_at'        => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Register metaboxes under order details page.
	 */
	public function register_meta_boxes() {
		$screen = sdevs_wc_order_hpos_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		// phpcs:ignore
		if (  isset( $_GET['action'] ) && 'edit' === $_GET['action']) {
			add_meta_box(
				'pathao_order_wc',
				__( 'Pathao Shipping', 'sdevs_pathao' ),
				array( $this, 'pathao_shipping' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Display order shipping form and details.
	 */
	public function pathao_shipping() {
		$order = wc_get_order( sdevs_wc_order_hpos_enabled() ? esc_html( $_GET['id'] ) : get_the_ID() );
		if ( ! $order ) {
			return;
		}
		$consignment_id = $order->get_meta( '_pathao_consignment_id' );
		$status         = $order->get_meta( '_pathao_order_status' );

		if ( $consignment_id && ! in_array( $status, array( 'Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed' ), true ) ) {
			$this->display_pathao_details( $order );
		} elseif ( $consignment_id && in_array( $status, array( 'Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed' ), true ) ) {
			$this->display_pathao_details( $order );
			$this->pathao_shipping_form( $order );
		} else {
			$this->pathao_shipping_form( $order );
		}
	}

	/**
	 *  Display shipping details.
	 *
	 *  @param WC_Order $order Current order.
	 */
	public function display_pathao_details( WC_Order $order ) {
		$consignment_id = $order->get_meta( '_pathao_consignment_id' );
		$delivery_fee   = $order->get_meta( '_pathao_delivery_fee' );
		$order_status   = $order->get_meta( '_pathao_order_status' );
		include 'views/pathao-shipping-details.php';
	}

	/**
	 * Display shipping form.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function pathao_shipping_form( WC_Order $order ) {
		$order_id = $order->get_id();
		wp_localize_script(
			'pathao_admin_script',
			'pathao_admin_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'order_id' => $order_id,
			)
		);
		wp_enqueue_style( 'pathao_toast_styles' );
		wp_enqueue_script( 'pathao_toast_script' );
		wp_enqueue_script( 'pathao_admin_script' );

		$amount = is_sdevs_pathao_pro_activated() && $order->has_status( substr( sdevs_pathao_settings( 'paid_order_status', 'wc-paid' ), 3 ) ) ? 0 : $order->get_total();

		$all_totals       = sdevs_pathao_get_totals_from_items( $order );
		$total_weight     = $all_totals->weight;
		$item_description = $all_totals->item_description;
		$status           = $order->get_meta( '_pathao_order_status' );

		include 'views/pathao-shipping.php';
	}
}
