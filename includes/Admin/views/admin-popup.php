<div id="ptc-modal" class="ptc-modal" style="display:none;">
  <div class="ptc-modal-content">

    <div class="ptc-header">
      <h2>Send Order to Pathao</h2>
      <img src=" " alt="Shop Logo">
    </div>

    <form id="ptc-pathao-form">

      <!-- Order Information -->
      <div class="ptc-section">
        <h3>Order Information</h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label>Total Price</label>
            <input type="text" id="ptc-total-price" readonly>
          </div>

          <div class="ptc-field">
            <label>Payment Status</label>
            <input type="text" id="ptc-payment-status" readonly>
          </div>
        </div>

        <div class="ptc-field">
          <label>Order Items</label>
          <textarea id="ptc-order-items" readonly></textarea>
        </div>
      </div>

      <!-- Customer Details -->
      <div class="ptc-section">
        <h3>Customer Information</h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label>Name</label>
            <input type="text" id="ptc-name">
          </div>

          <div class="ptc-field">
            <label>Phone</label>
            <input type="text" id="ptc-phone">
          </div>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label>Secondary Phone</label>
            <input type="text" id="ptc-secondary-phone">
          </div>

          <div class="ptc-field">
            <label>Order Number</label>
            <input type="text" id="ptc-order-number" readonly>
          </div>
        </div>
      </div>

      <!-- Delivery Details -->
      <div class="ptc-section">
        <h3>Delivery Details</h3>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label>Collectable Amount</label>
            <input type="number" id="ptc-collectable">
          </div>

          <div class="ptc-field">
            <label>Weight (kg)</label>
            <input type="number" id="ptc-weight" step="0.01">
          </div>

          <div class="ptc-field">
            <label>Quantity</label>
            <input type="number" id="ptc-quantity">
          </div>
        </div>

        <div class="ptc-field">
          <label>Full Address</label>
          <textarea id="ptc-address"></textarea>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label>Store</label>
            <input type="text" id="ptc-store" name="store">
          </div>

          <div class="ptc-field">
            <label>City</label>
            <select id="ptc-city"></select>
          </div>

          <div class="ptc-field">
            <label>Zone</label>
            <select id="ptc-zone"></select>
          </div>

          <div class="ptc-field">
            <label>Area</label>
            <select id="ptc-area"></select>
          </div>
        </div>

        <div class="ptc-grid">
          <div class="ptc-field">
            <label>Delivery Type</label>
            <select id="ptc-delivery-type">
              <option value="48">Normal Delivery</option>
              <option value="12">Express Delivery</option>
            </select>
          </div>

          <div class="ptc-field">
            <label>Item Type</label>
            <select id="ptc-item-type">
              <option value="2">Parcel</option>
              <option value="1">Document</option>
            </select>
          </div>
        </div>

        <div class="ptc-field">
          <label>Note</label>
          <textarea id="ptc-note"></textarea>
        </div>

        <div class="ptc-field">
          <label>Special Instruction</label>
          <textarea id="ptc-instruction"></textarea>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="ptc-footer">
        <button type="button" id="ptc-send-confirm" class="ptc-btn-primary">Send to Pathao</button>
        <button type="button" id="ptc-send-cancel" class="ptc-btn-secondary">Cancel</button>
      </div>

    </form>
  </div>
</div>
