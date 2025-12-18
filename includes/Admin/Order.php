<?php

namespace SpringDevs\Pathao\Admin;

use WC_Order;

if (!defined('ABSPATH')) {
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

        add_action('admin_footer', array($this, 'load_pathao_popup_view'));
 
    } 

    private function render_pathao_orders_table()
    {
        $per_page = absint($_GET['per_page'] ?? 20);

        $args = [
            'limit' => $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($_GET['s'])) {
            $args['search'] = sanitize_text_field($_GET['s']);
        }

        if (!empty($_GET['from_date']) || !empty($_GET['to_date'])) {
            $args['date_created'] = [];

            if (!empty($_GET['from_date'])) {
                $args['date_created']['after'] = sanitize_text_field($_GET['from_date']);
            }

            if (!empty($_GET['to_date'])) {
                $args['date_created']['before'] = sanitize_text_field($_GET['to_date']);
            }
        }

        $orders = wc_get_orders($args);
?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><input type="checkbox"></th>
                    <th><?php echo esc_html__( 'Order','integration-of-pathao-for-woocommerce' ); ?>
                    <th><?php echo esc_html__( 'Date','integration-of-pathao-for-woocommerce' ); ?>
                    <th><?php echo esc_html__( 'Status','integration-of-pathao-for-woocommerce' ); ?>
                    <th><?php echo esc_html__( 'Total','integration-of-pathao-for-woocommerce' ); ?>
                    <th><?php echo esc_html__( 'Pathao Courier','integration-of-pathao-for-woocommerce' ); ?>
                    <th><?php echo esc_html__( 'Pathao Status','integration-of-pathao-for-woocommerce' ); ?>
                    <th><?php echo esc_html__( 'Delivery Fee','integration-of-pathao-for-woocommerce' ); ?>
                </tr>
            </thead>

            <tbody>
                <?php if ($orders) : ?>
                    <?php foreach ($orders as $order) :

                        $consignment_id = $order->get_meta('_pathao_consignment_id');  
                        $pathao_status = $order->get_meta('_pathao_order_status');
                        $delivery_fee = $order->get_meta('_pathao_delivery_fee');
                    ?>
                        <tr>
                            <td><input type="checkbox"></td>

                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">
                                    #<?php echo esc_html($order->get_id()); ?>
                                </a>
                            </td>

                            <td><?php echo esc_html($order->get_date_created()->date('F jS, Y')); ?></td>
                            <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                            <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>

                            <td>
                                <?php if ($consignment_id) : 
                                    ?>
                                     
                                    <?php echo esc_html($consignment_id); ?>
                                <?php else : ?>
                                    <button type="button" id="ptc-send-confirm" class="button button-primary"> 
                                       <?php  echo esc_html__( 'Send To Pathao','integration-of-pathao-for-woocommerce' ); ?>
                                    </button>

                                <?php endif; ?>
                            </td>

                            <td><?php echo esc_html($pathao_status ?: '—'); ?></td>
                            <td><?php echo esc_html($delivery_fee ?: '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="8">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

<?php
    }

    public function load_pathao_popup_view()
    {
        $view = plugin_dir_path(dirname(__DIR__)) . 'includes/Admin/views/admin-popup.php';

        if (file_exists($view)) {
            include $view;
        } else {
            echo "<!-- Pathao Popup View Missing -->";
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

        if ($order instanceof WC_Order) {
            $order_obj = $order;
        } else {
            $order_obj = wc_get_order(absint($order));
            if (!$order_obj) {
                echo 'N/A';
                return;
            }
        }

        $order_id = $order_obj->get_id();
        $consignment_id = trim($order_obj->get_meta('_pathao_consignment_id'));

        $button = sprintf(
            '<button class="button button-primary ptc-open-modal-button" data-order-id="%d">Send To Pathao</button>',
            $order_id
        ); 
      
        if (!empty($consignment_id)) {

            // Base URL MUST be in quotes
            $url = 'https://merchant.pathao.com/' . 'courier/orders/' . urlencode($consignment_id);

            echo sprintf(
                '<a href="%s" class="order-view" target="_blank">%s</a>',
                esc_url($url),
                esc_html($consignment_id)
            );

            return;
        }


        // CASE 2: No consignment ID → show button
        echo '<span class="ptc-assign-area">' . $button . '</span>';
    }

    public function store_log_after_creation( $res ) {
        global $wpdb;

        // Resolve order ID safely
        $order_id = 0;

        if ( ! empty( $res->merchant_order_id ) ) {
            $order_id = (int) $res->merchant_order_id;
        } elseif ( ! empty( $res->order_id ) ) {
            $order_id = (int) $res->order_id;
        }

        if ( ! $order_id ) { 
            return;
        }

        $consignment_id = sanitize_text_field( $res->consignment_id ?? '' );
        $order_status   = sanitize_text_field( $res->order_status ?? '' );

        // SAVE INTO ORDER META (THIS FIXES BLANK ISSUE)
        update_post_meta( $order_id, '_pathao_consignment_id', $consignment_id );
        update_post_meta( $order_id, '_pathao_order_status', $order_status );

        if ( ! empty( $res->delivery_fee ) ) {
            update_post_meta( $order_id, '_pathao_delivery_fee', $res->delivery_fee );
        }

        // Optional log table
        $wpdb->insert(
            $wpdb->prefix . 'pathao_logs',
            [
                'order_id'          => $order_id,
                'consignment_id'    => $consignment_id,
                'order_status'      => $order_status,
                'order_status_slug' => $order_status,
                'reason'            => "Pathao Order created & it's pending.",
                'updated_at'        => current_time('mysql'),
            ],
            ['%d','%s','%s','%s','%s','%s']
        );
    }



    public function register_meta_boxes()
    {
        if (($_GET['action'] ?? '') === 'edit') {
            add_meta_box(
                'pathao_order_wc',
                __('Pathao Shipping', 'integration-of-pathao-for-woocommerce'),
                array($this, 'pathao_shipping'),
                'shop_order',
                'side',
                'default'
            );
        }
    }

    public function pathao_shipping()
    {
        $order_id = isset($_GET['post'])
            ? absint($_GET['post'])
            : (isset($_GET['id']) ? absint($_GET['id']) : get_the_ID());

        $order = wc_get_order($order_id);

        if (!$order) return;

        $consignment_id = $order->get_meta('_pathao_consignment_id');
        $status = $order->get_meta('_pathao_order_status');

        if ($consignment_id) {
            $this->display_pathao_details($order);
        }

        $this->pathao_shipping_form($order);
    }

    public function display_pathao_details(WC_Order $order)
    {
        $view = __DIR__ . '/views/pathao-shipping-details.php';

        if (file_exists($view)) {
            include $view;
        } else {
            echo '<p>Pathao details view missing.</p>';
        }
    }

    public function pathao_shipping_form(WC_Order $order)
    {
        wp_localize_script('pathao_admin_script', 'pathao_admin_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'order_id' => $order->get_id(),   // FIXED — previously undefined
        ]);
    }
}