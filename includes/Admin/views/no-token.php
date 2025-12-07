<div class="notice notice-info">
	<p><b><?php esc_html_e(
		'Thanks for installing and using Pathao Integration for WooCommerce!',
		'integration-of-pathao-for-woocommerce'
	); ?></b></p>
	<p>
	<?php
	esc_html_e(
		'You need to generate tokens for use the plugin.',
		'integration-of-pathao-for-woocommerce'
	);
	?>
	</p>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=pathao#pathao-setup' ) ); ?>" class="button button-primary"> <?php esc_html_e( 'Setup now', 'integration-of-pathao-for-woocommerce' ); ?></a>
	</p>
</div>
