<?php
        namespace ConversLabs\Pathao\Admin;

        use ConversLabs\Pathao\Services\PathaoApiService;
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
 

         

            public function load_pathao_popup_view()
            {
                $view = plugin_dir_path(dirname(__DIR__)) . 'includes/Admin/views/admin-popup.php';

                if (file_exists($view)) {
                    include $view;
                } else {
                    echo "<!-- Pathao Popup View Missing -->";
                }
            }

             public function update_order_info_from_pathao(int $order_id): void
                {
                    $order = wc_get_order($order_id);
                    if (! $order) {
                        return;
                    }

                    $consignment_id = $order->get_meta('_pathao_consignment_id');
                    if (empty($consignment_id)) {
                        return;
                    }

                    $api = new PathaoApiService();
                    $res = $api->get_order_by_merchant_order_id((string) $order_id);

                    if (empty($res->data)) {
                        return;
                    }

                    $data = $res->data;

                    if (! empty($data->consignment_id)) {
                        update_post_meta($order_id, '_pathao_consignment_id', $data->consignment_id);
                    }

                    if (! empty($data->order_status)) {
                        update_post_meta($order_id, '_pathao_order_status', $data->order_status);
                    }

                    if (isset($data->delivery_fee)) {
                        update_post_meta($order_id, '_pathao_delivery_fee', $data->delivery_fee);
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
                $columns['sdevs_pathao_status_column'] = __( 'Pathao Status', 'integration-of-pathao-for-woocommerce' );
            $columns['sdevs_pathao_fee_column']    = __( 'Delivery Fee', 'integration-of-pathao-for-woocommerce' );
                return $columns;
            }

            public function add_custom_columns_data( $column, $order ) {

            if ( $order instanceof WC_Order ) {
                $order_obj = $order;
            } else {
                $order_obj = wc_get_order( absint( $order ) );
                if ( ! $order_obj ) {
                    echo '—';
                    return;
                }
            }

            $order_id       = $order_obj->get_id();
            $consignment_id = trim( $order_obj->get_meta( '_pathao_consignment_id' ) );
            $pathao_status  = $order_obj->get_meta( '_pathao_order_status' ); 
            $delivery_fee = (string) $order_obj->get_meta( '_pathao_delivery_fee', true );


            switch ( $column ) {

                /** Pathao Courier column */
                case 'sdevs_pathao_order_column':

                    $button = sprintf(
                        '<button class="button button-primary ptc-open-modal-button" data-order-id="%d">Send To Pathao</button>',
                        $order_id
                    );

                    if ( ! empty( $consignment_id ) ) {
 
                        $base = get_option('pathao_sandbox_mode')
                            ? 'https://merchant.pathao.com/courier/orders/'
                            : 'https://merchant.pathao.com/courier/orders/';

                        $url = $base . urlencode($consignment_id);


                        echo sprintf(
                            '<a href="%s" class="order-view" target="_blank">%s</a>',
                            esc_url( $url ),
                            esc_html( $consignment_id )
                        );

                    } else {
                        echo '<span class="ptc-assign-area">' . $button . '</span>';
                    }

                    break;
 
                case 'sdevs_pathao_status_column':
                    echo esc_html( $pathao_status ?: '—' );
                    break; 

                case 'sdevs_pathao_fee_column':
                    if ( $delivery_fee === '' ) {
                    echo '—';
                    } else {
                        echo esc_html( $delivery_fee );
                    }

                    break;
            }
        }


            public function store_log_after_creation( $res ) {
                global $wpdb;
 
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

                update_post_meta( $order_id, '_pathao_consignment_id', $consignment_id );
                update_post_meta( $order_id, '_pathao_order_status', $order_status );

                if ( ! empty( $res->delivery_fee ) ) {
                    update_post_meta( $order_id, '_pathao_delivery_fee', $res->delivery_fee );
                }

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
                    'order_id' => $order->get_id(),  
                ]);
            }
        }