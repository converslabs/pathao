<?php

use SpringDevs\Pathao\Facades\PathaoAPI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Pathao shipping init.
 */
function sdevs_pathao_shipping_method_init() {
	if ( ! class_exists( 'SDEVS_Pathao_Method' ) ) {
		/**
		 * Pathao shipping class.
		 */
		class SDEVS_Pathao_Method extends WC_Shipping_Method {

			/**
			 * Constructor for your shipping class
			 *
			 * @access public
			 * @return void
			 */
			public function __construct() {
				$this->id                 = 'pathao';
				$this->method_title       = __( 'Pathao', 'sdevs_pathao' );
				$this->method_description = __( 'Implement Pathao within WooCommerce fully effective way.', 'sdevs_pathao' );

				$this->availability = 'including';
				$this->countries    = array( 'BD' );

				$this->enabled = is_sdevs_pathao_pro_activated() && in_array(
					$this->get_option( 'enabled' ),
					array(
						'yes',
						'yes_as_popup',
					),
					true
				) ? 'yes' : 'no';
				$this->title   = $this->get_option( 'title' );
				$this->init();
			}

			/**
			 * Init your settings
			 *
			 * @access public
			 * @return void
			 */
			public function init() {
				// Load the settings API.
				$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
				$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

				// Save settings in admin if you have any defined.
				add_action(
					'woocommerce_update_options_shipping_' . $this->id,
					array(
						$this,
						'process_admin_options',
					)
				);
			}

			/**
			 * Settings fields initialization.
			 */
			public function init_form_fields() {
				$stores          = PathaoAPI::get_stores();
				$stores          = $stores->success ? $stores->data : array();
				$dropdown_stores = array();
				foreach ( $stores as $store ) {
					$dropdown_stores[ $store->id ] = $store->name;
				}

				$order_statuses    = array();
				$wc_order_statuses = wc_get_order_statuses();
				foreach ( $wc_order_statuses as $status => $status_name ) {
					$order_statuses[ $status ] = $status_name;
				}
				if ( $this->get_option( 'store' ) === '' && count( $dropdown_stores ) > 0 ) {
					$this->update_option( 'enabled', 'yes' );
					$this->update_option( 'title', 'Pathao' );
					$this->update_option( 'store', array_key_first( $dropdown_stores ) );
					$this->update_option( 'area_field', 'display_required' );
					$this->update_option( 'delivery_type', 48 );
					$this->update_option( 'default_weight', 0.5 );
					$this->update_option( 'custom_order_status', 'yes' );
					$this->update_option( 'paid_order_status', 'wc-paid' );
					$this->update_option( 'at_the_sorting_hub_status', 'wc-in-shipment' );
					$this->update_option( 'pickup_failed_status', 'wc-processing' );
					$this->update_option( 'delivered_status', 'wc-completed' );
					$this->update_option( 'return_status', 'wc-processing' );
					$this->update_option( 'on_hold_status', 'wc-on-hold' );
				} elseif ( count( $dropdown_stores ) > 0 && ! array_key_exists( $this->get_option( 'store' ), $dropdown_stores ) ) {
					$this->update_option( 'store', array_key_first( $dropdown_stores ) );
				}

				$this->form_fields = array(
					'enabled'                   => array(
						'title'       => __( 'Enable', 'sdevs_pathao' ),
						'type'        => 'select',
						'description' => __( 'Enable this shipping.', 'sdevs_pathao' ),
						'options'     => array(
							'yes'            => 'Enable',
							'yes_as_carrier' => 'Enable as Carrier',
							'yes_as_popup'   => 'Enable as Popup Checkout',
							'no'             => 'Disable',
						),
						'default'     => is_sdevs_pathao_pro_activated() ? 'yes' : 'no',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
					'title'                     => array(
						'title'       => __( 'Title', 'sdevs_pathao' ),
						'type'        => 'text',
						'description' => __( 'Title to be display on site', 'sdevs_pathao' ),
						'default'     => 'Pathao',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
						'required'    => true,
					),
					'store'                     => array(
						'title'       => __( 'Store', 'sdevs_pathao' ),
						'type'        => 'select',
						'class'       => 'wc-enhanced-select',
						'options'     => $dropdown_stores,
						'disabled'    => count( $dropdown_stores ) === 0,
						'description' => count( $dropdown_stores ) === 0 ? __( 'Please generate token at first !', 'sdevs_pathao' ) : null,
					),
					'area_field'                => array(
						'title'    => __( 'Area Field', 'sdevs_pathao' ),
						'type'     => 'select',
						'options'  => array(
							'display_required'    => __( 'Display & Required', 'sdevs_pathao' ),
							'display_no_required' => __( 'Display & Not Required', 'sdevs_pathao' ),
							'not_display'         => __( 'No Display', 'sdevs_pathao' ),
						),
						'default'  => 'display_required',
						'disabled' => ! is_sdevs_pathao_pro_activated(),
					),
					'delivery_type'             => array(
						'title'    => __( 'Delivery Type', 'sdevs_pathao' ),
						'type'     => 'select',
						'options'  => array(
							48 => __( 'Normal', 'sdevs_pathao' ),
							12 => __( 'On Demand', 'sdevs_pathao' ),
						),
						'default'  => 48,
						'disabled' => ! is_sdevs_pathao_pro_activated(),
					),
					'default_weight'            => array(
						'title'             => __( 'Default Item Weight (KG)', 'sdevs_pathao' ),
						'type'              => 'number',
						'custom_attributes' => array(
							'step'     => '0.1',
							'min'      => '0.1',
							'max'      => '200.0',
							'required' => 'required',
						),
						'description'       => __( 'This value will be replaced when total weight of order is 0 ! Minimum 0.1 KG to Maximum 200 KG', 'sdevs_pathao' ),
						'default'           => 0.5,
						'disabled'          => ! is_sdevs_pathao_pro_activated(),
					),
					'custom_order_status'       => array(
						'title'       => __( 'Custom Order Statuses', 'sdevs_pathao' ),
						'type'        => 'checkbox',
						'options'     => $order_statuses,
						'description' => __( 'Enable helpfull statuses for order (In shipment, Paid, Shipment failed).', 'sdevs_pathao' ),
						'default'     => 'yes',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
					'paid_order_status'         => array(
						'title'       => __( 'Order Status For Paid', 'sdevs_pathao' ),
						'type'        => 'select',
						'options'     => $order_statuses,
						'description' => __( 'When order is paid, the `Amount to Collect` will be 0.', 'sdevs_pathao' ),
						'default'     => 'wc-paid',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
					'at_the_sorting_hub_status' => array(
						'title'       => __( 'Order Status For At the Sorting HUB', 'sdevs_pathao' ),
						'type'        => 'select',
						'options'     => $order_statuses,
						'description' => __( 'When Pathao order status is At the Sorting HUB, WooCommerce Order status will be set this status !', 'sdevs_pathao' ),
						'default'     => 'wc-in-shipment',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
					'pickup_failed_status'      => array(
						'title'       => __( 'Order Status For Pickup Failed', 'sdevs_pathao' ),
						'type'        => 'select',
						'options'     => $order_statuses,
						'description' => __( 'When Pathao order status is Pickup Failed, WooCommerce Order status will be set this status !', 'sdevs_pathao' ),
						'default'     => 'wc-processing',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
					'delivered_status'          => array(
						'title'       => __( 'Order Status For Delivered', 'sdevs_pathao' ),
						'type'        => 'select',
						'options'     => $order_statuses,
						'description' => __( 'When Pathao order status is Delivered, WooCommerce Order status will be set this status !', 'sdevs_pathao' ),
						'default'     => 'wc-completed',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
					'return_status'             => array(
						'title'       => __( 'Order Status For Return', 'sdevs_pathao' ),
						'type'        => 'select',
						'options'     => $order_statuses,
						'description' => __( 'When Pathao order status is Return, WooCommerce Order status will be set this status !', 'sdevs_pathao' ),
						'default'     => 'wc-processing',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
					'on_hold_status'            => array(
						'title'       => __( 'Order Status For On_Hold', 'sdevs_pathao' ),
						'type'        => 'select',
						'options'     => $order_statuses,
						'description' => __( 'When Pathao order status is On_Hold, WooCommerce Order status will be set this status !', 'sdevs_pathao' ),
						'default'     => 'wc-on-hold',
						'disabled'    => ! is_sdevs_pathao_pro_activated(),
					),
				);
			}

			/**
			 * Calculate shipping function.
			 *
			 * @access public
			 *
			 * @param array $package Package.
			 *
			 * @return void
			 */
			public function calculate_shipping( $package = array() ) {
				do_action( 'pathao_calculate_shipping', $package, $this );
			}
		}
	}
}

add_action( 'woocommerce_shipping_init', 'sdevs_pathao_shipping_method_init' );
