<?php

namespace SpringDevs\Pathao\Admin;

use WC_Order;

if (! defined('ABSPATH')) {
    exit;
}

class Order
{
    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('pathao_order_created', array($this, 'store_log_after_creation'));

        add_filter('manage_edit-shop_order_columns', array($this, 'add_custom_columns'));
        add_filter('woocommerce_shop_order_list_table_columns', array($this, 'add_custom_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'add_custom_columns_data'), 10, 2);
        add_action('init', array($this, 'load_hpos_hooks'));
    }

    public function load_hpos_hooks()
    {
        add_action('manage_' . \wc_get_page_screen_id('shop_order') . '_custom_column', array($this, 'add_custom_columns_data'), 10, 2);
    }

    public function add_custom_columns($columns)
    {
        $columns['sdevs_pathao_order_column'] = __('xx Pathao', 'integration-of-pathao-for-woocommerce');
        return $columns;
    }

    /**
     * Output column content. $order may be WC_Order or post ID depending on hook.
     *
     * @param string $column
     * @param mixed  $order  WC_Order or post ID
     */
    public function add_custom_columns_data($column, $order)
    {
        if ($column !== 'sdevs_pathao_order_column') {
            return;
        }

        // Normalize to WC_Order
        if ($order instanceof WC_Order) {
            $order_obj = $order;
            $order_id  = $order_obj->get_id();
        } else {
            $order_id = absint($order);
            $order_obj = wc_get_order($order_id);
            if (!$order_obj) {
                return;
            }
        }

        // Get meta as in your original logic
        $consignment_id = $order_obj->get_meta('_pathao_consignment_id', true);

        // 1 Prepare button (shown when no consignment ID)
        $button = sprintf(
            '<button class="ptc-open-modal-button" data-order-id="%s">%s</button>',
            $order_id,
            __('Send with Pathao', 'integration-of-pathao-for-woocommerce')
        );

        // 2️If consignment exists
        if (!empty($consignment_id)) {

            // If it is NOT the EMPTY FLAG → show link
            if ($consignment_id !== PTC_EMPTY_FLAG) {
                $url = get_ptc_merchant_panel_base_url() . '/courier/orders/' . $consignment_id;

                echo sprintf(
                    '<a href="%s" class="order-view" target="_blank">%s</a>',
                    esc_url($url),
                    esc_html($consignment_id)
                );
                return;
            }

            // If EMPTY FLAG → show ---
            echo '---';
            return;
        }

        // 3️ When no consignment → show button
        echo '<span class="ptc-assign-area">' . $button . '</span>';
    }


    /**
     * Insert a log row when a Pathao order is created.
     *
     * @param object|array $res
     */
    public function store_log_after_creation($res)
    {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pathao_logs';

        error_log('[Pathao Debug] store_log_after_creation called with: ' . print_r($res, true));

        $order_id      = isset($res->merchant_order_id) ? (int) $res->merchant_order_id : (isset($res->order_id) ? (int) $res->order_id : 0);
        $consignment_id = isset($res->consignment_id) ? sanitize_text_field($res->consignment_id) : '';
        $order_status   = isset($res->order_status) ? sanitize_text_field($res->order_status) : '';

        //error_log("[Pathao Debug] Parsed values - order_id: $order_id, consignment_id: $consignment_id, order_status: $order_status");

        $wpdb->insert(
            $log_table,
            array(
                'order_id'          => $order_id,
                'consignment_id'    => $consignment_id,
                'order_status'      => $order_status,
                'order_status_slug' => $order_status,
                'reason'            => "Pathao Order created & it's pending.",
                'updated_at'        => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($wpdb->last_error) {
            error_log('[Pathao Debug] DB Insert Error: ' . $wpdb->last_error);
        } else {
            error_log('[Pathao Debug] Log inserted successfully.');
        }
    }

    public function register_meta_boxes()
    {
        // screen is shop_order for both HPOS and classic
        $screen = 'shop_order';

        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        // Only add the meta box on the order edit screen
        if ('edit' === $action) {
            add_meta_box(
                'pathao_order_wc',
                __('Pathao Shipping', 'integration-of-pathao-for-woocommerce'),
                array($this, 'pathao_shipping'),
                $screen,
                'side',
                'default'
            );
        }
    }

    public function pathao_shipping()
    {
        error_log('[Pathao Debug] pathao_shipping called');

        // cover both possible query params 'post' and 'id'
        if (isset($_GET['post'])) {
            $order_id = absint(wp_unslash($_GET['post']));
        } elseif (isset($_GET['id'])) {
            $order_id = absint(wp_unslash($_GET['id']));
        } else {
            $order_id = get_the_ID();
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $consignment_id = $order->get_meta('_pathao_consignment_id');
        $status         = $order->get_meta('_pathao_order_status');

        //error_log("[Pathao Debug] pathao_shipping - order_id: $order_id, consignment_id: $consignment_id, status: $status");

        if ($consignment_id && ! in_array($status, ['Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed'], true)) {
            $this->display_pathao_details($order);
        } elseif ($consignment_id) {
            $this->display_pathao_details($order);
            $this->pathao_shipping_form($order);
        } else {
            $this->pathao_shipping_form($order);
        }
    }

    public function display_pathao_details(WC_Order $order)
    {
        $consignment_id = $order->get_meta('_pathao_consignment_id');
        $delivery_fee   = $order->get_meta('_pathao_delivery_fee');
        $order_status   = $order->get_meta('_pathao_order_status');

        //error_log("[Pathao Debug] display_pathao_details - consignment_id: $consignment_id, status: $order_status");

        $view = __DIR__ . '/views/pathao-shipping-details.php';
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<p>' . esc_html__('Pathao details view missing.', 'integration-of-pathao-for-woocommerce') . '</p>';
        }
    }

    public function pathao_shipping_form(WC_Order $order)
    {

        error_log("xxOrder ID:" . $order->get_id());

        $order_id = $order->get_id();

        wp_localize_script(
            'pathao_admin_script',
            'pathao_admin_obj',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'order_id' => $order_id,
            )
        );

        wp_enqueue_style('pathao_toast_styles');
        wp_enqueue_script('pathao_toast_script');
        wp_enqueue_script('pathao_admin_script');

        $amount = is_sdevs_pathao_pro_activated() && $order->has_status(substr(sdevs_pathao_settings('paid_order_status', 'wc-paid'), 3)) ? 0 : $order->get_total();
        $all_totals       = sdevs_pathao_get_totals_from_items($order);
        $total_weight     = $all_totals->weight;
        $item_description = $all_totals->item_description;
        $status           = $order->get_meta('_pathao_order_status');

        //error_log("[Pathao Debug] pathao_shipping_form - amount: $amount, weight: $total_weight, status: $status");

        $view = __DIR__ . '/views/pathao-shipping.php';
        if (file_exists($view)) {
            include $view;
        } else {
            echo '<p>' . esc_html__('Pathao form view missing.', 'integration-of-pathao-for-woocommerce') . '</p>';
        }

        // Output a nonce named 'nonce' so client JS posts 'nonce' and server verifies with action 'pathao_send_order'
        wp_nonce_field('pathao_send_order', 'nonce');
    }
}
