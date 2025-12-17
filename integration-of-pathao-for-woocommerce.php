<?php
/**
 * Plugin Name: Pathao Integration for WooCommerce
 * Plugin URI: https://springdevs.com/plugin/pathao
 * Description: Pathao integration for WooCommerce
 * Version: 1.1
 * Author: SpringDevs
 * Author URI: https://springdevs.com
 * Requires Plugins: woocommerce
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sdevs_pathao
 * Domain Path: /languages
 *
 * @package Pathao
 */

/**
 * Copyright (c) 2022 SpringDevs (email: contact@springdevs.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

// don't call the file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Sdevs_pathao class
 *
 * @class Sdevs_pathao The class that holds the entire Sdevs_pathao plugin
 */
final class Sdevs_pathao {

	/**
	 * Plugin version
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const VERSION = '1.1.0';

	/**
	 * Holds various class instances.
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 */
	private $container = array();

	/**
	 * Constructor for the Sdevs_pathao class.
	 *
	 * Sets up all the appropriate hooks and actions
	 * within our plugin.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->define_constants();

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}

	/**
	 * Initializes the Sdevs_pathao() class.
	 *
	 * Checks for an existing Sdevs_pathao() instance
	 * and if it doesn't find one, creates it.
	 *
	 * @since 1.0.0
	 *
	 * @return Sdevs_pathao|bool
	 */
	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new Sdevs_pathao();
		}

		return $instance;
	}

	/**
	 * Magic getter to bypass referencing plugin.
	 *
	 * @param $prop
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function __get( $prop ) {
		if ( array_key_exists( $prop, $this->container ) ) {
			return $this->container[ $prop ];
		}

		return $this->{$prop};
	}

	/**
	 * Magic isset to bypass referencing plugin.
	 *
	 * @param $prop
	 *
	 * @since 1.0.0
	 *
	 * @return mixed
	 */
	public function __isset( $prop ) {
		return isset( $this->{$prop} ) || isset( $this->container[ $prop ] );
	}

	/**
	 * Define the constants.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function define_constants() {
		define( 'SDEVS_PATHAO_VERSION', self::VERSION );
		define( 'SDEVS_PATHAO_FILE', __FILE__ );
		define( 'SDEVS_PATHAO_PATH', dirname( SDEVS_PATHAO_FILE ) );
		define( 'SDEVS_PATHAO_INCLUDES', __DIR__ . '/includes' );
		define( 'SDEVS_PATHAO_URL', plugins_url( '', SDEVS_PATHAO_FILE ) );
		define( 'SDEVS_PATHAO_ASSETS', SDEVS_PATHAO_URL . '/assets' );
	}

	/**
	 * Load the plugin after all plugins are loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_plugin() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Placeholder for activation function.
	 *
	 * Nothing being called here yet.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activate() {
		$installer = new SpringDevs\Pathao\Installer();
		$installer->run();
	}

	/**
	 * Placeholder for deactivation function.
	 *
	 * Nothing being called here yet.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
	}

	/**
	 * Include the required files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function includes() {
		if ( $this->is_request( 'admin' ) ) {
			$this->container['admin'] = new SpringDevs\Pathao\Admin();
		}

		if ( $this->is_request( 'frontend' ) ) {
			$this->container['frontend'] = new SpringDevs\Pathao\Frontend();
		}

		if ( $this->is_request( 'ajax' ) ) {
			// require_once SDEVS_PATHAO_INCLUDES . '/class-ajax.php';
		}
	}

	/**
	 * Initialize the hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'init_classes' ) );

		// Localize our plugin.
		add_action( 'init', array( $this, 'localization_setup' ) );
	}

	/**
	 * Instantiate the required classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_classes() {
		if ( $this->is_request( 'ajax' ) ) {
			$this->container['ajax'] = new SpringDevs\Pathao\Ajax();
		}

		$this->container['api']    = new SpringDevs\Pathao\Api();
		$this->container['assets'] = new SpringDevs\Pathao\Assets();
	}

	/**
	 * Initialize plugin for localization.
	 *
	 * @uses load_plugin_textdomain()
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function localization_setup() {
		load_plugin_textdomain( 'sdevs_pathao', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * What type of request is this?
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();

			case 'ajax':
				return defined( 'DOING_AJAX' );

			case 'rest':
				return defined( 'REST_REQUEST' );

			case 'cron':
				return defined( 'DOING_CRON' );

			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}
} // Sdevs_pathao

/**
 * Initialize the main plugin.
 *
 * @since 1.0.0
 *
 * @return \Sdevs_pathao|bool
 */
function sdevs_pathao() {
	return Sdevs_pathao::init();
}

/**
 * Kick-off the plugin.
 *
 * @since 1.0.0
 */
sdevs_pathao();
