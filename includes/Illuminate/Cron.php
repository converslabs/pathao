<?php

namespace SpringDevs\Pathao\Illuminate;

use SpringDevs\Pathao\Facades\PathaoAPI;

/**
 * Class Cron
 *
 * @package SpringDevs\Pathao\Illuminate
 */
class Cron {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'pathao_refresh_token_cron', array( $this, 'update_token' ) );
	}

	/**
	 * Refresh tokens.
	 *
	 * @return void
	 */
	public function update_token() {
		$res = PathaoAPI::refresh_tokens();

		if ( ! $res->success ) {
			return;
		}

		$data = $res->data;
		update_option( 'pathao_access_token', $data->access_token );
		update_option( 'pathao_refresh_token', $data->refresh_token );
	}
}
