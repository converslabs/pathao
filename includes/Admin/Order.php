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


         

           public function pathao_orders_menu_content() {
                // Basic security check: Ensure user has permission
                if ( ! current_user_can( 'manage_woocommerce' ) ) {
                    return;
                }

                // Get filter values safely
                $search_val = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
                $from_date  = isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '';
                $to_date    = isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '';
                $per_page   = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 20;
                ?>
                <div class="wrap pathao-order-page">
                    <h1 class="wp-heading-inline">
                        <?php echo esc_html__( 'Pathao Courier Order Page', 'integration-of-pathao-for-woocommerce' ); ?>
                    </h1>
                    <p class="description">
                        <?php echo esc_html__( 'Manage your deliveries without any distraction', 'integration-of-pathao-for-woocommerce' ); ?>
                    </p>
                    <hr class="wp-header-end">

                    <form method="get" class="pathao-filter-form">
                        <input type="hidden" name="page" value="pathao-orders-menu-slug">

                        <div class="pathao-filter-row">
                            <label class="pathao-filter-item">
                                <span><?php echo esc_html__( 'Search orders:', 'integration-of-pathao-for-woocommerce' ); ?></span>
                                <input type="search" name="s" placeholder="<?php echo esc_attr__( 'Consignment ID', 'integration-of-pathao-for-woocommerce' ); ?>" value="<?php echo esc_attr( $search_val ); ?>">
                            </label>

                            <label class="pathao-filter-item">
                                <span><?php echo esc_html__( 'From Date', 'integration-of-pathao-for-woocommerce' ); ?></span>
                                <input type="date" name="from_date" value="<?php echo esc_attr( $from_date ); ?>">
                            </label>

                            <label class="pathao-filter-item">
                                <span><?php echo esc_html__( 'To Date', 'integration-of-pathao-for-woocommerce' ); ?></span>
                                <input type="date" name="to_date" value="<?php echo esc_attr( $to_date ); ?>">
                            </label>

                            <label class="pathao-filter-item">
                                <span><?php echo esc_html__( 'Items Per Page', 'integration-of-pathao-for-woocommerce' ); ?></span>
                                <select name="per_page">
                                    <?php
                                    foreach ( [10, 20, 50, 100] as $count ) {
                                        printf(
                                            '<option value="%1$d" %2$s>%1$d %3$s</option>',
                                            $count,
                                            selected( $per_page, $count, false ),
                                            esc_html__( 'items', 'integration-of-pathao-for-woocommerce' )
                                        );
                                    }
                                    ?>
                                </select>
                            </label>

                            <button type="submit" class="button">
                                <?php echo esc_html__( 'Filter', 'integration-of-pathao-for-woocommerce' ); ?>
                            </button> 
                        </div>
                    </form>

                    <div class="pathao-table-container">
                        <?php $this->render_pathao_orders_table(); ?>
                    </div>

                    <div class="pathao-action-bar">
                        <button id="pathao-bulk-send" class="button button-primary"> 
                            <?php echo esc_html__( 'Send Selected Orders to Pathao', 'integration-of-pathao-for-woocommerce' ); ?>
                        </button>

                        <button id="pathao-sync-status" class="button pathao-sync-btn">
                            <span class="dashicons dashicons-update"></span>
                            <?php echo esc_html__( 'Sync Order Status', 'integration-of-pathao-for-woocommerce' ); ?>
                        </button>
                    </div>
                </div>
                <?php
            }
            
            private function render_pathao_orders_table()
            {
                $per_page = absint($_GET['per_page'] ?? 20);

                $args = [
                    'limit' => $per_page,
                    'orderby' => 'date_created', 
                    'order' => 'DESC',
                ];

                if ( ! empty( $_GET['s'] ) ) {

                $search = sanitize_text_field( $_GET['s'] );
 
                    if ( is_numeric( $search ) ) {
                        $args['include'] = [ (int) $search ];
                    } else {

                        $args['meta_query'] = [
                            'relation' => 'OR',
                            [
                                'key'     => '_pathao_consignment_id',
                                'value'   => $search,
                                'compare' => 'LIKE',
                            ], 
                        ];
                }
            }



                if ( ! empty( $_GET['from_date'] ) && ! empty( $_GET['to_date'] ) ) {

                    $from = sanitize_text_field( $_GET['from_date'] );
                    $to   = sanitize_text_field( $_GET['to_date'] );

                     if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
                        $args['date_created'] = $from . '...' . $to;
                    } elseif (!empty($_GET['from_date'])) {
                        $args['date_created'] = '>=' . $from;
                    } elseif (!empty($_GET['to_date'])) {
                        $args['date_created'] = '<=' . $to;
                    }



                } elseif ( ! empty( $_GET['from_date'] ) ) {

                    $from = sanitize_text_field( $_GET['from_date'] );
                    $args['date_created'] = '>=' . $from;

                } elseif ( ! empty( $_GET['to_date'] ) ) {

                    $to = sanitize_text_field( $_GET['to_date'] );
                    $args['date_created'] = '<=' . $to;
                }



                $orders = wc_get_orders($args);
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox"></th>
                            <th><?php esc_html_e( 'Order', 'integration-of-pathao-for-woocommerce' ); ?> </th>
                            <th><?php esc_html_e( 'Date', 'integration-of-pathao-for-woocommerce' ); ?> </th>
                            <th><?php esc_html_e( 'Status', 'integration-of-pathao-for-woocommerce' ); ?> </th>
                            <th><?php esc_html_e( 'Total', 'integration-of-pathao-for-woocommerce' ); ?> </th>
                            <th><?php esc_html_e( 'Pathao Courier', 'integration-of-pathao-for-woocommerce' ); ?> </th>
                            <th><?php esc_html_e( 'Pathao Status', 'integration-of-pathao-for-woocommerce' ); ?> </th>
                            <th><?php esc_html_e( 'Delivery Fee', 'integration-of-pathao-for-woocommerce' ); ?> </th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($orders) : ?>
                            <?php foreach ($orders as $order) :

                                $consignment_id = $order->get_meta('_pathao_consignment_id');
                                            // error_log($consignment_id);

                                $pathao_status = $order->get_meta('_pathao_order_status');
                                $delivery_fee = $order->get_meta('_pathao_delivery_fee');
                            ?>
                                <tr>
                                   <td>
                                    <input
                                        type="checkbox"
                                        class="pathao-order-checkbox"
                                        value="<?php echo esc_attr( $order->get_id() ); ?>"
                                    >
                                 </td> 
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link($order->get_id())); ?>">
                                            #<?php echo esc_html($order->get_id()); ?>
                                        </a>
                                    </td>

                                    <td><?php echo esc_html($order->get_date_created()->date('F jS, Y')); ?></td>
                                    <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                    <td><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>

                                <td>
                                    <?php
                                    $order_id       = $order->get_id();
                                    $consignment_id = trim($order->get_meta('_pathao_consignment_id'));

                                    // Build Send button
                                    $button = sprintf(
                                        '<button class="button button-primary ptc-open-modal-button" data-order-id="%d">Send To Pathao</button>',
                                        $order_id
                                    );

                                    if ( ! empty( $consignment_id ) ) {

                                        // Pathao order URL
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
                                    ?>
                                </td> 
                                    <td><?php echo esc_html($pathao_status ?: '—'); ?></td>
                                    <td><?php echo esc_html($delivery_fee ?: '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8"><?php esc_html_e( 'No orders found.', 'integration-of-pathao-for-woocommerce' ); ?> </td>
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