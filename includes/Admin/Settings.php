<?php

namespace SpringDevs\Pathao\Admin;

/**
 * The Settings class.
 */
class Settings {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'woocommerce_settings_page_init', array( $this, 'init_scripts' ) );
		add_action( 'woocommerce_sections_shipping', array( $this, 'display_sandbox_notice' ) );
		add_action( 'woocommerce_after_settings_shipping', array( $this, 'display_setup_settings' ), 20 );
		add_action( 'woocommerce_settings_shipping', array( $this, 'pro_version_notice' ), 20 );
		add_filter( 'woocommerce_settings_api_sanitized_fields_pathao', array( $this, 'update_settings_on_free' ) );
	}

	/**
	 * Update settings.
	 *
	 * @param array $settings Settings.
	 *
	 * @return array
	 */
	public function update_settings_on_free( array $settings ): array {
		if ( ! is_sdevs_pathao_pro_activated() ) {
			$option = get_option( 'woocommerce_pathao_settings' );
			if ( $option ) {
				$settings['title']                   = $option['title'];
				$settings['enabled']                 = $option['enabled'];
				$settings['replace_checkout_fields'] = $option['replace_checkout_fields'];
				$settings['area_field']              = $option['area_field'];
				$settings['delivery_type']           = $option['delivery_type'];
				$settings['default_weight']          = $option['default_weight'];
				$settings['multi_checkout_support']  = $option['multi_checkout_support'];
			} else {
				$settings['title']                   = 'Pathao';
				$settings['enabled']                 = 'yes';
				$settings['replace_checkout_fields'] = 'yes';
				$settings['area_field']              = 'display_required';
				$settings['delivery_type']           = 48;
				$settings['default_weight']          = 0.5;
				$settings['multi_checkout_support']  = 'no';
			}
		}

		return $settings;
	}

	public function display_sandbox_notice() {
		if ( empty( $_GET['section'] ) || 'pathao' !== $_GET['section'] || ! get_option( 'pathao_sandbox_mode' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Sandbox mode is enabled.', 'sdevs_pathao' ); ?></p>
		</div>
		<?php
	}

	public function pro_version_notice() {
		if ( ! is_sdevs_pathao_pro_activated() && isset( $_GET['section'] ) && 'pathao' === $_GET['section'] ) {
			echo wp_kses_post( '<p style="color:red;">Pathao pro version required to work frontend shipping !</p>' );
		}
	}

	public function init_scripts() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue Style & scripts related to settings form.
	 */
	public function enqueue_scripts() {
		wp_localize_script(
			'pathao_admin_script',
			'pathao_admin_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
		wp_enqueue_style( 'pathao_toast_styles' );
		wp_enqueue_script( 'pathao_toast_script' );
		wp_enqueue_script( 'pathao_admin_script' );
	}

	public function display_setup_settings() {
		if ( ! isset( $_GET['section'] ) || 'pathao' !== $_GET['section'] ) {
			return;
		}

		include_once 'views/setup.php';
	}
}
