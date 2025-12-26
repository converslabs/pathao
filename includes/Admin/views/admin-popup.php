<div id="ptc-modal" class="ptc-modal" style="display:none;">
  <div class="ptc-modal-content">

    <div class="ptc-header">
      <h2> <?php esc_html_e( 'Send Order to Pathao', 'integration-of-pathao-for-woocommerce' ); ?> </h2>
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
            <input type="number" id="ptc-collectable" name="amount_to_collect">
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
