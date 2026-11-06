<?php
/**
 * Plugin Name:          Hibira Live Visitors
 * Plugin URI:           https://hibira.io/guides/woocommerce
 * Description:          Real "12 people are viewing this" counts, "someone just bought" popups and live stock on product pages, counted from open browser tabs on Hibira's realtime service. No fake numbers.
 * Version:              0.1.2
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      10.3
 * Author:               Hibira
 * Author URI:           https://hibira.io/?utm_source=woocommerce&utm_medium=plugin&utm_campaign=plugins
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          hibira-live-visitors
 *
 * @package Hibira_Live_Visitors
 */

defined( 'ABSPATH' ) || exit;

define( 'HIBIRA_LV_VERSION', '0.1.2' );
define( 'HIBIRA_LV_FILE', __FILE__ );
define( 'HIBIRA_LV_DIR', plugin_dir_path( __FILE__ ) );
define( 'HIBIRA_LV_URL', plugin_dir_url( __FILE__ ) );

require_once HIBIRA_LV_DIR . 'includes/class-hibira-lv-client.php';
require_once HIBIRA_LV_DIR . 'includes/class-hibira-lv.php';
require_once HIBIRA_LV_DIR . 'includes/class-hibira-lv-events.php';
require_once HIBIRA_LV_DIR . 'includes/class-hibira-lv-frontend.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HIBIRA_LV_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', HIBIRA_LV_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		Hibira_LV_Events::init();
		Hibira_LV_Frontend::init();
		add_filter(
			'woocommerce_get_settings_pages',
			function ( $pages ) {
				require_once HIBIRA_LV_DIR . 'includes/class-hibira-lv-settings.php';
				$pages[] = new Hibira_LV_Settings();
				return $pages;
			}
		);
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( HIBIRA_LV_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=hibira' ) ) . '">' . esc_html__( 'Settings', 'hibira-live-visitors' ) . '</a>' );
		return $links;
	}
);
