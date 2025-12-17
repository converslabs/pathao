<?php

namespace SpringDevs\Pathao\Admin;

/**
 * Plugin action links
 *
 * Class Links
 *
 * @package SpringDevs\Pathao\Admin
 */
class Links {

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . plugin_basename( SDEVS_PATHAO_FILE ), array( $this, 'plugin_action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );  }

	/**
	 * Add plugin action links
	 *
	 * @param array $links Plugin Links.
	 */
	public function plugin_action_links( $links ) {
		$extra_links             = array();
		$extra_links['settings'] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=pathao' ) . '" aria-label="' . esc_attr__( 'View Shipping settings', 'sdevs_pathao' ) . '">' . esc_html__( 'Settings', 'sdevs_pathao' ) . '</a>';

		if ( ! is_sdevs_pathao_pro_activated() ) {
			$extra_links['premium'] = '<a href="https://springdevs.com/plugin/pathao" target="_blank" style="color:#3db634;">' . __( 'Upgrade to premium', 'sdevs_pathao' ) . '</a>';
		}
		$extra_links['deactivate'] = $links['deactivate'];
		unset( $links['deactivate'] );
		return array_merge( $links, $extra_links );
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param array $links Plugin Row Meta.
	 * @param mixed $file  Plugin Base file.
	 *
	 * @return array
	 */
	public function plugin_row_meta( $links, $file ) {
		if ( plugin_basename( SDEVS_PATHAO_FILE ) !== $file ) {
			return $links;
		}

		$row_meta = array(
			'docs'    => '<a href="https://springdevs.com/docs/pathao" target="_blank">' . __( 'Docs', 'sdevs_pathao' ) . '</a>',
			'support' => '<a href="https://wordpress.org/support/plugin/integration-of-pathao-for-woocommerce" target="_blank">' . __( 'Support', 'sdevs_pathao' ) . '</a>',
			'review'  => '<a href="https://wordpress.org/support/plugin/integration-of-pathao-for-woocommerce/reviews/?rate=5#new-post" target="_blank">' . __( 'Review', 'sdevs_pathao' ) . '</a>',
		);

		return array_merge( $links, $row_meta );
	}
}
