<?php
/**
 * Publishes purchases and stock changes. Purchases carry only the product
 * (no customer data) and are kept as a short "recent" list for visitors who
 * arrive later.
 *
 * @package Hibira_Live_Visitors
 */

defined( 'ABSPATH' ) || exit;

class Hibira_LV_Events {

	const RECENT_OPTION = 'hibira_lv_recent';
	const RECENT_MAX    = 10;
	const SENT_META     = '_hibira_lv_sent';

	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'purchase' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'purchase' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'purchase' ) );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'stock' ) );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'stock_status' ), 10, 3 );
	}

	/** Publishes the order's first visible product once per order. */
	public static function purchase( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::SENT_META ) ) {
			return;
		}
		$order->update_meta_data( self::SENT_META, time() );
		$order->save_meta_data();

		$item = self::purchased_product( $order );
		if ( ! $item ) {
			return;
		}
		$recent = get_option( self::RECENT_OPTION, array() );
		$recent = is_array( $recent ) ? $recent : array();
		array_unshift( $recent, $item );
		update_option( self::RECENT_OPTION, array_slice( $recent, 0, self::RECENT_MAX ), false );

		if ( ! Hibira_LV::on( 'popups' ) ) {
			return;
		}
		Hibira_LV::later( Hibira_LV::store_channel(), 'purchase', $item );
	}

	/**
	 * The popup data for an order: its first product that is published and
	 * visible in the catalog.
	 *
	 * @param WC_Order $order
	 * @return array|null id, title, url, image, at (milliseconds).
	 */
	public static function purchased_product( $order ) {
		foreach ( $order->get_items() as $line ) {
			if ( ! $line instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $line->get_product();
			if ( $product && $product->is_type( 'variation' ) ) {
				$product = wc_get_product( $product->get_parent_id() );
			}
			if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
				continue;
			}
			$image = wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' );
			return array(
				'id'    => $product->get_id(),
				'title' => wp_strip_all_tags( $product->get_name() ),
				'url'   => get_permalink( $product->get_id() ),
				'image' => $image ? $image : '',
				'at'    => (int) round( microtime( true ) * 1000 ),
			);
		}
		return null;
	}

	/** Recent purchases still young enough to show. */
	public static function recent() {
		$recent  = get_option( self::RECENT_OPTION, array() );
		$max_age = max( 1, (int) Hibira_LV::get( 'max_age' ) ) * HOUR_IN_SECONDS * 1000;
		$now     = (int) round( microtime( true ) * 1000 );
		return array_values(
			array_filter(
				is_array( $recent ) ? $recent : array(),
				function ( $p ) use ( $now, $max_age ) {
					return is_array( $p ) && isset( $p['at'] ) && $now - (int) $p['at'] <= $max_age;
				}
			)
		);
	}

	/** @param WC_Product $product */
	public static function stock( $product ) {
		self::publish_stock( $product );
	}

	public static function stock_status( $product_id, $status, $product = null ) {
		self::publish_stock( $product ? $product : wc_get_product( $product_id ) );
	}

	/** @param WC_Product|false|null $product */
	private static function publish_stock( $product ) {
		static $sent = array();
		if ( ! $product || $product->is_type( 'variation' ) || ! Hibira_LV::on( 'stock' ) ) {
			return;
		}
		$data = self::stock_data( $product );
		$key  = $product->get_id() . ':' . md5( wp_json_encode( $data ) );
		if ( isset( $sent[ $key ] ) ) {
			return;
		}
		$sent[ $key ] = true;
		Hibira_LV::later( Hibira_LV::product_channel( $product->get_id() ), 'stock', $data );
	}

	/** @param WC_Product $product */
	public static function stock_data( $product ) {
		$availability = $product->get_availability();
		return array(
			'status' => $product->get_stock_status(),
			'stock'  => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
			'text'   => wp_strip_all_tags( isset( $availability['availability'] ) ? $availability['availability'] : '' ),
			'class'  => isset( $availability['class'] ) ? sanitize_html_class( $availability['class'] ) : '',
		);
	}
}
