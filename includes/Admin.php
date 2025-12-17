<?php
/**
 * The admin class
 *
 * @package SpringDevs\Pathao\Admin
 */

namespace SpringDevs\Pathao;

use SpringDevs\Pathao\Admin\Links;
use SpringDevs\Pathao\Admin\Notice;
use SpringDevs\Pathao\Admin\Settings;

/**
 * The admin class
 */
class Admin {


	/**
	 * Initialize the class.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {
		new Notice();
		new Links();
		new Illuminate();
		new Settings();
		new Admin\Order();
	}
}
