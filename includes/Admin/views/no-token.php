<div class="notice notice-info">
	<p><b><?php esc_html_e(
		'Thanks for installing and using Pathao Integration for WooCommerce!',
		'sdevs_pathao'
	); ?></b></p>
	<p>
	<?php
	esc_html_e(
		'You need to generate tokens for use the plugin.',
		'sdevs_pathao'
	);
	?>
	</p>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=pathao#pathao-setup' ) ); ?>" class="button button-primary"> <?php esc_html_e( 'Setup now', 'sdevs_pathao' ); ?></a>
	</p>
</div>
