<div id="ptc-modal" class="ptc-modal" style="display:none;">
  <div class="ptc-modal-content">

    <div class="ptc-header">
      <h2><?php echo esc_html__( 'Send Order to Pathao', 'integration-of-pathao-for-woocommerce' ); ?></h2>
      <img src=" " alt="Shop Logo">
    </div>

    <form id="ptc-pathao-form">

      <!-- Order Information -->
      <div class="ptc-section">
        <h3><?php echo esc_html__( 'Order Information','integration-of-pathao-for-woocommerce' ); ?></h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php echo esc_html__( 'Total Price','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-total-price" readonly>
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Payment Status','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-payment-status" readonly>
          </div>
        </div>

        <div class="ptc-field">
          <label><?php echo esc_html__( 'Order Items','integration-of-pathao-for-woocommerce' ); ?></label>
          <textarea id="ptc-order-items" readonly></textarea>
        </div>
      </div>

      <!-- Customer Details -->
      <div class="ptc-section">
        <h3><?php echo esc_html__( 'Customer Information','integration-of-pathao-for-woocommerce' ); ?></h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php echo esc_html__( 'Name','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-name">
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Phone','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-phone">
          </div>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php echo esc_html__( 'Secondary Phone','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-secondary-phone">
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Order Number','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-order-number" readonly>
          </div>
        </div>
      </div>

      <!-- Delivery Details -->
      <div class="ptc-section">
        <h3><?php echo esc_html__( 'Delivery Details','integration-of-pathao-for-woocommerce' ); ?></h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php echo esc_html__( 'Collectable Amount','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="number" id="ptc-collectable">
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Weight (kg)','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="number" id="ptc-weight" step="0.01">
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Quantity','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="number" id="ptc-quantity">
          </div>
        </div>

        <div class="ptc-field">
          <label><?php echo esc_html__( 'Full Address','integration-of-pathao-for-woocommerce' ); ?></label>
          <textarea id="ptc-address"></textarea>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php echo esc_html__( 'Store','integration-of-pathao-for-woocommerce' ); ?></label>
            <input type="text" id="ptc-store" name="store">
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'City','integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-city"></select>
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Zone','integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-zone"></select>
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Area','integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-area"></select>
          </div>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label><?php echo esc_html__( 'Delivery Type','integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-delivery-type">
              <option value="48"><?php echo esc_html__( 'Normal Delivery','integration-of-pathao-for-woocommerce' ); ?></option>
              <option value="12"><?php echo esc_html__( 'Express Delivery','integration-of-pathao-for-woocommerce' ); ?></option>
            </select>
          </div>

          <div class="ptc-field">
            <label><?php echo esc_html__( 'Item Type','integration-of-pathao-for-woocommerce' ); ?></label>
            <select id="ptc-item-type">
              <option value="2"><?php echo esc_html__( 'Parcel','integration-of-pathao-for-woocommerce' ); ?></option>
              <option value="1"><?php echo esc_html__( 'Document','integration-of-pathao-for-woocommerce' ); ?></option>
            </select>
          </div>
        </div>

        <div class="ptc-field">
          <label><?php echo esc_html__( 'Note','integration-of-pathao-for-woocommerce' ); ?></label>
          <textarea id="ptc-note"></textarea>
        </div>

        <div class="ptc-field">
          <label><?php echo esc_html__( 'Special Instruction','integration-of-pathao-for-woocommerce' ); ?></label>
          <textarea id="ptc-instruction"></textarea>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="ptc-footer">
        <button type="button" id="ptc-send-confirm" class="ptc-btn-primary"><?php echo esc_html__( 'Send to Pathao','integration-of-pathao-for-woocommerce' ); ?></button>
        <button type="button" id="ptc-send-cancel" class="ptc-btn-secondary">
          <?php echo esc_html__( 'Cancel', 'integration-of-pathao-for-woocommerce' ); ?>
      </button>

      </div>

    </form>
  </div>
</div>
