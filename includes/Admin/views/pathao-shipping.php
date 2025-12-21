<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>

<div class="sdevs_sidebar_form">
	<?php wp_nonce_field( 'pathao_send_order', 'pathao_send_order_nonce' ); ?>
 <input type="hidden" value="<?php echo esc_html( $order_id ); ?>" id="pathao_order_id">
	<p class="form-field">
		<label for="pathao_delivery_type">
 <b><?php echo esc_html__( 'Delivery Type', 'integration-of-pathao-for-woocommerce' ); ?></b>
	 	<abbr class="required" title="required">*</abbr>
		</label>
		<select style="width: 100%;" name="pathao_delivery_type" id="pathao_delivery_type">
 <option value="48"><?php echo esc_html__( 'Normal Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
 <option value="12"><?php echo esc_html__( 'On-demand Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
 </select>
	</p>
	<p class="form-field">
		<label for="pathao_item_type">
			<b><?php echo esc_html__( 'Item Type', 'integration-of-pathao-for-woocommerce' ); ?></b>
			<abbr class="required" title="required">*</abbr>
		</label>
		<select style="width: 100%;" name="pathao_item_type" id="pathao_item_type">
			<option value="2"><?php echo esc_html__( 'Parcel', 'integration-of-pathao-for-woocommerce' ); ?></option>
			<option value="1"><?php echo esc_html__( 'Document', 'integration-of-pathao-for-woocommerce' ); ?></option>
		</select>
	</p>
	<p class="form-field">
		<label for="pathao_city"><b><?php echo esc_html__( 'City', 'integration-of-pathao-for-woocommerce' ); ?></b></label>
		<select style="width: 95%;" id="pathao_city" name="pathao_city">
			<option value=""><?php echo esc_html__( 'Select City', 'integration-of-pathao-for-woocommerce' ); ?></option>
		</select>
	</p>
	<p class="form-field" id="pathao_zone_select" style="display: none;">
		<label for="pathao_zone"><b><?php echo esc_html__( 'Zone', 'integration-of-pathao-for-woocommerce' ); ?></b></label>
		<select style="width: 95%;" id="pathao_zone" name="pathao_zone">
		</select>
	</p>
	<p class="form-field" id="pathao_area_select" style="display: none;">
		<label for="pathao_area"><b><?php echo esc_html__( 'Area', 'integration-of-pathao-for-woocommerce' ); ?>/b></label>
		<select style="width: 95%;" id="pathao_area" name="pathao_area">
		</select>
	</p>
	<p class="form-field">
		<label for="pathao_weight">
			<b><?php echo esc_html__( 'Total weight (kg)', 'integration-of-pathao-for-woocommerce' ); ?></b>
			<abbr class="required" title="required">*</abbr>	
		</label>
		<input type="text" value="<?php echo is_sdevs_pathao_pro_activated() ? esc_html( $total_weight ) : ''; ?>"
				id="pathao_weight" name="pathao_weight" />
	</p>
	<p class="form-field">
		<label for="pathao_amount">
			<b><?php echo esc_html__( 'Amount to Collect', 'integration-of-pathao-for-woocommerce' ); ?></b>
			<abbr class="required" title="required">*</abbr>
		</label>
		<input type="text" value="<?php echo esc_html( round( $amount ) ); ?>" id="pathao_amount" name="pathao_amount" />
	</p>
	<p class="form-field">
		<label for="pathao_item_description">
			<b><?php echo esc_html__( 'Item Description', 'integration-of-pathao-for-woocommerce' ); ?></b>
		</label>
		<textarea style="width: 100%;" id="pathao_item_description" name="pathao_item_description"><?php echo esc_html( trim( $item_description ) ); ?></textarea>
	</p>
	<p class="form-field">
		<label for="pathao_special_instruction"><b><?php echo esc_html__( 'Special Instruction', 'integration-of-pathao-for-woocommerce' ); ?></b></label>
		<textarea style="width: 100%;" id="pathao_special_instruction" name="pathao_special_instruction"></textarea>
	</p>

	<input class="button-primary" id="pathao_submit_shipping" type="button"
			value="
			<?php
			echo $status && in_array(
				$status,
				array(
					'Pickup_Failed',
					'Pickup_Cancelled',
					'Delivery_Failed',
				),
				true
			) ? 'Send Order Again' : 'Send Order';
			?>
			" />
	<div class="spinner pathao-shipping-spinner"
		style="float:none;width:auto;height:auto;padding:10px 0 10px 50px;background-position:20px 0;position: relative;left: -25px;top: -1px;"></div>
</div>
