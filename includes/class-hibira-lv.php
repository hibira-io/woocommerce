<?php
/**
 * Settings access, the store channel name and the Hibira client.
 *
 * @package Hibira_Live_Visitors
 */

defined( 'ABSPATH' ) || exit;

class Hibira_LV {

	const PREFIX = 'hibira_lv_';

	/** Defaults of every option, without the prefix. */
	public static function defaults() {
		return array(
			'app_id'      => '',
			'key'         => '',
			'secret'      => '',
			'host'        => 'ws.hibira.io',
			'viewers'     => 'yes',
			'min'         => 2,
			'store_count' => 'no',
			'popups'      => 'yes',
			'stock'       => 'yes',
			'position'    => 'bottom-left',
			'delay'       => 8,
			'max_age'     => 24,
		);
	}

	/** An option; HIBIRA_APP_ID / HIBIRA_APP_KEY / HIBIRA_APP_SECRET / HIBIRA_HOST constants win. */
	public static function get( $name ) {
		$constants = array(
			'app_id' => 'HIBIRA_APP_ID',
			'key'    => 'HIBIRA_APP_KEY',
			'secret' => 'HIBIRA_APP_SECRET',
			'host'   => 'HIBIRA_HOST',
		);
		if ( isset( $constants[ $name ] ) && defined( $constants[ $name ] ) ) {
			return (string) constant( $constants[ $name ] );
		}
		$defaults = self::defaults();
		return get_option( self::PREFIX . $name, isset( $defaults[ $name ] ) ? $defaults[ $name ] : '' );
	}

	public static function on( $name ) {
		return 'yes' === self::get( $name );
	}

	public static function configured() {
		return '' !== trim( (string) self::get( 'app_id' ) ) && '' !== trim( (string) self::get( 'key' ) ) && '' !== trim( (string) self::get( 'secret' ) );
	}

	/** host, port, tls from "ws.hibira.io" or "127.0.0.1:6001" (plain, for local development). */
	public static function host_parts() {
		$host = preg_replace( '#^(https?|wss?)://#', '', rtrim( trim( (string) self::get( 'host' ) ), '/' ) );
		if ( '' === $host ) {
			$host = 'ws.hibira.io';
		}
		if ( preg_match( '/^(.+):(\d+)$/', $host, $m ) ) {
			$port = (int) $m[2];
			return array( $m[1], $port, 443 === $port );
		}
		return array( $host, 443, true );
	}

	/**
	 * The store channel: lv- and a hash of the home URL, so several shops can
	 * share one Hibira app. Product channels add -p-{product ID}.
	 */
	public static function store_channel() {
		return 'lv-' . substr( md5( home_url( '/' ) ), 0, 12 );
	}

	public static function product_channel( $product_id ) {
		return self::store_channel() . '-p-' . absint( $product_id );
	}

	/** @var array<int, array{0: string, 1: string, 2: array}> */
	private static $queue = array();

	/**
	 * Publishes an event at the end of the request, after the response is
	 * sent where PHP-FPM allows it, so checkout and stock updates never wait
	 * on the network.
	 */
	public static function later( $channel, $event, $data ) {
		if ( ! self::configured() ) {
			return;
		}
		if ( ! self::$queue ) {
			add_action( 'shutdown', array( __CLASS__, 'flush' ), 100 );
		}
		self::$queue[] = array( $channel, $event, $data );
	}

	/** Sends the queued events (shutdown). */
	public static function flush() {
		$queue       = self::$queue;
		self::$queue = array();
		if ( ! $queue ) {
			return;
		}
		if ( function_exists( 'fastcgi_finish_request' ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			fastcgi_finish_request();
		}
		$client = self::client();
		if ( ! $client ) {
			return;
		}
		foreach ( $queue as $item ) {
			$client->trigger( $item[0], $item[1], $item[2] );
		}
	}

	/** @return Hibira_LV_Client|null */
	public static function client() {
		if ( ! self::configured() ) {
			return null;
		}
		list( $host, $port, $tls ) = self::host_parts();
		$client                    = new Hibira_LV_Client( trim( self::get( 'app_id' ) ), trim( self::get( 'key' ) ), trim( self::get( 'secret' ) ), $host, $port, $tls );
		/**
		 * Filters the Hibira client (tests swap the transport).
		 *
		 * @param Hibira_LV_Client $client
		 */
		return apply_filters( 'hibira_lv_client', $client );
	}
}
