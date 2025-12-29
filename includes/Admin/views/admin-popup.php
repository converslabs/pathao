<div id="ptc-modal" class="ptc-modal" style="display:none;">
    <div class="ptc-modal-content">
        <div class="ptc-header">
            <h2><?php esc_html_e( 'Send Order to Pathao', 'integration-of-pathao-for-woocommerce' ); ?></h2>
            <div class="ptc-logo-container">
                <img src="<?php echo esc_url( plugins_url( 'assets/images/pathao-logo.png', __FILE__ ) ); ?>" alt="Pathao Logo">
            </div>
        </div>

        <form id="ptc-pathao-form">
            <div class="ptc-section">
                <h3 class="ptc-section-title"><?php esc_html_e( 'Order Information', 'integration-of-pathao-for-woocommerce' ); ?></h3>
                <div class="ptc-grid ptc-grid-2">
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Total Price', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <input type="text" id="ptc-total-price" name="total_price" readonly class="ptc-input-readonly">
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Payment Status', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <input type="text" id="ptc-payment-status" name="payment_status" readonly class="ptc-input-readonly">
                    </div>
                </div>
                <div class="ptc-field">
                    <label><?php esc_html_e( 'Order Items', 'integration-of-pathao-for-woocommerce' ); ?></label>
                    <div id="ptc-order-items" class="ptc-items-list"></div>
                </div>
            </div>

            <div class="ptc-section">
                <h3 class="ptc-section-title"><?php esc_html_e( 'Customer Information', 'integration-of-pathao-for-woocommerce' ); ?></h3>
                <div class="ptc-grid ptc-grid-2">
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Name', 'integration-of-pathao-for-woocommerce' ); ?> <span class="ptc-required">*</span></label>
                        <input type="text" id="ptc-name" name="recipient_name" required>
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Phone', 'integration-of-pathao-for-woocommerce' ); ?> <span class="ptc-required">*</span></label>
                        <input type="text" id="ptc-phone" name="recipient_phone" required>
                    </div>
                </div>
                <div class="ptc-grid ptc-grid-2">
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Secondary Phone', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <input type="text" id="ptc-secondary-phone">
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Order Number', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <input type="text" id="ptc-order-number" name="order_number" readonly class="ptc-input-readonly">
                    </div>
                </div>
            </div>

            <div class="ptc-section">
                <h3 class="ptc-section-title"><?php esc_html_e( 'Delivery Details', 'integration-of-pathao-for-woocommerce' ); ?></h3>
                <div class="ptc-grid ptc-grid-3">
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Collectable Amount', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <input type="number" id="ptc-collectable" name="amount_to_collect" readonly class="ptc-input-readonly">
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Weight (kg)', 'integration-of-pathao-for-woocommerce' ); ?> <span class="ptc-required">*</span></label>
                        <input type="number" id="ptc-weight" step="0.01" name="item_weight" required>
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Quantity', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <input type="number" id="ptc-quantity" name="item_quantity">
                    </div>
                </div>

                <div class="ptc-field">
                    <label><?php esc_html_e( 'Full Address', 'integration-of-pathao-for-woocommerce' ); ?> <span class="ptc-required">*</span></label>
                    <textarea id="ptc-address" name="recipient_address" rows="2" required></textarea>
                </div>

                <div class="ptc-grid ptc-grid-3">  
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'City', 'integration-of-pathao-for-woocommerce' ); ?> <span class="ptc-required">*</span></label>
                        <select id="ptc-city" name="recipient_city" required></select>
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Zone', 'integration-of-pathao-for-woocommerce' ); ?> <span class="ptc-required">*</span></label>
                        <select id="ptc-zone" name="recipient_zone" required></select>
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Area', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <select id="ptc-area" name="recipient_area"></select>
                    </div>
                </div>

                <div class="ptc-grid ptc-grid-2">
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Delivery Type', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <select id="ptc-delivery-type" name="delivery_type">
                            <option value="48"><?php esc_html_e( 'Normal Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
                            <option value="12"><?php esc_html_e( 'Express Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
                        </select>
                    </div>
                    <div class="ptc-field">
                        <label><?php esc_html_e( 'Item Type', 'integration-of-pathao-for-woocommerce' ); ?></label>
                        <select id="ptc-item-type" name="item_type">
                            <option value="2"><?php esc_html_e( 'Parcel', 'integration-of-pathao-for-woocommerce' ); ?></option>
                            <option value="1"><?php esc_html_e( 'Document', 'integration-of-pathao-for-woocommerce' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="ptc-field">
                    <label><?php esc_html_e( 'Special Instruction', 'integration-of-pathao-for-woocommerce' ); ?></label>
                    <textarea id="ptc-instruction" name="special_instruction" rows="2"></textarea>
                </div>
            </div>

            <div class="ptc-footer">
                <button type="button" id="ptc-send-cancel" class="ptc-btn-secondary"><?php esc_html_e( 'Cancel', 'integration-of-pathao-for-woocommerce' ); ?></button>
                <button type="button" id="ptc-send-confirm" class="ptc-btn-primary"><?php esc_html_e( 'Send to Pathao', 'integration-of-pathao-for-woocommerce' ); ?></button>
            </div>
        </form>
    </div>
</div>


<div id="pathao-bulk-modal" class="pathao-bulk-modal" style="display:none;">
    <div class="pathao-bulk-overlay"></div>
    <div class="pathao-bulk-content">
        <div class="pathao-bulk-header">
            <h2><?php esc_html_e( 'Bulk Send Orders to Pathao', 'integration-of-pathao-for-woocommerce' ); ?></h2>
            <button type="button" class="pathao-bulk-close">&times;</button>
        </div>

        <form id="pathao-bulk-form">
            <div class="pathao-table-container">
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="col-order"><?php esc_html_e( 'Order', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-name"><?php esc_html_e( 'Name', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-phone"><?php esc_html_e( 'Phone', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-loc"><?php esc_html_e( 'City', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-loc"><?php esc_html_e( 'Zone', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-zone"><?php esc_html_e( 'Area', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-addr"><?php esc_html_e( 'Address', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-type"><?php esc_html_e( 'Type', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-wi"><?php esc_html_e( 'Wt', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-qty"><?php esc_html_e( 'Qty', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-cod"><?php esc_html_e( 'COD', 'integration-of-pathao-for-woocommerce' ); ?></th>
                            <th class="col-instr"><?php esc_html_e( 'Instruction', 'integration-of-pathao-for-woocommerce' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="pathao-bulk-rows">
                        </tbody>
                </table>
            </div>

            <div class="pathao-bulk-footer">
                <div class="pathao-bulk-info">
                    <span id="pathao-bulk-count">0</span> <?php esc_html_e( 'orders selected', 'integration-of-pathao-for-woocommerce' ); ?>
                </div>
                <div class="pathao-bulk-buttons">
                    <button type="button" class="button pathao-bulk-close"><?php esc_html_e( 'Cancel', 'integration-of-pathao-for-woocommerce' ); ?></button>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Send Selected Orders', 'integration-of-pathao-for-woocommerce' ); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>