<?php
/**
 * The admin class
 *
 * @package ConversLabs\Pathao\Admin
 */

namespace ConversLabs\Pathao;

use ConversLabs\Pathao\Admin\Links;
use ConversLabs\Pathao\Admin\Notice;
use ConversLabs\Pathao\Admin\Settings;

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
