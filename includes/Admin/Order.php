<?php

namespace SpringDevs\Pathao\Admin;

use WC_Order;

if ( ! defined('ABSPATH') ) {
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

        // Load popup at admin footer
        add_action('admin_footer', array($this, 'load_pathao_popup_view')); 
    }
    

    /**
     * Load popup modal HTML from admin-popup.php
     */
    public function load_pathao_popup_view()
    {
        $view = plugin_dir_path(dirname(__DIR__)) . 'includes/Admin/views/admin-popup.php';  
        if (file_exists($view)) {
            include $view;
        } else {
            echo "<!-- Pathao Popup View Missing: admin-popup.php -->";
        }
    }


    public function load_hpos_hooks()
    {
        add_action(
            'manage_' . \wc_get_page_screen_id('shop_order') . '_custom_column',
            array($this, 'add_custom_columns_data'),
            10,
            2
        );
    }

    public function add_custom_columns($columns)
    {
        $columns['sdevs_pathao_order_column'] = __('Pathao', 'integration-of-pathao-for-woocommerce');
        return $columns;
    }

    public function add_custom_columns_data($column, $order)
    {
        if ($column !== 'sdevs_pathao_order_column') {
            return;
        }

        // Normalize the order object
        if ($order instanceof WC_Order) {
            $order_obj = $order;
        } else {
            $order_id = absint($order);
            $order_obj = wc_get_order($order_id);

            if (!$order_obj) {
                echo esc_html('N/A');
                return;
            }
        }

        $order_id        = $order_obj->get_id();
        $consignment_id  = trim($order_obj->get_meta('_pathao_consignment_id', true));

        // Button HTML
        $button = sprintf(
            '<button type="button" class="ptc-open-modal-button button button-primary" data-order-id="%d">Send To Pathao</button>',
            $order_id
        );

        // CASE 1: Already has a valid consignment ID → Show link
        if (!empty($consignment_id) && (!defined('PTC_EMPTY_FLAG') || $consignment_id !== PTC_EMPTY_FLAG)) {

            $url = trailingslashit(get_ptc_merchant_panel_base_url()) 
                . 'courier/orders/' 
                . urlencode($consignment_id);

            echo sprintf(
                '<a href="%s" class="order-view" target="_blank">%s</a>',
                esc_url($url),
                esc_html($consignment_id)
            );

            return;
        }

        // CASE 2: Consignment ID exists but equals EMPTY FLAG → show ---
        if (!empty($consignment_id) && defined('PTC_EMPTY_FLAG') && $consignment_id === PTC_EMPTY_FLAG) {
            echo esc_html('---');
            return;
        }

        // CASE 3: No consignment ID → show button
        echo '<span class="ptc-assign-area">' . $button . '</span>';
    }


    public function store_log_after_creation($res)
    {
        global $wpdb;
        $log_table = $wpdb->prefix . 'pathao_logs';

        $order_id = isset($res->merchant_order_id)
            ? (int) $res->merchant_order_id
            : (isset($res->order_id) ? (int) $res->order_id : 0);

        $consignment_id = isset($res->consignment_id) ? sanitize_text_field($res->consignment_id) : '';
        $order_status   = isset($res->order_status) ? sanitize_text_field($res->order_status) : '';

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
    }

    public function register_meta_boxes()
    {
        $screen = 'shop_order';
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : '';

        if ($action === 'edit') {
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
        if (isset($_GET['post'])) {
            $order_id = absint(wp_unslash($_GET['post']));
        } elseif (isset($_GET['id'])) {
            $order_id = absint(wp_unslash($_GET['id']));
        } else {
            $order_id = get_the_ID();
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $consignment_id = $order->get_meta('_pathao_consignment_id');
        $status         = $order->get_meta('_pathao_order_status');

        if ($consignment_id && !in_array($status, ['Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed'], true)) {
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
        $view = __DIR__ . '/views/pathao-shipping-details.php';

        if (file_exists($view)) {
            include $view;
        } else {
            echo '<p>' . esc_html__('Pathao details view missing.', 'integration-of-pathao-for-woocommerce') . '</p>';
        }
    }

    public function pathao_shipping_form(WC_Order $order) {
        wp_localize_script('pathao_admin_script', 'pathao_admin_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'order_id' => $order_id,
        ]);
    }

}
