<?php
/**
 * Sidebar Form for Pathao Order Actions
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Logic for button text
$retry_statuses = array( 'Pickup_Failed', 'Pickup_Cancelled', 'Delivery_Failed' );
$button_text    = ( $status && in_array( $status, $retry_statuses, true ) ) 
    ? __( 'Send Order Again', 'integration-of-pathao-for-woocommerce' ) 
    : __( 'Send Order', 'integration-of-pathao-for-woocommerce' );
?>

<div class="sdevs-sidebar-form pathao-sidebar-wrapper">
    <?php wp_nonce_field( 'pathao_send_order', 'pathao_send_order_nonce' ); ?>
    
    <input type="hidden" value="<?php echo esc_attr( $order_id ); ?>" id="pathao_order_id">

    <p class="form-field">
        <label for="pathao_delivery_type">
            <strong><?php echo esc_html__( 'Delivery Type', 'integration-of-pathao-for-woocommerce' ); ?></strong>
            <span class="required" title="<?php echo esc_attr__( 'required', 'integration-of-pathao-for-woocommerce' ); ?>">*</span>
        </label>
        <select name="pathao_delivery_type" id="pathao_delivery_type">
            <option value="48"><?php echo esc_html__( 'Normal Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
            <option value="12"><?php echo esc_html__( 'On-demand Delivery', 'integration-of-pathao-for-woocommerce' ); ?></option>
        </select>
    </p>

    <p class="form-field">
        <label for="pathao_item_type">
            <strong><?php echo esc_html__( 'Item Type', 'integration-of-pathao-for-woocommerce' ); ?></strong>
            <span class="required" title="<?php echo esc_attr__( 'required', 'integration-of-pathao-for-woocommerce' ); ?>">*</span>
        </label>
        <select name="pathao_item_type" id="pathao_item_type">
            <option value="2"><?php echo esc_html__( 'Parcel', 'integration-of-pathao-for-woocommerce' ); ?></option>
            <option value="1"><?php echo esc_html__( 'Document', 'integration-of-pathao-for-woocommerce' ); ?></option>
        </select>
    </p>

    <p class="form-field">
        <label for="pathao_city"><strong><?php echo esc_html__( 'City', 'integration-of-pathao-for-woocommerce' ); ?></strong></label>
        <select id="pathao_city" name="pathao_city" class="pathao-select-full">
            <option value=""><?php echo esc_html__( 'Select City', 'integration-of-pathao-for-woocommerce' ); ?></option>
        </select>
    </p>

    <p class="form-field pathao-dynamic-field" id="pathao_zone_select">
        <label for="pathao_zone"><strong><?php echo esc_html__( 'Zone', 'integration-of-pathao-for-woocommerce' ); ?></strong></label>
        <select id="pathao_zone" name="pathao_zone" class="pathao-select-full"></select>
    </p>

    <p class="form-field pathao-dynamic-field" id="pathao_area_select">
        <label for="pathao_area"><strong><?php echo esc_html__( 'Area', 'integration-of-pathao-for-woocommerce' ); ?></strong></label>
        <select id="pathao_area" name="pathao_area" class="pathao-select-full"></select>
    </p>

    <p class="form-field">
        <label for="pathao_weight">
            <strong><?php echo esc_html__( 'Total weight (kg)', 'integration-of-pathao-for-woocommerce' ); ?></strong>
            <span class="required" title="<?php echo esc_attr__( 'required', 'integration-of-pathao-for-woocommerce' ); ?>">*</span>    
        </label>
        <?php 
            $weight_val = is_sdevs_pathao_pro_activated() ? $total_weight : ''; 
        ?>
        <input type="text" value="<?php echo esc_attr( $weight_val ); ?>" id="pathao_weight" name="pathao_weight" />
    </p>

    <p class="form-field">
        <label for="pathao_amount">
            <strong><?php echo esc_html__( 'Amount to Collect', 'integration-of-pathao-for-woocommerce' ); ?></strong>
            <span class="required" title="<?php echo esc_attr__( 'required', 'integration-of-pathao-for-woocommerce' ); ?>">*</span>
        </label>
        <input type="text" value="<?php echo esc_attr( round( $amount ) ); ?>" id="pathao_amount" name="pathao_amount" />
    </p>

    <p class="form-field">
        <label for="pathao_item_description">
            <strong><?php echo esc_html__( 'Item Description', 'integration-of-pathao-for-woocommerce' ); ?></strong>
        </label>
        <textarea id="pathao_item_description" name="pathao_item_description"><?php echo esc_textarea( trim( $item_description ) ); ?></textarea>
    </p>

    <p class="form-field">
        <label for="pathao_special_instruction"><strong><?php echo esc_html__( 'Special Instruction', 'integration-of-pathao-for-woocommerce' ); ?></strong></label>
        <textarea id="pathao_special_instruction" name="special_instruction"></textarea>
    </p>

    <div class="pathao-footer-actions">
        <input class="button-primary" id="pathao_submit_shipping" type="button" value="<?php echo esc_attr( $button_text ); ?>" />
        <div class="spinner pathao-shipping-spinner"></div>
    </div>
</div>