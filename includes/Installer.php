<?php

namespace SpringDevs\Pathao;

/**
 * Class Installer
 *
 * @package SpringDevs\Pathao
 */
class Installer {


	/**
	 * Run the installer.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run() {
		$this->add_version();
		$this->create_tables();
	}

	/**
	 * Add time and version on DB.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_version() {
		$installed = get_option( 'pathao_installed' );

		if ( ! $installed ) {
			update_option( 'pathao_installed', time() );
		}

		update_option( 'pathao_version', SDEVS_PATHAO_VERSION );

		if ( ! wp_next_scheduled( 'pathao_refresh_token_cron' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'pathao_refresh_token_cron' );
		}
	}

	/**
	 * Create necessary database tables.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function create_tables() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$this->create_logs_table();
	}

	/**
	 * Create logs table
	 *
	 * @return void
	 */
	public function create_logs_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'pathao_logs';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` INT(255) NOT NULL AUTO_INCREMENT,
                      `order_id` INT(100) NOT NULL,
                      `consignment_id` VARCHAR(100) NOT NULL,
                      `order_status` VARCHAR(100) NOT NULL,
                      `order_status_slug` VARCHAR(100) NOT NULL,
                      `reason` VARCHAR(200) NULL,
                      `updated_at` TIMESTAMP NOT NULL,
                      PRIMARY KEY (`id`)
                    ) $charset_collate";

		dbDelta( $schema );
	}
}
