<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
} ?>

<div class="sdevs_sidebar_form">
	<?php wp_nonce_field( 'pathao_send_order', 'pathao_send_order_nonce' ); ?>
	<input type="hidden" value="<?php echo esc_html( $order_id ); ?>" id="pathao_order_id">
	<p class="form-field">
		<label for="pathao_delivery_type">
			<b>Delivery Type</b>
			<abbr class="required" title="required">*</abbr>
		</label>
		<select style="width: 100%;" name="pathao_delivery_type" id="pathao_delivery_type">
			<option value="48">Normal Delivery</option>
			<option value="12">On-demand Delivery</option>
		</select>
	</p>
	<p class="form-field">
		<label for="pathao_item_type">
			<b>Item Type</b>
			<abbr class="required" title="required">*</abbr>
		</label>
		<select style="width: 100%;" name="pathao_item_type" id="pathao_item_type">
			<option value="2">Parcel</option>
			<option value="1">Document</option>
		</select>
	</p>
	<p class="form-field">
		<label for="pathao_city"><b>City</b></label>
		<select style="width: 95%;" id="pathao_city" name="pathao_city">
			<option value="">Select City</option>
		</select>
	</p>
	<p class="form-field" id="pathao_zone_select" style="display: none;">
		<label for="pathao_zone"><b>Zone</b></label>
		<select style="width: 95%;" id="pathao_zone" name="pathao_zone">
		</select>
	</p>
	<p class="form-field" id="pathao_area_select" style="display: none;">
		<label for="pathao_area"><b>Area</b></label>
		<select style="width: 95%;" id="pathao_area" name="pathao_area">
		</select>
	</p>
	<p class="form-field">
		<label for="pathao_weight">
			<b>Total weight (kg)</b>
			<abbr class="required" title="required">*</abbr>	
		</label>
		<input type="text" value="<?php echo is_sdevs_pathao_pro_activated() ? esc_html( $total_weight ) : ''; ?>"
				id="pathao_weight" name="pathao_weight" />
	</p>
	<p class="form-field">
		<label for="pathao_amount">
			<b>Amount to Collect</b>
			<abbr class="required" title="required">*</abbr>
		</label>
		<input type="text" value="<?php echo esc_html( round( $amount ) ); ?>" id="pathao_amount" name="pathao_amount" />
	</p>
	<p class="form-field">
		<label for="pathao_item_description">
			<b>Item Description</b>
		</label>
		<textarea style="width: 100%;" id="pathao_item_description" name="pathao_item_description"><?php echo esc_html( trim( $item_description ) ); ?></textarea>
	</p>
	<p class="form-field">
		<label for="pathao_special_instruction"><b>Special Instruction</b></label>
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
