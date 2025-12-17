<?php

namespace SpringDevs\Pathao\Admin;

/**
 * Display admin notices.
 */
class Notice {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'no_token_notice' ) );
	}

	/**
	 * Display notice if no tokens.
	 *
	 * @return void
	 */
	public function no_token_notice() {
		if ( get_option( 'pathao_access_token' ) && get_option( 'pathao_refresh_token' ) ) {
			return;
		}

		require_once 'views/no-token.php';
	}
}
