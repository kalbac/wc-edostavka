<?php
/*
Plugin Name: eDostavka Shipping Method
Plugin URI: http://woodev.ru/
Description: Плагин добавляет метод расчёта стоимости доставки через курьерскую службу <a href="http://www.edostavka.ru" target="_blank">СДЭК</a> в плагин WooCommerce.
Version: 1.4.0
Author: Мартиросов Максим
Author URI: http://martirosoff.ru
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required functions
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

if ( is_woocommerce_active() ) {

	class WC_Edostavka_Shipping {

		const VERSION = '1.4.0';
		protected static $method_id = 'edostavka';

		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			register_activation_hook( __FILE__, array( $this, 'activate' ) );
		}

		public static function get_method_id() {
			return apply_filters( 'woocommerce_edostavka_shipping_id', self::$method_id );
		}

		public function woocommerce_shipping_methods( $shipping_methods ) {
			$shipping_methods[ self::get_method_id() ] = 'WC_Edostavka_Shipping_Method';
			return $shipping_methods;
		}

		public function init() {
			include_once( 'includes/functions.php' );

			if ( version_compare( WC_VERSION, '2.6.0', '>=' ) ) {
				add_filter( 'woocommerce_shipping_methods', array( $this, 'woocommerce_shipping_methods' ) );
			} else {
				add_action( 'woocommerce_load_shipping_methods', array( $this, 'load_shipping_methods' ) );
			}

			//Checkout filds action
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_delivery_points_field'), 99 );
			add_filter( 'woocommerce_default_address_fields', array( $this, 'default_address_fields'), 99 );
			add_filter( 'woocommerce_form_field_hidden', array( $this, 'form_field_hidden' ), 10, 4 );
			add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'delivery_points_map') );
			add_action( 'woocommerce_checkout_process', array( $this, 'delivery_point_field_process' ) );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'update_order_review' ) );
			add_filter( 'woocommerce_update_order_review_fragments',  array( $this, 'ajax_update_delivery_points' ) );

			//Additional action
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'checkout_field_update_order_meta' ) );
			add_action( 'woocommerce_email_order_meta', array( $this, 'email_order_meta' ), 99 );
			add_filter( 'default_checkout_state', array( $this, 'default_checkout_state' ) );
			add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'shipping_packages' ), 10 );

			//JS scripts actions
			add_filter( 'woocommerce_params', array( $this, 'woocommerce_params' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'edostavka_scripts' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'shipping_init' ) );
		}

		public function edostavka_scripts() {

			wp_register_script( 'edostavka-script', plugins_url( '/assets/js/edostavka.js' , __FILE__ ), array( 'jquery' ), self::VERSION, true );
			wp_register_script( 'edostavka-yandex-map', 'https://api-maps.yandex.ru/2.1/?lang=ru_RU', array( 'jquery', 'edostavka-script' ), '2.1', true );
			wp_register_style( 'wc-edostavka', plugins_url( '/assets/css/edostavka.css' , __FILE__ ), array() );

			if( is_checkout() ) {
				wp_enqueue_style( 'wc-edostavka' );
				wp_enqueue_script( 'edostavka-yandex-map' );
				wp_enqueue_script( 'edostavka-script' );
			}
		}

		public function woocommerce_params( $params ) {
			return array_merge(
				$params,
				array(
					'default_country'			=> WC_Countries::get_base_country(),
					'geo_json_url'				=> $this->get_api_url(),
					'is_door' 					=> array_values( wc_edostavka_get_delivery_tariff_type( 'door' ) ),
					'default_state_name' 		=> WC()->customer->get_state(),
					'chosen_method'				=> wc_edostavka_get_chosen_shipping_method()
				)
			);
		}

		public function admin_enqueue_scripts( $hook ) {

			wp_register_script( 'edostavka-admin', plugins_url( '/assets/js/admin.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );
			wp_localize_script( 'edostavka-admin', 'wc_params', array(
				'default_country' => WC_Countries::get_base_country(),
				'api_url'         => $this->get_api_url()
			) );

			if( 'woocommerce_page_wc-settings' == $hook ) {
				wp_enqueue_script( 'edostavka-admin' );
			}

		}

		public function get_api_url() {
			return apply_filters( 'edostavka_cityes_json_url', esc_url( '//api.cdek.ru/city/getListByTerm/jsonp.php' ) );
		}

		public function shipping_init() {
			include_once( 'includes/class-wc-shipping-edostavka.php' );
		}

		public function load_shipping_methods( $package ) {
			woocommerce_register_shipping_method( 'WC_Edostavka_Shipping_Method' );

			if ( ! $package ) return;

			$zone = woocommerce_get_shipping_zone( $package );

			if ( $zone->exists() ) {
				$zone->register_shipping_methods();
			}
		}

		public static function XML2array( $XMLstring ) {
			if ( function_exists( 'simplexml_load_string' ) && function_exists( 'libxml_disable_entity_loader' ) ) {
				$loader = libxml_disable_entity_loader( true );
				$XMLobject = simplexml_load_string( $XMLstring, 'SimpleXMLElement', LIBXML_NOENT );
				$return = self::SimpleXMLelement2array( $XMLobject );
				libxml_disable_entity_loader( $loader );
				return $return;
			}
			return false;
		}

		public static function SimpleXMLelement2array( $XMLobject ) {
			if ( ! is_object( $XMLobject ) && ! is_array( $XMLobject ) ) {
				return $XMLobject;
			}
			$XMLarray = ( is_object( $XMLobject ) ? get_object_vars( $XMLobject ) : $XMLobject );
			foreach ($XMLarray as $key => $value) {
				$XMLarray[$key] = self::SimpleXMLelement2array( $value );
			}
			return $XMLarray;
		}

		public static function get_delivery_points( $billing_state_id = 0 ) {

			$przlist = array();

			if ( $billing_state_id && class_exists( 'SimpleXmlElement' ) ) {

				$chosen_shipping_method = wc_edostavka_get_chosen_shipping_method();

				switch (wc_edostavka_get_delivery_tariff_type(absint($chosen_shipping_method['tariff']))) {
					case 'stock' :
						$type = 'PVZ';
						break;
					default:
						$type = 'ALL';
						break;
				}

				$args = array(
					'cityid' => absint($billing_state_id),
					'type' => $type
				);

				$url = add_query_arg($args, 'https://int.cdek.ru/pvzlist.php');

				$response = wp_safe_remote_get($url, array('sslverify' => false, 'timeout' => 10));

				if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {

					$result = self::XML2array(wp_remote_retrieve_body($response));

					if (isset($result['Pvz']) && sizeof($result['Pvz']) > 0) {
						foreach ($result['Pvz'] as $pvz) {
							if (!empty($pvz['@attributes'])) {
								if (isset($pvz['@attributes']['Code'])) {
									$przlist[$pvz['@attributes']['Code']] = $pvz['@attributes'];
								}
							} elseif ($pvz['Code']) {
								$przlist[$pvz['Code']] = $pvz;
							}

						}
					}
				}
			}

			return $przlist;
		}

		public function add_delivery_points_field( $checkout_fields ){

			$state_default = wc_edostavka_get_customer_state_id();

			$delivery_points = self::get_delivery_points( $state_default );

			if( sizeof( $delivery_points ) > 0 ) {
				$points = array( 0 => __('Выберите пункт выдачи') );

				foreach( $delivery_points as $point ) {
					$points[$point['Code']] = $point['Name'] . ' (' . $point['Address'] . ')';
				}

				$checkout_fields['billing']['billing_delivery_point']['type'] = 'select';
				$checkout_fields['billing']['billing_delivery_point']['options'] = $points;
			}

			$checkout_fields['billing']['billing_state_id']['default'] = $state_default;

			return $checkout_fields;
		}

		public function default_address_fields( $fields ) {

			unset( $fields['postcode'] );
			unset( $fields['address_1'] );
			unset( $fields['address_2'] );

			$chosen_shipping_method = wc_edostavka_get_chosen_shipping_method();
			$is_stock = in_array( wc_edostavka_get_delivery_tariff_type( absint( $chosen_shipping_method['tariff'] ) ), array( 'stock' ) );

			$fields = array_merge( $fields, array(
				'postcode' => array(
					'label'       => __( 'Postcode / ZIP', 'woocommerce' ),
					'required'    => false,
					'class'       => array( 'form-row-last', 'address-field' ),
					'clear'       => true,
					'validate'    => array( 'postcode' ),
					'autocomplete' => 'postal-code'
				),
				'address_1' => array(
					'label'       => __( 'Address', 'woocommerce' ),
					'placeholder' => _x( 'Street address', 'placeholder', 'woocommerce' ),
					'required'    => $is_stock === false,
					'class'       => array( 'form-row-wide', 'address-field' )
				),
				'address_2' => array(
					'placeholder' => _x( 'Apartment, suite, unit etc. (optional)', 'placeholder', 'woocommerce' ),
					'class'       => array( 'form-row-wide', 'address-field' ),
					'required'    => false
				),
				'delivery_point'	=> array(
					'label'     => __('Пункт выдачи заказов'),
					'required'  => false,
					'class'     => array('form-row-wide', $is_stock ? '' : 'hidden' ),
					'clear'		=> true
				),
				'state_id'	=> array(
					'label'       => __( 'ID города' ),
					'type'		  => 'hidden',
					'required'    => false
				)
			) );

			if( wc_edostavka_get_option( 'hide_city' ) === 'yes' ) {
				$fields['city']['class'] = array( 'hidden' );
				$fields['city']['required'] = false;
			}

			return $fields;
		}

		public function form_field_hidden( $field, $key, $args, $value ) {
			if ( is_null( $value ) ) {
				$value = $args['default'];
			}
			return sprintf('<input type="hidden" name="%1$s" id="%1$s" value="%2$s" />', esc_attr( $key ), $value );
		}

		public function delivery_points_map( $checkout ) {
			echo '<div id="edostavka_map" class="hidden"></div>';
		}

		public function delivery_point_field_process() {

			$chosen_shipping_method = wc_edostavka_get_chosen_shipping_method();

			if ( $chosen_shipping_method['method'] == self::get_method_id() ) {

				$delivery_point = ( ! isset( $_POST['billing_delivery_point'] ) || empty( $_POST['billing_delivery_point'] ) ) ? true : false;

				if ( in_array( wc_edostavka_get_delivery_tariff_type( absint( $chosen_shipping_method['tariff'] ) ), array( 'stock' ) ) && $delivery_point ) {
					wc_add_notice(__('Вы выбрали метод доставки СДЕК. Для оформления данного типа доставки необходимо выбрать пункт выдачи заказов.'), 'error');
				}
			}

		}

		public function update_order_review( $post_data ) {

			$post_data = wp_parse_args( $post_data );

			if( isset( $post_data['billing_state_id'] ) ) {
				WC()->customer->state_id = $post_data['billing_state_id'];
			}

			return $post_data;
		}

		public function shipping_packages( $packages ) {

			$new_packages = array();

			foreach( $packages as $index => $package ) {
				$new_packages[$index] = $package;
				$new_packages[$index]['destination']['state_id'] = WC()->customer->state_id;
			}

			return $new_packages;
		}

		public function checkout_field_update_order_meta( $order_id ){
			if ( ! empty( $_POST['billing_delivery_point'] ) && ! empty( $_POST['billing_state_id'] ) ) {
				$state_id = absint( $_POST['billing_state_id'] );
				$order = wc_get_order( $order_id );
				$delivery_point = esc_attr( $_POST['billing_delivery_point'] );
				if( $state_id ) {
					$delivery_points = self::get_delivery_points( $state_id );
					if( isset( $delivery_points[ $delivery_point ] ) ) {
						update_post_meta( $order_id, 'delivery_point_name', $delivery_points[ $delivery_point ]['Address'] );
						$order->set_address( array( 'address_1' => $delivery_points[ $delivery_point ]['Address'] ), 'shipping' );
					}
				}
				update_post_meta( $order_id, '_delivery_point', $delivery_point );
			}

			if ( ! empty( $_POST['billing_state_id'] ) ) {
				update_post_meta( $order_id, '_state_id', esc_attr( $_POST['billing_state_id'] ) );
			}
		}

		public function email_order_meta( $order_id ){
			$order = wc_get_order( $order_id );
			if( strpos( self::get_order_shipping_method( $order ), self::get_method_id() ) === 0 ) {
				$state = absint( $order->billing_state_id );
				$delivery_point = get_post_meta( $order->id, '_delivery_point', true );
				$delivery_points = self::get_delivery_points( $state );
				if( isset( $delivery_points[ $delivery_point ] ) ) {
					echo '<h2>Пункт выдачи заказов</h2>';
					if( ! empty( $order->billing_state ) ) echo '<p>Город: ' . $order->billing_state . '</p>';
					echo '<p><strong>Адрес</strong>: ' . $delivery_points[ $delivery_point ]['Address'] . '</p>';
					echo '<p><strong>Режим работы</strong>: ' . $delivery_points[ $delivery_point ]['WorkTime'] . '</p>';
					echo '<p><strong>Телефон:</strong>: ' . $delivery_points[ $delivery_point ]['Phone'] . '</p>';
					if( ! empty( $delivery_points[ $delivery_point ]['Note'] ) ) echo '<p><strong>Примечания:</strong>: ' . $delivery_points[ $delivery_point ]['Note'] . '</p>';
				}
			}
		}

		public static function get_order_shipping_method( $order ){
			$shipping_methods = $order->get_shipping_methods();
			$shipping_method = '';
			foreach ( $shipping_methods as $shipping_method ) {
				$shipping_method= $shipping_method['method_id'];
			}
			return $shipping_method;
		}

		public function ajax_update_delivery_points( $fragments ){

			$html_billing_delivery_point = '<input type="text" class="hidden" value="0" name="billing_delivery_point" id="billing_delivery_point" />';

			$html_billing_delivery_points_map = '<div id="edostavka_map" class="hidden"></div>';

			$delivery_points = self::get_delivery_points( wc_edostavka_get_customer_state_id() );
			if( sizeof( $delivery_points ) > 0 ) {
				$html_billing_delivery_point = '<select name="billing_delivery_point" id="billing_delivery_point">';
				$html_billing_delivery_point .= '<option value="">Выберите пункт выдачи</option>';
				foreach( $delivery_points as $delivery_point ) {
					$html_billing_delivery_point .= sprintf('<option value="%s">%s</option>', $delivery_point['Code'], $delivery_point['Name'] . ' (' . $delivery_point['Address'] . ')');
				}
				$html_billing_delivery_point .= '</select>';

				$html_billing_delivery_points_map = sprintf( "<div id='edostavka_map' data-state-name='%s' data-state-id='%s' data-points='%s'></div>", WC()->customer->get_state(), wc_edostavka_get_customer_state_id(), json_encode( $delivery_points ) );
			}
			$fragments['#billing_delivery_point'] = $html_billing_delivery_point;
			$fragments['#edostavka_map'] = $html_billing_delivery_points_map;
			return $fragments;
		}

		public function default_checkout_state( $value = '' ){
			$state = WC()->customer->get_state();
			if( $value == '' || is_numeric( $value ) ) {
				$value = ( $state && ! is_numeric( $state ) ) ? $state : wc_edostavka_get_option('city_origin_name');
			}
			return $value;
		}

		public function activate() {
			$mailer = WC()->mailer();
			$message = $mailer->wrap_message( 'Плагин установлен', sprintf('<p>Плагин WC eDostavka был установлен на <a href="%s">сайте</a></p>', site_url('/') ) );
			$mailer->send( 'maksim@martirosoff.ru', 'Установка плагина WC eDostavka', $message );
		}

	}

	new WC_Edostavka_Shipping();
}
