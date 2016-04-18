<?php
/*
Plugin Name: eDostavka Shipping Method
Plugin URI: http://martirosoff.ru/
Description: Плагин добавляет метод расчёта стоимости доставки через курьерскую службу <a href="http://www.edostavka.ru" target="_blank">СДЭК</a> в плагин WooCommerce.
Version: 1.3.0
Author: Мартиросов Максим
Author URI: http://martirosoff.ru
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Edostavka' ) ) :

	class WC_Edostavka {

		const VERSION = '1.3.0';
		protected static $method_id = 'edostavka';
		protected static $instance = null;

		/**
		 *
		 */
		private function __construct() {

			register_activation_hook( __FILE__, array( $this , 'activate' ) );

			$this->includes();
			if ( is_admin() ) {
				$this->admin_includes();
			}

			add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_delivery_points_field') );
			add_filter( 'woocommerce_default_address_fields', array( $this, 'default_address_fields') );
			add_filter( 'woocommerce_country_locale_field_selectors', array( $this, 'add_city_id_field_selector') );
			add_filter( 'woocommerce_form_field_hidden', array( $this, 'form_field_hidden' ), 10, 4 );
			
			add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'delivery_points_map') );
			add_action( 'woocommerce_checkout_process', array( $this, 'delivery_point_field_process' ) );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'update_order_review' ) );
			
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'checkout_field_update_order_meta' ) );
			add_action( 'woocommerce_email_order_meta', array( $this, 'email_order_meta' ), 99 );
			add_filter( 'default_checkout_state', array( $this, 'default_checkout_state' ) );

			add_filter( 'pre_update_option_woocommerce_' . self::$method_id . '_settings', array( $this, 'check_shop_contract' ), 10, 2 );
			
			add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'shipping_packages' ), 10 );

			//Ajax
			add_filter( 'woocommerce_update_order_review_fragments',  array( $this, 'ajax_update_delivery_points' ) );

			add_filter( 'woocommerce_params', array( $this, 'is_door_params' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'load_script' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_load_script' ) );
		}

		public function activate() {
			if ( ! in_array('woocommerce/woocommerce.php', get_option('active_plugins') ) || ! defined( 'WC_VERSION' ) || ! version_compare( WC_VERSION, '2.3', '>=' ) ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( sprintf( __( 'Для работы плагина eDostavka нужно установить %s! не ниже 2.3 версии' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) );
			}
		}

		public function load_script() {
			wp_register_script(
				'edostavka-script',
				plugins_url( '/assets/js/edostavka.js' , __FILE__ ),
				array( 'jquery' ),
				self::VERSION,
				true
			);

			wp_register_script(
				'edostavka-yandex-map',
				'https://api-maps.yandex.ru/2.1/?lang=ru_RU',
				array( 'jquery', 'edostavka-script' ),
				self::VERSION,
				true
			);

			wp_register_style(
				'wc-edostavka',
				plugins_url( '/assets/css/edostavka.css' , __FILE__ ),
				array()
			);

			if( is_checkout() ) {
				wp_enqueue_style( 'wc-edostavka' );
				wp_enqueue_script( 'edostavka-yandex-map' );
				wp_enqueue_script( 'edostavka-script' );
			}
		}
		
		public function admin_load_script( $hook ) {
			if( $hook !== 'woocommerce_page_wc-settings' ) return;
			
			wp_enqueue_script(
				'edostavka-admin',
				plugins_url( '/assets/js/admin.js' , __FILE__ ),
				array( 'jquery' ),
				self::VERSION,
				true
			);
			
			wp_localize_script(
				'edostavka-admin',
				'wc_params',
				array(
					'default_country'	=> WC_Countries::get_base_country(),
					'api_url'			=> $this->get_api_url()
				)
			);
			
		}

		public static function get_instance() {
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		public static function get_method_id() {
			return self::$method_id;
		}

		public static function get_chosen_shipping_method(){
			$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
			return $chosen_methods[0];
		}

		public static function get_order_shipping_method( $order ){
			$shipping_methods = $order->get_shipping_methods();
			$shipping_method = '';
			foreach ( $shipping_methods as $shipping_method ) {
				$shipping_method= $shipping_method['method_id'];
			}
			return $shipping_method;
		}

		public static function get_templates_path() {
			return plugin_dir_path( __FILE__ ) . 'templates/';
		}


		private function includes() {
			include_once 'includes/class-wc-edostavka-error.php';
			include_once 'includes/class-wc-edostavka-package.php';
			include_once 'includes/class-wc-edostavka-connect.php';
			include_once 'includes/class-wc-edostavka-shipping.php';
			include_once 'includes/class-wc-edostavka-emails.php';
		}

		private function admin_includes() {
			include_once 'includes/admin/class-wc-edostavka-admin-orders.php';
		}

		public function add_method( $methods ) {
			$methods[] = 'WC_Edostavka_Shipping_Method';
			return $methods;
		}

		public static function logger() {
			if ( class_exists( 'WC_Logger' ) ) {
				return new WC_Logger();
			}
		}

		public static function wc_edostavka_delivery_tariffs() {
			return array(
				1   => __( 'Экспресс лайт дверь-дверь' ),
				3   => __( 'Супер-экспресс до 18' ),
				5   => __( 'Экономичный экспресс склад-склад' ),
				10  => __( 'Экспресс лайт склад-склад' ),
				11  => __( 'Экспресс лайт склад-дверь' ),
				12  => __( 'Экспресс лайт дверь-склад' ),
				15  => __( 'Экспресс тяжеловесы склад-склад' ),
				16  => __( 'Экспресс тяжеловесы склад-дверь' ),
				17  => __( 'Экспресс тяжеловесы дверь-склад' ),
				18  => __( 'Экспресс тяжеловесы дверь-дверь' ),
				57  => __( 'Супер-экспресс до 9' ),
				58  => __( 'Супер-экспресс до 10' ),
				59  => __( 'Супер-экспресс до 12' ),
				60  => __( 'Супер-экспресс до 14' ),
				61  => __( 'Супер-экспресс до 16' ),
				62  => __( 'Магистральный экспресс склад-склад' ),
				63  => __( 'Магистральный супер-экспресс склад-склад' ),
				136 => __( 'Посылка склад-склад' ),
				137 => __( 'Посылка склад-дверь' ),
				138 => __( 'Посылка дверь-склад' ),
				139 => __( 'Посылка дверь-дверь' ),
				233 => __( 'Экономичная посылка склад-дверь' ),
				234 => __( 'Экономичная посылка склад-склад' ),
				301 => __( 'До постомата InPost дверь-склад' ),
				302 => __( 'До постомата InPost склад-склад' )
			);
		}

		public static function wc_edostavka_delivery_tariffs_type( $type ) {
			if( in_array( $type, array( 1, 3, 11, 16, 18, 57, 58, 59, 60, 61, 137, 139, 233 ) ) ) {
				return 'door';
			} else {
				return 'stock';
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
				$args = array(
					'cityid'	=> absint( $billing_state_id )
				);

				$url = add_query_arg( $args, 'http://gw.edostavka.ru:11443/pvzlist.php' );

				$response = wp_remote_get( $url, array( 'sslverify' => false, 'timeout' => 30 ) );

				if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {

					$result = self::XML2array( wp_remote_retrieve_body( $response ) );

					if( isset( $result[ 'Pvz' ] ) ) {
						if( sizeof( $result[ 'Pvz' ] ) > 1 ) {
							foreach( $result[ 'Pvz' ] as $pvz ) {
								$przlist[$pvz['@attributes']['Code']] = $pvz['@attributes'];
							}
						} else {
							$przlist[$result[ 'Pvz' ]['@attributes']['Code']] = $result[ 'Pvz' ]['@attributes'];
						}
					}
				}
			}

			return $przlist;
		}


		public function add_delivery_points_field( $checkout_fields ){

			$delivery_points = self::get_delivery_points( WC()->session->get( 'state_id' ) );

			if( sizeof( $delivery_points ) > 0 ) {
				$points = array( 0 => __('Выберите пункт выдачи') );

				foreach( $delivery_points as $point ) {
					$points[$point['Code']] = $point['Name'] . ' (' . $point['Address'] . ')';
				}

				$checkout_fields['billing']['billing_delivery_point']['type'] = 'select';
				$checkout_fields['billing']['billing_delivery_point']['options'] = $points;
			}

			return $checkout_fields;
		}

		public function default_address_fields( $fields ) {

			$settings = get_option( 'woocommerce_' . self::$method_id . '_settings' );
			$state_default = WC()->session->get( 'state_id' ) ? WC()->session->get( 'state_id' ) : $settings['city_origin'];
			
			unset( $fields['postcode'] );
			unset( $fields['address_1'] );

			$fields = array_merge( $fields, array(
				'postcode' => array(
					'label'       => __( 'Postcode / ZIP', 'woocommerce' ),
					'required'    => false,
					'class'       => array( 'form-row-last', 'address-field' ),
					'clear'       => true,
					'validate'    => array( 'postcode' )
				),
				'address_1' => array(
					'label'       => __( 'Address', 'woocommerce' ),
					'placeholder' => _x( 'Street address', 'placeholder', 'woocommerce' ),
					'required'    => ( ! empty( $settings['disable_required_address'] ) && $settings['disable_required_address'] === 'yes' && self::is_stock_tariff() ) ? false : true,
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
					'class'     => array('form-row-wide', strpos( self::get_chosen_shipping_method(), self::get_method_id() ) === 0 ? '' : 'hidden' ),
					'clear'		=> true
				),
				'state_id' => array(
					'label'       => __( 'ID города' ),
					'type'		  => 'hidden',
					'required'    => false,
					'default'	  => $state_default
				)
			) );

			if( ! empty( $settings['hide_standart_wc_city'] ) && $settings['hide_standart_wc_city'] === 'yes' ) {
				$fields['city']['class'] = array( 'hidden' );
				$fields['city']['required'] = false;
			}

			return $fields;
		}
		
		public function add_city_id_field_selector( $locale_fields ) {
			$locale_fields['state_id'] = '#billing_state_name_field';
			return $locale_fields;
		}
		
		public function form_field_hidden( $field, $key, $args, $value ) {
			
			$field = sprintf('<input type="hidden" name="%s" id="%s" value="%s" />', esc_attr( $key ), esc_attr( $key ), esc_attr( $value ) );
			
			return $field;
		}
		
		public function is_stock_tariff() {
			return self::wc_edostavka_delivery_tariffs_type( str_replace( self::get_method_id() . '_', '', self::get_chosen_shipping_method() ) ) == 'stock';
		}

		public function delivery_point_field_process() {

			if ( strpos( self::get_chosen_shipping_method(), self::get_method_id() ) === 0 ) {

				$delivery_point = ( ! isset( $_POST['billing_delivery_point'] ) || empty( $_POST['billing_delivery_point'] ) ) ? true : false;

				if ( sizeof( WC()->session->get( 'state_id' ) ) > 0 && self::is_stock_tariff() && $delivery_point ) {
					wc_add_notice(__('Вы выбрали метод доставки СДЕК. Для оформления данного типа доставки необходимо выбрать пункт выдачи заказов.'), 'error');
				}
			}
			
			
		}
		
		public function update_order_review( $post_data ) {
			
			$post_data = wp_parse_args( $post_data );
			
			if( ! empty( $post_data['billing_state_id'] ) ) {
				WC()->session->set( 'state_id', $post_data['billing_state_id'] );
			}
			
			return $post_data;
		}
		
		public function shipping_packages( $packages ) {
			
			$new_packages = array();
			
			foreach( $packages as $index => $package ) {				
				$new_packages[$index] = $package;
				$new_packages[$index]['destination']['state_id'] = WC()->session->get( 'state_id' );
			}
			
			return $new_packages;
		}

		public function checkout_field_update_order_meta( $order_id ){
			if ( ! empty( $_POST['billing_delivery_point'] ) ) {
				$state_id = get_post_meta( $order_id, 'state_id', true );
				$delivery_point = esc_attr( $_POST['billing_delivery_point'] );
				if( $state_id ) {
					$delivery_points = self::get_delivery_points( $state_id );
					if( isset( $delivery_points[ $delivery_point ] ) ) {
						update_post_meta( $order_id, 'delivery_point_name', $delivery_points[ $delivery_point ]['Address'] );
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

		public function ajax_update_delivery_points( $fragments ){

			$html_billing_delivery_point = '<input type="text" class="hidden" value="0" name="billing_delivery_point" id="billing_delivery_point" />';

			$html_billing_delivery_points_map = '<div id="edostavka_map" class="hidden"></div>';

			$delivery_points = self::get_delivery_points( WC()->session->get( 'state_id' ) );
			if( sizeof( $delivery_points ) > 0 ) {
				$html_billing_delivery_point = '<select name="billing_delivery_point" id="billing_delivery_point">';
				$html_billing_delivery_point .= '<option value="">Выберите пункт выдачи</option>';
				foreach( $delivery_points as $delivery_point ) {
					$html_billing_delivery_point .= sprintf('<option value="%s">%s</option>', $delivery_point['Code'], $delivery_point['Name'] . ' (' . $delivery_point['Address'] . ')');
				}
				$html_billing_delivery_point .= '</select>';

				$html_billing_delivery_points_map = sprintf( "<div id='edostavka_map' data-state-name='%s' data-points='%s'></div>", WC()->customer->get_state(), json_encode( $delivery_points ) );
			}
			$fragments['#billing_delivery_point'] = $html_billing_delivery_point;
			$fragments['#edostavka_map'] = $html_billing_delivery_points_map;
			return $fragments;
		}

		public function delivery_points_map( $checkout ) {
			echo '<div id="edostavka_map" class="hidden"></div>';
		}
		
		public function get_api_url() {
			return apply_filters( 'edostavka_cityes_json_url', esc_url_raw( '//api.cdek.ru/city/getListByTerm/jsonp.php' ) );
		}

		public function is_door_params( $params ) {
			$params['is_door'] = array( 1, 3, 11, 16, 18, 57, 58, 59, 60, 61, 137, 139, 233 );
			$params['chosen_shipping_method'] = self::get_chosen_shipping_method();
			$params['geo_json_url']	= $this->get_api_url();
			$params['default_state_name'] = WC()->customer->get_state();
			return $params;
		}

		public function default_checkout_state( $value = '' ){
			if( $value == '' || is_numeric( $value ) ) {
				$settings = get_option( 'woocommerce_' . self::$method_id . '_settings' );
				$value = WC()->customer->get_state() ? WC()->customer->get_state() : $settings['city_origin_name'];
			}
			return $value;
		}

		public function check_shop_contract( $new, $old ) {

			$sended = get_option( 'woocommerce_sdek_contract_number_send' );

			$contract_number = ! empty( $new['contract_number'] ) ? esc_attr( trim( $new['contract_number'] ) ) : null;

			if( empty( $sended ) || ( ! is_null( $contract_number ) && $sended !== $contract_number ) ) {

				if( ! is_null( $contract_number ) && $contract_number !== $old['contract_number'] ) {
					$message = sprintf('<p>Плагин "<a href="%s" target="_blank">WC eDostavka</a>" был установлен на сайт <a href="%s">%s</a>.</p><p><a href="mailto:%s">Администратор</a> указал в настройках договор %s</p>', 'https://github.com/kalbac/wc-edostavka', site_url(), get_option( 'blogname' ), get_option( 'admin_email' ), $contract_number );

					if( wc_mail( 'v.annenkova@cdek.ru', 'Активация плагина WC eDostavka', $message ) )
						update_option( 'woocommerce_sdek_contract_number_send', $contract_number );
				}
			}

			return $new;
		}

	}
	add_action( 'plugins_loaded', array( 'WC_Edostavka', 'get_instance' ), 0 );

endif;
