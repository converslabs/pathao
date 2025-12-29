<?php

/**
 * Plugin Name: Pathao Integration for WooCommerce
 * Plugin URI: https://converslabs.com/products/pathao/
 * Description: Pathao integration for WooCommerce
 * Version: 2.0
 * Contributors: converswp, shamsbd71
 * Author: ConversWP
 * Author URI: https://converslabs.com
 * Requires Plugins: woocommerce 
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: integration-of-pathao-for-woocommerce
 * Domain Path: /languages
 *
 * @package Pathao
 */

// Don't call the file directly.
if (! defined('ABSPATH')) {
	exit;
}

define('SDEVS_PATHAO_DIR', plugin_dir_path(__FILE__));


require_once __DIR__ . '/vendor/autoload.php';

/**
 * Main plugin class.
 */
final class Sdevs_pathao
{

	const VERSION = '2.0';
	private $container = array();

	private function __construct()
	{
		$this->define_constants();

		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));

		  add_action('plugins_loaded', array($this, 'init_plugin'));
	}

	public static function init()
	{
		static $instance = false;
		if (!$instance) {
			$instance = new Sdevs_pathao();
		}
		return $instance;
	}

	public function __get($prop)
	{
		return $this->container[$prop] ?? $this->{$prop};
	}

	public function __isset($prop)
	{
		return isset($this->{$prop}) || isset($this->container[$prop]);
	}

	private function define_constants()
	{
		define('SDEVS_PATHAO_VERSION', self::VERSION);
		define('SDEVS_PATHAO_FILE', __FILE__);
		define('SDEVS_PATHAO_PATH', dirname(SDEVS_PATHAO_FILE));
		define('SDEVS_PATHAO_INCLUDES', __DIR__ . '/includes');
		define('SDEVS_PATHAO_URL', plugins_url('', SDEVS_PATHAO_FILE));
		define('SDEVS_PATHAO_ASSETS', SDEVS_PATHAO_URL . '/assets');
	}

	public function init_plugin()
	{
		$this->includes();
		$this->init_hooks();
	}

	public function activate()
	{
		$installer = new ConversLabs\Pathao\Installer();
		$installer->run();
	}

	public function deactivate() {}

	private function includes()
	{
		// Always include Ajax
		require_once SDEVS_PATHAO_INCLUDES . '/Ajax.php';

		if ($this->is_request('admin')) {
			$this->container['admin'] = new ConversLabs\Pathao\Admin();
		} 
	}

	private function init_hooks()
	{
		add_action('init', array($this, 'init_classes'));
		add_action('init', array($this, 'localization_setup'));
	}

	public function init_classes()
	{
		// Initialize Ajax as singleton
		$this->container['ajax'] = \ConversLabs\Pathao\Ajax::init();

		$this->container['api']    = new \ConversLabs\Pathao\Api();
		$this->container['assets'] = new \ConversLabs\Pathao\Assets();
	}

	public function localization_setup()
	{
		load_plugin_textdomain(
			'integration-of-pathao-for-woocommerce',
			false,
			dirname(plugin_basename(__FILE__)) . '/languages/'
		);
	}

	private function is_request($type)
	{
		switch ($type) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined('DOING_AJAX');
			case 'rest':
				return defined('REST_REQUEST');
			case 'cron':
				return defined('DOING_CRON');
			case 'frontend':
				return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
		}
		return false;
	}

 

}

// Kickoff
function sdevs_pathao()
{
	return Sdevs_pathao::init();
}
sdevs_pathao();
