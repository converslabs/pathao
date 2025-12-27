<div id="ptc-modal" class="ptc-modal" style="display:none;">
  <div class="ptc-modal-content">

    <div class="ptc-header">
      <h2> <?php esc_html_e( 'x Send Order to Pathao', 'integration-of-pathao-for-woocommerce' ); ?> </h2>
      <img src=" " alt="Shop Logo">
    </div>

    <form id="ptc-pathao-form">

      <!-- Order Information -->
      <div class="ptc-section">
        <h3><?php esc_html_e( 'Order Information', 'integration-of-pathao-for-woocommerce' ); ?> </h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php esc_html_e( 'Total Price', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-total-price" readonly>
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Payment Status', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-payment-status" readonly>
          </div>
        </div>

        <div class="ptc-field">
          <label><?php esc_html_e( 'Order Items', 'integration-of-pathao-for-woocommerce' ); ?></label>
          <div id="ptc-order-items" readonly></div>
        </div>
      </div>

      <!-- Customer Details -->
      <div class="ptc-section">
        <h3>Customer Information</h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php esc_html_e( 'Name', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-name"  name="recipient_name">
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Phone', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-phone" name="recipient_phone">
          </div>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php esc_html_e( 'Secondary Phone', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-secondary-phone">
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Order Number', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-order-number" readonly>
          </div>
        </div>
      </div>

      <!-- Delivery Details -->
      <div class="ptc-section">
        <h3><?php esc_html_e( 'Delivery Details', 'integration-of-pathao-for-woocommerce' ); ?></h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php esc_html_e( 'Collectable Amount', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="number" id="ptc-collectable" name="amount_to_collect" readonly>
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Weight (kg)', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="number" id="ptc-weight" step="0.01" name="item_weight">
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Quantity', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="number" id="ptc-quantity" name="item_quantity">
          </div>
        </div>

        <div class="ptc-field">
          <label><?php esc_html_e( 'Full Address', 'integration-of-pathao-for-woocommerce' ); ?></label>
          <textarea id="ptc-address" name="recipient_address"></textarea>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php esc_html_e( 'Store', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-store" name="store">
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'City', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-city"  name="recipient_city"></select>
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Zone', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-zone"  name="recipient_zone"></select>
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Area', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-area" name="recipient_area"></select>
          </div>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php esc_html_e( 'Delivery Type', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-delivery-type" name="delivery_type">
              <option value="48"><?php esc_html_e( 'Normal Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
              <option value="12"><?php esc_html_e( 'Express Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
            </select>
          </div>

          <div class="ptc-field">
            <label><?php esc_html_e( 'Item Type', 'integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-item-type"  name="item_type">
              <option value="2"><?php esc_html_e( 'Parcel', 'integration-of-pathao-for-woocommerce' ); ?></option>
              <option value="1"><?php esc_html_e( 'Document', 'integration-of-pathao-for-woocommerce' ); ?></option>
            </select>
          </div>
        </div>

        <div class="ptc-field">
          <label><?php esc_html_e( 'Note', 'integration-of-pathao-for-woocommerce' ); ?></label>
          <textarea id="ptc-note"  name="note"></textarea>
        </div>

        <div class="ptc-field">
          <label><?php esc_html_e( 'Special Instruction', 'integration-of-pathao-for-woocommerce' ); ?></label>
          <textarea id="ptc-instruction" name="special_instruction"></textarea>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="ptc-footer">
        <button type="button" id="ptc-send-confirm" class="ptc-btn-primary"><?php esc_html_e( 'Send to Pathao', 'integration-of-pathao-for-woocommerce' ); ?></button>
        <button type="button" id="ptc-send-cancel" class="ptc-btn-secondary"><?php esc_html_e( 'Cancel', 'integration-of-pathao-for-woocommerce' ); ?></button>
      </div>

    </form>
  </div>
</div>


<!--==================== Bulk Order Modal ===================== -->
<div id="pathao-bulk-modal" class="pathao-bulk-modal" style="display:none;">
    <div class="pathao-bulk-overlay"></div>
    <div class="pathao-bulk-content" style="background:#fff;padding:20px;max-width:95%;max-height:90vh;overflow:auto;position:relative;margin:2% auto;border:1px solid #ccc;border-radius:4px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;border-bottom:1px solid #ddd;padding-bottom:15px;">
            <h2 style="margin:0;">Send Selected Orders to Pathao</h2>
            <button type="button" class="pathao-bulk-close" style="background:none;border:none;font-size:24px;cursor:pointer;color:#666;">&times;</button>
        </div>

        <form id="pathao-bulk-form">
            <div style="overflow-x:auto;">
                <table class="widefat fixed striped" style="table-layout:auto;">
                    <thead>
                        <tr>
                            <th style="width:80px;">Order</th>
                            <th style="width:120px;">Name</th>
                            <th style="width:130px;">Phone</th>
                            <th style="width:120px;">City</th>
                            <th style="width:120px;">Zone</th>
                            <th style="width:120px;">Area</th>
                            <th style="width:150px;">Address</th>
                            <th style="width:100px;">Delivery</th>
                            <th style="width:100px;">Item Type</th>
                            <th style="width:100px;">Weight</th>
                            <th style="width:100px;">Qty</th>
                            <th style="width:150px;">COD</th>
                            <th style="width:150px;">Instruction</th>
                        </tr>
                    </thead>
                    <tbody id="pathao-bulk-rows">
                        <!-- Rows loaded via AJAX -->
                    </tbody>
                </table>
            </div>

            <p style="margin-top:20px;text-align:right;">
                <button type="button" class="button pathao-bulk-close" style="margin-right:10px;">Cancel</button>
                <button type="submit" class="button button-primary">
                    Send Selected Orders to Pathao
                </button>
            </p>
        </form>
    </div>
</div>

<style>
.pathao-bulk-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pathao-bulk-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100001;
}

.pathao-bulk-content {
    position: relative;
    z-index: 100002;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    background: #fff;
    border-radius: 4px;
}

.pathao-bulk-content table {
    border-collapse: collapse;
}

.pathao-bulk-content table th {
    background: #f5f5f5;
    font-weight: 600;
    border-bottom: 2px solid #ddd;
    padding: 12px 8px;
    text-align: left;
    position: sticky;
    top: 0;
    z-index: 10;
}

.pathao-bulk-content table td {
    padding: 10px 8px;
    vertical-align: top;
    border-bottom: 1px solid #eee;
}

.pathao-bulk-content table tbody tr:hover {
    background: #f9f9f9;
}

.pathao-bulk-content table tbody tr[style*="opacity"] {
    background: #fff8e1;
}

.pathao-bulk-content input[type="text"],
.pathao-bulk-content input[type="number"],
.pathao-bulk-content select,
.pathao-bulk-content textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 13px;
}

.pathao-bulk-content input[type="text"]:focus,
.pathao-bulk-content input[type="number"]:focus,
.pathao-bulk-content select:focus,
.pathao-bulk-content textarea:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 1px #2271b1;
}

.pathao-bulk-content input:required,
.pathao-bulk-content select:required,
.pathao-bulk-content textarea:required {
    border-left: 3px solid #d63638;
}

.pathao-bulk-content select:disabled {
    background: #f0f0f0;
    cursor: not-allowed;
}

.pathao-bulk-content .small-text {
    width: 80px;
    min-width: 80px;
}

.pathao-bulk-content .regular-text {
    width: 100%;
    min-width: 120px;
}

.pathao-bulk-content .large-text {
    width: 100%;
    min-width: 150px;
    resize: vertical;
}

.pathao-bulk-content label {
    font-weight: 600;
    display: block;
    margin-bottom: 4px;
    font-size: 12px;
    color: #555;
}

.pathao-bulk-content small {
    display: block;
    color: #666;
    font-size: 11px;
    margin-top: 2px;
}

.pathao-bulk-content .field-group {
    margin-bottom: 8px;
}

.pathao-bulk-content .field-group:last-child {
    margin-bottom: 0;
}
</style>
