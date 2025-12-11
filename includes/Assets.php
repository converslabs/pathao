<?php

/**
 * Scripts and Styles Class.
 *
 * @package SpringDevs\Pathao\Assets
 */

namespace SpringDevs\Pathao;

if (! defined('ABSPATH')) {
    exit;
}

class Assets
{
    /**
     * Assets constructor.
     */
    public function __construct()
    {
        if (is_admin()) {
            // Enqueue scripts & styles for admin
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        } else {
            // Enqueue frontend scripts & styles
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        }
    }

    /**
     * Get all registered scripts
     *
     * @return array
     */
    public function get_scripts()
    {
        $plugin_js_assets_path = SDEVS_PATHAO_ASSETS . '/js/';

        return [
            'pathao_toast_script' => [
                'src'       => $plugin_js_assets_path . 'jquery.toast.min.js',
                'deps'      => ['jquery'],
                'version'   => SDEVS_PATHAO_VERSION,
                'in_footer' => true,
            ],
            'pathao_admin_script' => [
                'src'       => $plugin_js_assets_path . 'admin.js',
                'deps'      => ['jquery', 'pathao_toast_script'],
                'version'   => SDEVS_PATHAO_VERSION,
                'in_footer' => true,
            ],
        ];
    }

    /**
     * Get all registered styles
     *
     * @return array
     */
    public function get_styles()
    {
        $plugin_css_assets_path = SDEVS_PATHAO_ASSETS . '/css/';

        return [
            'pathao_toast_styles' => [
                'src' => $plugin_css_assets_path . 'jquery.toast.min.css',
            ],
            'pathao_styles' => [
                'src' => $plugin_css_assets_path . 'style.css',
            ],
        ];
    }

    /**
     * Enqueue admin scripts & styles
     *
     * @param string $hook Current admin page
     */
    public function enqueue_scripts($hook)
    {
		error_log('[Pathao Debug] Enqueueing admin scripts for hook: ' . $hook);
        // Only load on WooCommerce orders page
        // if ($hook !== 'edit.php' || get_post_type() !== 'shop_order') {
        //     return;
        // }

        // Enqueue JS
	foreach ($this->get_scripts() as $handle => $script) {
			wp_enqueue_script(
				$handle,
				$script['src'],
				$script['deps'] ?? [],
				$script['version'] ?? SDEVS_PATHAO_VERSION,
				$script['in_footer'] ?? true
			);
		}

        // Enqueue CSS
        foreach ($this->get_styles() as $handle => $style) {
            wp_enqueue_style(
                $handle,
                $style['src'],
                $style['deps'] ?? [],
                SDEVS_PATHAO_VERSION
            );
        }

        // Localize AJAX URL and nonce
        wp_localize_script('pathao_admin_script', 'pathao_admin_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pathao_send_order'),
        ]); 
    }

    /**
     * Enqueue frontend scripts & styles
     */
    public function enqueue_frontend_scripts()
    {
        // Add frontend scripts here if needed
        foreach ($this->get_scripts() as $handle => $script) {
            wp_enqueue_script(
                $handle,
                $script['src'],
                $script['deps'] ?? [],
                $script['version'] ?? SDEVS_PATHAO_VERSION,
                $script['in_footer'] ?? true
            );
        }

        foreach ($this->get_styles() as $handle => $style) {
            wp_enqueue_style(
                $handle,
                $style['src'],
                $style['deps'] ?? [],
                SDEVS_PATHAO_VERSION
            );
        }
    }
}
