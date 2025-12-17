<?php
/**
 * Scripts and Styles Class.
 *
 * @package SpringDevs\Pathao\Assets
 */

namespace SpringDevs\Pathao;

/**
 * Scripts and Styles Class
 */
class Assets {

	/**
	 * Assets constructor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'register' ), 5 );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 5 );
		}
	}

	/**
	 * Register our app scripts and styles
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		$this->register_scripts( $this->get_scripts() );
		$this->register_styles( $this->get_styles() );
	}

	/**
	 * Register scripts
	 *
	 * @param array $scripts
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_scripts( $scripts ) {
		foreach ( $scripts as $handle => $script ) {
			$deps      = $script['deps'] ?? false;
			$in_footer = $script['in_footer'] ?? false;
			$version   = $script['version'] ?? SDEVS_PATHAO_VERSION;

			wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );
		}
	}

	/**
	 * Register styles
	 *
	 * @param array $styles
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_styles( $styles ) {
		foreach ( $styles as $handle => $style ) {
			$deps = $style['deps'] ?? false;

			wp_register_style( $handle, $style['src'], $deps, SDEVS_PATHAO_VERSION );
		}
	}

	/**
	 * Get all registered scripts
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_scripts() {
		$plugin_js_assets_path = SDEVS_PATHAO_ASSETS . '/js/';

		$scripts = array(
			'pathao_toast_script' => array(
				'src'       => $plugin_js_assets_path . 'jquery.toast.min.js',
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
			),
			'pathao_admin_script' => array(
				'src'       => $plugin_js_assets_path . 'admin.js',
				'deps'      => array( 'jquery', 'pathao_toast_script' ),
				'in_footer' => true,
			),
		);

		return $scripts;
	}

	/**
	 * Get registered styles
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_styles() {
		$plugin_css_assets_path = SDEVS_PATHAO_ASSETS . '/css/';

		$styles = array(
			'pathao_toast_styles' => array(
				'src' => $plugin_css_assets_path . 'jquery.toast.min.css',
			),
		);

		return $styles;
	}
}
