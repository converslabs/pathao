<?php

namespace SpringDevs\Pathao;

class Assets {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function get_scripts() { 

        $plugin_js_assets_path = SDEVS_PATHAO_ASSETS . '/js/';

        return [
            'pathao_toast_script' => [
                'src'       => $plugin_js_assets_path . 'jquery.toast.min.js',
                'deps'      => ['jquery'],
                'in_footer' => true,
            ],
            'pathao_admin_script' => [
                'src'       => $plugin_js_assets_path . 'admin.js',
                'deps'      => ['jquery'],
                'in_footer' => true,
            ],
            'pathao_popup_script' => [
                'src'       => $plugin_js_assets_path . 'popup.js',
                'deps'      => ['jquery'],
                'in_footer' => true,
            ],
        ];
    }

    public function get_styles() {
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

    public function register_scripts() {
        foreach ($this->get_scripts() as $handle => $script) { 
            wp_register_script(
                $handle,
                $script['src'],
                $script['deps'],
                SDEVS_PATHAO_VERSION,
                $script['in_footer']
            );
        }
    }

    public function register_styles() {
        foreach ($this->get_styles() as $handle => $style) {
            wp_register_style(
                $handle,
                $style['src'],
                isset($style['deps']) ? $style['deps'] : [],
                SDEVS_PATHAO_VERSION
            );
        }
    }

    public function enqueue_admin_assets() {
        $screen = get_current_screen();

        // 🔧 TEMP: Disable screen check so assets load everywhere
        // if (!$screen || $screen->id !== 'edit-shop_order') {
        //     return;
        // }
  
        // Register
        $this->register_scripts();
        $this->register_styles();

        // Enqueue scripts
        foreach ($this->get_scripts() as $handle => $script) {
            wp_enqueue_script($handle);
        }

        wp_localize_script('pathao_popup_script', 'PTC_AJAX', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);

        // Enqueue styles
        foreach ($this->get_styles() as $handle => $style) {
            wp_enqueue_style($handle);
        }
    }


}
