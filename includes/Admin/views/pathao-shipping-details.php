<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<p>
	<b>Consignment ID :</b><br>
	<code><?php echo esc_html( $consignment_id ); ?></code>
</p>
<p>
	<b>Delivery Fee :</b><br>
	<span><?php echo esc_html( '৳ ' . $delivery_fee ); ?></span>
</p>
<p>
	<b>Status :</b><br>
	<span><?php echo esc_html( str_replace( '_', ' ', $order_status ) ); ?></span>
</p>
