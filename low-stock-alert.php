<?php
/**
 * Plugin Name: LeanCart Low Stock Alert
 * Plugin URI:  https://crownlengtheningcost.com/
 * Description: Sends a single alert email to the admin when product or variation stock falls below your set threshold. No repeat emails for the same product during the day.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: low-stock-alert
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_LOW_STOCK_ALERT_DEMO_SITE' ) ) {
	define( 'LEANCART_LOW_STOCK_ALERT_DEMO_SITE', 'https://crownlengtheningcost.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Low_Stock_Alert {

	const SLUG = 'low-stock-alert';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Low Stock Alert',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_LOW_STOCK_ALERT_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'low-stock-alert', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Low Stock Alert' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'low-stock-alert' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'threshold', __('Threshold quantity', 'low-stock-alert'), __('Send an alert when stock falls below this number.', 'low-stock-alert'), 'number' );
		LeanCart_Framework::field_text( 'email', __('Notification email', 'low-stock-alert'), __('Leave blank to use the admin email.', 'low-stock-alert'), 'email' );
	}


	public static function hooks() {
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'check' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'check' ) );
	}

	public static function check( $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_stock_quantity' ) ) { return; }
		$stock = $product->get_stock_quantity();
		if ( null === $stock ) { return; }
		$threshold = max( 0, (int) LeanCart_Framework::setting( 'threshold', 3 ) );
		if ( $stock > $threshold ) { return; }
		$id  = $product->get_id();
		$key = 'lc_lsa_' . $id;
		if ( get_transient( $key ) ) { return; }
		set_transient( $key, 1, DAY_IN_SECONDS );
		$to = LeanCart_Framework::setting( 'email' );
		if ( ! is_email( $to ) ) { $to = get_option( 'admin_email' ); }
		$subject = sprintf( __( '[%s] Low stock: %s', 'low-stock-alert' ), get_bloginfo( 'name' ), $product->get_name() );
		$message = sprintf(
			__( "Product: %s (ID %d)\nStock remaining: %d\nThreshold: %d\n\nTo edit the product: %s", 'low-stock-alert' ),
			$product->get_name(), $id, $stock, $threshold,
			admin_url( 'post.php?post=' . $id . '&action=edit' )
		);
		wp_mail( $to, $subject, $message );
		LeanCart_Framework::log( 'Low stock mail sent for product ' . $id );
	}


}

LeanCart_Low_Stock_Alert::init();

