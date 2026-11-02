<?php
/**
 * WooCommerce → Settings → Hibira.
 *
 * @package Hibira_Live_Visitors
 */

defined( 'ABSPATH' ) || exit;

class Hibira_LV_Settings extends WC_Settings_Page {

	public function __construct() {
		$this->id    = 'hibira';
		$this->label = __( 'Live visitors', 'hibira-live-visitors' );
		parent::__construct();
		add_action( 'woocommerce_admin_field_hibira_lv_test', array( $this, 'test_field' ) );
		add_action( 'admin_post_hibira_lv_test', array( $this, 'send_test' ) );
		add_action( 'admin_notices', array( $this, 'test_notice' ) );
	}

	protected function get_settings_for_default_section() {
		$p      = Hibira_LV::PREFIX;
		$d      = Hibira_LV::defaults();
		$locked = function ( $constant ) {
			return defined( $constant ) ? array( 'disabled' => 'disabled' ) : array();
		};
		return array(
			array(
				'title' => __( 'Hibira app', 'hibira-live-visitors' ),
				'type'  => 'title',
				/* translators: 1: link to the Hibira portal, 2: the store's origin. */
				'desc'  => sprintf( __( 'Create a free app at %1$s and copy its App ID, Key and Secret. On the app page, turn on <strong>Send subscriber counts</strong> and add %2$s to <strong>Allowed origins</strong>.', 'hibira-live-visitors' ), '<a href="https://hibira.io/portal" target="_blank" rel="noopener">hibira.io</a>', '<code>' . esc_html( untrailingslashit( home_url() ) ) . '</code>' ),
				'id'    => $p . 'app',
			),
			array(
				'title'             => __( 'App ID', 'hibira-live-visitors' ),
				'id'                => $p . 'app_id',
				'type'              => 'text',
				'default'           => '',
				'custom_attributes' => $locked( 'HIBIRA_APP_ID' ),
			),
			array(
				'title'             => __( 'Key', 'hibira-live-visitors' ),
				'id'                => $p . 'key',
				'type'              => 'text',
				'default'           => '',
				'custom_attributes' => $locked( 'HIBIRA_APP_KEY' ),
			),
			array(
				'title'             => __( 'Secret', 'hibira-live-visitors' ),
				'id'                => $p . 'secret',
				'type'              => 'password',
				'default'           => '',
				'desc'              => __( 'Or define HIBIRA_APP_SECRET in wp-config.php to keep it out of the database.', 'hibira-live-visitors' ),
				'custom_attributes' => $locked( 'HIBIRA_APP_SECRET' ),
			),
			array(
				'title'   => __( 'Host', 'hibira-live-visitors' ),
				'id'      => $p . 'host',
				'type'    => 'text',
				'default' => $d['host'],
				'desc'    => __( 'ws.hibira.io unless your app lives in another cluster.', 'hibira-live-visitors' ),
			),
			array(
				'type' => 'hibira_lv_test',
				'id'   => $p . 'test',
			),
			array(
				'type' => 'sectionend',
				'id'   => $p . 'app',
			),
			array(
				'title' => __( 'What shoppers see', 'hibira-live-visitors' ),
				'type'  => 'title',
				'desc'  => __( 'Every number is a real open browser tab, counted by Hibira. Nothing is made up.', 'hibira-live-visitors' ),
				'id'    => $p . 'show',
			),
			array(
				'title'   => __( 'Product viewers', 'hibira-live-visitors' ),
				'desc'    => __( 'Show "3 people are viewing this right now" on product pages', 'hibira-live-visitors' ),
				'id'      => $p . 'viewers',
				'type'    => 'checkbox',
				'default' => $d['viewers'],
			),
			array(
				'title'             => __( 'Show from', 'hibira-live-visitors' ),
				'desc'              => __( 'viewers. Below this the badge stays hidden.', 'hibira-live-visitors' ),
				'id'                => $p . 'min',
				'type'              => 'number',
				'default'           => $d['min'],
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
			),
			array(
				'title'   => __( 'Store-wide count', 'hibira-live-visitors' ),
				'desc'    => __( 'Count shoppers on every shop page; show it with [hibira_viewers scope="store"]', 'hibira-live-visitors' ),
				'id'      => $p . 'store_count',
				'type'    => 'checkbox',
				'default' => $d['store_count'],
			),
			array(
				'title'   => __( 'Purchase popups', 'hibira-live-visitors' ),
				'desc'    => __( 'Show "Someone just bought" with the product, live and for recent orders. No customer names or places.', 'hibira-live-visitors' ),
				'id'      => $p . 'popups',
				'type'    => 'checkbox',
				'default' => $d['popups'],
			),
			array(
				'title'   => __( 'Popup position', 'hibira-live-visitors' ),
				'id'      => $p . 'position',
				'type'    => 'select',
				'default' => $d['position'],
				'options' => array(
					'bottom-left'  => __( 'Bottom left', 'hibira-live-visitors' ),
					'bottom-right' => __( 'Bottom right', 'hibira-live-visitors' ),
				),
			),
			array(
				'title'   => __( 'Recent orders for', 'hibira-live-visitors' ),
				'desc'    => __( 'hours. A visitor who arrives later sees one popup from this window.', 'hibira-live-visitors' ),
				'id'      => $p . 'max_age',
				'type'    => 'number',
				'default' => $d['max_age'],
				'custom_attributes' => array(
					'min'  => 1,
					'max'  => 168,
					'step' => 1,
				),
			),
			array(
				'title'   => __( 'Live stock', 'hibira-live-visitors' ),
				'desc'    => __( 'Update "Only 2 left in stock" and "Out of stock" on open product pages as orders come in', 'hibira-live-visitors' ),
				'id'      => $p . 'stock',
				'type'    => 'checkbox',
				'default' => $d['stock'],
			),
			array(
				'type' => 'sectionend',
				'id'   => $p . 'show',
			),
		);
	}

	public function test_field() {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=hibira_lv_test' ), 'hibira_lv_test' );
		echo '<tr><th scope="row">' . esc_html__( 'Connection', 'hibira-live-visitors' ) . '</th><td>';
		echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Send a test event', 'hibira-live-visitors' ) . '</a> ';
		echo '<p class="description">' . esc_html__( 'Save first. The event appears in the Debug tab of your Hibira app.', 'hibira-live-visitors' ) . '</p>';
		echo '</td></tr>';
	}

	public function send_test() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'hibira-live-visitors' ), 403 );
		}
		check_admin_referer( 'hibira_lv_test' );
		$client = Hibira_LV::client();
		$result = $client ? $client->trigger( Hibira_LV::store_channel(), 'hibira-test', array( 'message' => 'Hello from WooCommerce' ) ) : new WP_Error( 'hibira_config', __( 'Set the App ID, Key and Secret first.', 'hibira-live-visitors' ) );
		set_transient( 'hibira_lv_test_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : 'ok', 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=hibira' ) );
		exit;
	}

	public function test_notice() {
		$key    = 'hibira_lv_test_' . get_current_user_id();
		$result = get_transient( $key );
		if ( false === $result ) {
			return;
		}
		delete_transient( $key );
		if ( 'ok' === $result ) {
			/* translators: %s: channel name. */
			echo '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Test event sent to %s.', 'hibira-live-visitors' ), Hibira_LV::store_channel() ) ) . '</p></div>';
		} else {
			/* translators: %s: error message. */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'The test event failed: %s', 'hibira-live-visitors' ), $result ) ) . '</p></div>';
		}
	}
}
