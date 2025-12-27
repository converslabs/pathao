<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>




<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>

<div class="wrap pathao-admin-clean">
    <div class="pathao-setup-card">

        <div class="pathao-setup-header">
            <div class="brand-info">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L4 20L5 21L12 18L19 21L20 20L12 2Z" fill="#E11931"/>
                </svg>
       <h1><?php esc_html_e( 'Pathao Logistics', 'integration-of-pathao-for-woocommerce' ); ?></h1>
 
			</div> 
        </div>
        	 <?php settings_errors(); ?> 
	<div class="pathao-notice"></div>
	<p><?php esc_html_e( 'These credentials required for generate access & refresh token.', 'integration-of-pathao-for-woocommerce' ); ?></p>
	
        <form method="post" action="#" id="pathao-setup">
            <?php wp_nonce_field( '_pathao_setup_nonce', '_wp_setup_nonce' ); ?>
            
            <div class="pathao-input-grid">
                <div class="input-row">
                    <label for="pathao_client_id"><?php esc_html_e( 'Client ID', 'integration-of-pathao-for-woocommerce' ); ?></label>
                    <input id="pathao_client_id" type="text" value="<?php echo esc_attr( get_option( 'pathao_client_id' ) ); ?>" required />
                </div>
                <div class="input-row">
                    <label for="pathao_client_secret"><?php esc_html_e( 'Client Secret', 'integration-of-pathao-for-woocommerce' ); ?></label>
                    <input id="pathao_client_secret" type="password" value="<?php echo esc_attr( get_option( 'pathao_client_secret' ) ); ?>" required />
                </div>
                <div class="input-row">
                    <label for="pathao_client_username"><?php esc_html_e( 'Email Address', 'integration-of-pathao-for-woocommerce' ); ?></label>
                    <input id="pathao_client_username" type="text" required />
                </div>
                <div class="input-row">
                    <label for="pathao_client_password"><?php esc_html_e( 'Password', 'integration-of-pathao-for-woocommerce' ); ?></label>
                    <input id="pathao_client_password" type="password" required />
                </div>
            </div>

            <div class="pathao-toggle-row">
                <div class="toggle-text">
                    <span class="label-title"><?php esc_html_e( 'Sandbox Mode', 'integration-of-pathao-for-woocommerce' ); ?></span>
                    <span class="label-desc"><?php esc_html_e( 'Use the staging environment for testing.', 'integration-of-pathao-for-woocommerce' ); ?></span>
                </div>
                <label class="simple-switch">
                    <input id="pathao_sandbox_mode" type="checkbox" <?php checked( (int) get_option( 'pathao_sandbox_mode' ), 1 ); ?> />
                    <span class="dot-slider"></span>
                </label>
            </div>

            <div class="pathao-submit-area">
                <button type="submit" class="button-pathao-primary">
                    <?php esc_html_e( 'Authorize & Generate Token', 'integration-of-pathao-for-woocommerce' ); ?>
                </button>
                <div class="spinner pathao-setup-spinner"></div>
            </div>
        </form>

        <div class="connection-details-wrapper">
            <details class="token-details" <?php echo get_option('pathao_access_token') ? 'open' : ''; ?>>
                <summary>
                    <span class="summary-title"><?php esc_html_e( 'Connection Details', 'integration-of-pathao-for-woocommerce' ); ?></span>
                    <span class="summary-icon"></span>
                </summary>
                
                <div class="details-content">
                    <table class="form-table pathao-token-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="pathao_access_token">
                                        <?php esc_html_e( 'Access Token', 'integration-of-pathao-for-woocommerce' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea readonly class="large-text token-area" name="pathao_access_token" id="pathao_access_token" rows="6"><?php echo esc_html( get_option( 'pathao_access_token' ) ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Pathao api generated access token', 'integration-of-pathao-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="pathao_refresh_token">
                                        <?php esc_html_e( 'Refresh Token', 'integration-of-pathao-for-woocommerce' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea readonly class="large-text token-area" name="pathao_refresh_token" id="pathao_refresh_token" rows="6"><?php echo esc_html( get_option( 'pathao_refresh_token' ) ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Pathao api refresh access token', 'integration-of-pathao-for-woocommerce' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>

    </div>
</div>

<style>
.pathao-admin-clean {
    --p-accent: #E11931;
    --p-text: #1e293b;
    --p-muted: #64748b;
    --p-border: #e2e8f0;
    --p-bg-soft: #f8fafc;
    margin-top: 30px;
}

.pathao-setup-card {
    max-width: 800px;
    background: #fff;
    border: 1px solid var(--p-border);
    border-radius: 12px;
    padding: 35px;
    margin: 0 auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
}

/* Header & Status */
.pathao-setup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f1f5f9;
}
.brand-info { display: flex; align-items: center; gap: 12px; }
.brand-info h1 { font-size: 18px; font-weight: 700; margin: 0; color: var(--p-text); }

.status-indicator {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    padding: 5px 12px; border-radius: 20px; background: #f1f5f9; color: var(--p-muted);
}
.status-indicator.active { background: #dcfce7; color: #15803d; }

/* Form Elements */
.pathao-input-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.input-row { display: flex; flex-direction: column; gap: 6px; }
.input-row label { font-size: 13px; font-weight: 600; color: var(--p-text); }
.input-row input {
    border: 1px solid var(--p-border); border-radius: 8px; padding: 10px;
    font-size: 14px; background: #fafafa;
}

.pathao-toggle-row {
    margin: 25px 0; padding: 15px; background: var(--p-bg-soft); border-radius: 10px;
    display: flex; justify-content: space-between; align-items: center;
}
.label-title { display: block; font-weight: 600; font-size: 13px; }
.label-desc { font-size: 12px; color: var(--p-muted); }

/* Switch UI */
.simple-switch { position: relative; width: 40px; height: 22px; }
.simple-switch input { opacity: 0; width: 0; height: 0; }
.dot-slider { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; transition: .3s; border-radius: 20px; }
.dot-slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background: white; transition: .3s; border-radius: 50%; }
input:checked + .dot-slider { background: #10b981; }
input:checked + .dot-slider:before { transform: translateX(18px); }

/* Buttons */
.button-pathao-primary {
    background: var(--p-accent); color: white; border: none; padding: 12px 25px;
    border-radius: 8px; font-weight: 600; cursor: pointer;
}

/* Connection Details Section */
.connection-details-wrapper { margin-top: 40px; }
.token-details {
    border: 1px solid var(--p-border);
    border-radius: 8px;
    overflow: hidden;
}
.token-details summary {
    padding: 15px 20px;
    background: var(--p-bg-soft);
    cursor: pointer;
    font-weight: 600;
    color: var(--p-text);
    list-style: none;
    display: flex;
    justify-content: space-between;
}
.token-details summary::-webkit-details-marker { display: none; }
.summary-title::before { content: "↘"; margin-right: 8px; color: var(--p-accent); }

.details-content { padding: 10px 20px; border-top: 1px solid var(--p-border); }

/* Customizing the WordPress form-table for clean look */
.pathao-token-table th {
    width: 200px;
    padding: 20px 10px 20px 0;
    vertical-align: top;
    font-size: 13px;
    color: var(--p-text);
}
.pathao-token-table td { padding: 15px 0; }
.token-area {
    background: #f1f5f9 !important;
    border: 1px solid var(--p-border) !important;
    border-radius: 6px !important;
    font-family: monospace !important;
    font-size: 12px !important;
    color: #475569 !important;
    padding: 12px !important;
    width: 100%;
}
.pathao-token-table .description { margin-top: 8px; font-style: italic; color: var(--p-muted); }

@media (max-width: 600px) {
    .pathao-input-grid { grid-template-columns: 1fr; }
    .pathao-token-table th { width: auto; display: block; padding: 10px 0 5px; }
}
</style>