<?php
/**
 * The storefront: the viewers badge after the add-to-cart form, the
 * [hibira_viewers] shortcode, and the config and script on shop pages.
 *
 * @package Hibira_Live_Visitors
 */

defined( 'ABSPATH' ) || exit;

class Hibira_LV_Frontend {

	private static $needed = false;

	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'product_badge' ), 35 );
		add_shortcode( 'hibira_viewers', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'config' ), 5 );
	}

	/** Whether this request shows anything: a product page, or any shop page for popups and the store count. */
	private static function wanted() {
		if ( ! Hibira_LV::configured() || is_admin() ) {
			return false;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return true;
		}
		if ( ! Hibira_LV::on( 'popups' ) && ! Hibira_LV::on( 'store_count' ) ) {
			return self::$needed;
		}
		$is_wc = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_shop() );
		/**
		 * Filters whether the live visitors script loads on this page.
		 *
		 * @param bool $load True on WooCommerce pages.
		 */
		return (bool) apply_filters( 'hibira_lv_load', $is_wc || self::$needed );
	}

	public static function product_badge() {
		if ( Hibira_LV::configured() && Hibira_LV::on( 'viewers' ) ) {
			echo self::badge( 'product' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr.
		}
	}

	/** [hibira_viewers scope="store" min="3"] */
	public static function shortcode( $atts ) {
		$atts         = shortcode_atts(
			array(
				'scope' => 'product',
				'min'   => '',
			),
			$atts,
			'hibira_viewers'
		);
		self::$needed = true;
		return self::badge( 'store' === $atts['scope'] ? 'store' : 'product', $atts['min'] );
	}

	private static function badge( $scope, $min = '' ) {
		$attrs = ' data-hibira-viewers data-scope="' . esc_attr( $scope ) . '"';
		if ( '' !== $min ) {
			$attrs .= ' data-min="' . esc_attr( absint( $min ) ) . '"';
		}
		return '<div class="hibira-lv-badge"' . $attrs . ' hidden><span data-hibira-label></span></div>';
	}

	public static function enqueue() {
		if ( ! self::wanted() ) {
			return;
		}
		wp_enqueue_style( 'hibira-live-visitors', HIBIRA_LV_URL . 'assets/css/live-visitors.css', array(), HIBIRA_LV_VERSION );
		wp_enqueue_script(
			'hibira-live-visitors',
			HIBIRA_LV_URL . 'assets/js/live-visitors.js',
			array(),
			HIBIRA_LV_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/** Prints the config the script reads (never the secret). */
	public static function config() {
		if ( ! wp_script_is( 'hibira-live-visitors', 'enqueued' ) && ! ( self::$needed && self::wanted() ) ) {
			return;
		}
		if ( ! wp_script_is( 'hibira-live-visitors', 'enqueued' ) ) {
			self::enqueue();
		}
		echo '<script type="application/json" id="hibira-lv-config">' . wp_json_encode( self::client_config() ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
	}

	public static function client_config() {
		list( $host, $port, $tls ) = Hibira_LV::host_parts();
		$product                   = ( function_exists( 'is_product' ) && is_product() ) ? get_queried_object_id() : 0;
		return array(
			'key'        => trim( Hibira_LV::get( 'key' ) ),
			'host'       => 443 === $port || ( ! $tls && 80 === $port ) ? $host : $host . ':' . $port,
			'tls'        => $tls,
			'store'      => Hibira_LV::store_channel(),
			'product'    => $product ? $product : null,
			'min'        => max( 1, (int) Hibira_LV::get( 'min' ) ),
			'storeCount' => Hibira_LV::on( 'store_count' ),
			'popups'     => Hibira_LV::on( 'popups' ),
			'stock'      => Hibira_LV::on( 'stock' ) && $product,
			'position'   => 'bottom-right' === Hibira_LV::get( 'position' ) ? 'bottom-right' : 'bottom-left',
			'delay'      => max( 0, (int) Hibira_LV::get( 'delay' ) ),
			'maxAgeHours' => max( 1, (int) Hibira_LV::get( 'max_age' ) ),
			'recent'     => Hibira_LV::on( 'popups' ) ? Hibira_LV_Events::recent() : array(),
			'texts'      => array(
				/* translators: {count} is replaced with the number of people; keep it as is. */
				'viewing_one'    => __( '{count} person is viewing this right now', 'hibira-live-visitors' ),
				'viewing_other'  => __( '{count} people are viewing this right now', 'hibira-live-visitors' ),
				'shopping_one'   => __( '{count} person is shopping right now', 'hibira-live-visitors' ),
				'shopping_other' => __( '{count} people are shopping right now', 'hibira-live-visitors' ),
				'bought'         => __( 'Someone just bought', 'hibira-live-visitors' ),
				'close'          => __( 'Close', 'hibira-live-visitors' ),
			),
		);
	}
}
