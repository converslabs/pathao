<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;} ?>
<div>
	<?php settings_errors(); ?>
	<h2><?php esc_html_e( 'Pathao Setup', 'sdevs_pathao' ); ?></h2>
	<div class="pathao-notice"></div>
	<p><?php esc_html_e( 'These credentials required for generate access & refresh token.', 'sdevs_pathao' ); ?></p>
	<form method="post" action="options.php" id="pathao-setup">
		<?php wp_nonce_field( '_pathao_setup_nonce', '_wp_setup_nonce' ); ?>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label for="pathao_client_id">
							<?php esc_html_e( 'Client ID', 'sdevs_pathao' ); ?>
						</label>
					</th>
					<td>
						<input class="regular-text" id="pathao_client_id" type="text" value="<?php echo esc_html( get_option( 'pathao_client_id' ) ); ?>" required />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="pathao_client_secret">
							<?php esc_html_e( 'Client Secret', 'sdevs_pathao' ); ?>
						</label>
					</th>
					<td>
						<input class="regular-text" id="pathao_client_secret" type="text" value="<?php echo esc_html( get_option( 'pathao_client_secret' ) ); ?>" required />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="pathao_client_username">
							<?php esc_html_e( 'Client Email / Username', 'sdevs_pathao' ); ?>
						</label>
					</th>
					<td>
						<input class="regular-text" id="pathao_client_username" type="text" required />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="pathao_client_password">
							<?php esc_html_e( 'Client Password', 'sdevs_pathao' ); ?>
						</label>
					</th>
					<td>
						<input class="regular-text" id="pathao_client_password" type="password" required />
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="pathao_sandbox_mode">
							<?php esc_html_e( 'Sandbox Mode', 'sdevs_pathao' ); ?>
						</label>
					</th>
					<td>
						<input class="regular-text" id="pathao_sandbox_mode" type="checkbox" />
					</td>
				</tr>
			</tbody>
		</table>

		<div style="display:flex;align-items:center;">
			<?php submit_button( 'Generate Token' ); ?>
			<div class="spinner pathao-setup-spinner" style="float:none;width:auto;height:auto;padding:10px 0 10px 50px;background-position:20px 0;"></div>
		</div>

	</form>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row">
					<label for="pathao_access_token">
						<?php esc_html_e( 'Access Token', 'sdevs_pathao' ); ?>
					</label>
				</th>
				<td>
					<textarea readonly class="large-text" name="pathao_access_token" id="pathao_access_token" cols="80" rows="10"><?php echo esc_html( get_option( 'pathao_access_token' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Pathao api generated access token', 'sdevs_pathao' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="pathao_refresh_token">
						<?php esc_html_e( 'Refresh Token', 'sdevs_pathao' ); ?>
					</label>
				</th>
				<td>
					<textarea readonly class="large-text" name="pathao_refresh_token" id="pathao_refresh_token" cols="80" rows="10"><?php echo esc_html( get_option( 'pathao_refresh_token' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Pathao api refresh access token', 'sdevs_pathao' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
</div>
