<?php
/**
 * Plugin Name: WooCommerce - eBay Enterprise Affiliates
 * Plugin URI: https://pmgarman.me/woocommerce-ebay-enterprise-affiliates/
 * Description: Easily integrate your eBay Enterprise Affiliate Network code into your WooCommerce site.
 * Version: 1.0.0
 * Author: Patrick Garman
 * Author URI: https://pmgarman.me
 * Requires at least: 4.0
 * Tested up to: 4.0
 *
 * Text Domain: wc-ebay
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence will fall
}

/**
 * Class WC_eBay_Enterprise_Affiliates
 *
 * Base plugin class for WooCommerce eBay Enterprise Affiliates
 */
class WC_eBay_Enterprise_Affiliates {

	public $version   = '1.0.0';
	public $endpoint  = 'https://t.pepperjamnetwork.com/track';

	private $defaults = array();

	public function __construct() {
		/**
		 * Add actions to add functionality to plugin
		 */
		add_action( 'woocommerce_thankyou', array( $this, 'iframe_html' ) );

		/**
		 * Adds filters to add options to the WC settings
		 */
		add_filter( 'woocommerce_general_settings', array( $this, 'add_pid_option' ) );

		/**
		 * Setup the defaults for the iframe source query args
		 */
		$this->defaults = array(
			'PID'         => 0,
			'INT'         => 'ITEMIZED',
			'OID'         => 0
		);
	}

	public function add_pid_option( $options = array() ) {
		$options[] = array(
			'title'    => __( 'eBay Affiliates', 'wc-ebay' ),
			'type'     => 'title',
			'id'       => 'wc_ebay_affiliates'
		);

		$options[] = array(
			'title'    => __( 'PID', 'wc-ebay' ),
			'desc'     => __( 'Your eBay affiliate network program ID number.', 'wc-ebay' ),
			'id'       => 'woocommerce_ebay_affiliate_pid',
			'default'  => 'XXXX',
			'type'     => 'text',
			'desc_tip' =>  true,
		);

		$options[] = array(
			'type'     => 'sectionend',
			'id'       => 'wc_ebay_affiliates'
		);

		return $options;
	}

	public function iframe_html( $order_id = 0 ) {
		/**
		 * Only do something if the order ID is in fact an order ID
		 */
		if( $this->validate_order_id( $order_id ) ) {
			echo $this->get_iframe_html( $order_id );
		}
	}

	public function get_iframe_html( $order_id = 0, $args = array() ) {
		$order = new WC_Order( $order_id );
		$items = $order->get_items();
		$item_query_args = array();

		/**
		 * TODO: Discount is never actually passed, the documentation seems to be reducing EVERY product price by the
		 * orders total discount... going to wait for confirmation on how to implement the discount before adding it.
		 *
		 * PDF Documentation: http://c.pmgr.mn/pnWA
		 */
		$discount = $order->get_order_discount();

		$i = 0;
		foreach( $items as $item ) {
			$i++;

			/**
			 * Only add the product to the query arg if the data is valid
			 */
			if( isset( $item['product_id'] ) || 0 < absint( $item['product_id'] ) ) {
				$id = ! empty( $item['variation_id'] ) ?  $item['variation_id'] : $item['product_id'];

				/**
				 * Get the product SKU
				 */
				$productmeta                    = new WC_Product( $id );
				$sku                            = $productmeta->post->sku;
				$item_query_args[ 'ITEM' . $i ] = $sku;

				/**
				 * Get the product QTY
				 */
				$item_query_args[ 'QTY'  . $i ] = isset( $item['qty'] ) ? absint( $item['qty'] ) : 0;

				/**
				 * Get the product total
				 */
				$item_query_args[ 'TOTALAMOUNT' . $i ] = isset( $item['line_total'] ) ? $item['line_total'] : 0;
			}
		}

		$src = $this->generate_iframe_src( $order_id, $item_query_args );
		return '<iframe src="' . $src . '" width="1" height="1" frameborder="0"></iframe>';
	}

	public function generate_iframe_src( $order_id = 0, $query_args = array() ) {
		/**
		 * If we do not have a valid array, make it so.
		 */
		if( ! is_array( $query_args ) ) {
			$query_args = array();
		}

		/**
		 * Set the PID, OID, and INT args which are required for the URL
		 */
		$query_args['PID'] = WC_Admin_Settings::get_option( 'woocommerce_ebay_affiliate_pid', '' );
		$query_args['OID'] = $order_id;
		$query_args['INT'] = 'ITEMIZED';

		return add_query_arg( $query_args, $this->endpoint );
	}

	private function validate_order_id( $order_id = 0 ) {
		$order_id = absint( $order_id );

		return 'shop_order' == get_post_type( $order_id ) ? true : false;
	}

}

/**
 * Start your eBay Enterprise Affiliate engines.
 */
global $wc_ebay_enterprise_affiliates;
$wc_ebay_enterprise_affiliates = new WC_eBay_Enterprise_Affiliates;