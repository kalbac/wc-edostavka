<?php
/*
Plugin Name: eDostavka Shipping Method
Plugin URI: http://martirosoff.ru/
Description: Плагин добавляет метод расчёта стоимости доставки через курьерскую службу <a href="http://www.edostavka.ru" target="_blank">СДЭК</a> в плагин WooCommerce.
Version: 1.2
Author: Мартиросов Максим
Author URI: http://martirosoff.ru
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Edostavka' ) ) :

class WC_Edostavka {
	
	const VERSION = '1.2';
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
		} else {
			wp_enqueue_style( 'wc-edostavka', WP_PLUGIN_URL . '/wc-edostavka/assets/css/edostavka.css', array());
		}
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_method' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_ons_attributes') );
		add_action( 'woocommerce_checkout_process', array( $this, 'delivery_point_field_process' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'checkout_field_update_order_meta' ) );
		add_action( 'woocommerce_email_order_meta', array( $this, 'email_order_meta' ), 99 );
		add_filter( 'default_checkout_state', array( &$this, 'default_checkout_state' ), 99 );

		add_filter( 'woocommerce_checkout_fields' , array($this, 'override_checkout_billing_state'));

		//Ajax
		add_filter( 'woocommerce_update_order_review_fragments',  array( $this, 'ajax_update_delivery_points' ) );

		add_filter( 'woocommerce_params', array( $this, 'is_door_params' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'load_script' ) );
	}

	public function activate() {
		if ( ! in_array('woocommerce/woocommerce.php', get_option('active_plugins') ) || ! defined( 'WC_VERSION' ) || ! version_compare( WC_VERSION, '2.3', '>=' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( sprintf( __( 'Для работы плагина eDostavka нужно установить %s! не ниже 2.3 версии' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) );
		}
	}

	public function load_script() {
		if( is_checkout() )
			wp_enqueue_script(
				'edostavka-script',
				plugins_url( '/assets/js/edostavka.js' , __FILE__ ),
				array( 'jquery' )
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
				136 => __( 'Экспресс-доставка до пункта выдачи' ),
				137 => __( 'Экспресс-доставка курьером до двери' ),
				138 => __( 'Посылка дверь-склад' ),
				139 => __( 'Посылка дверь-дверь' ),
			);
	}
	
	public static function wc_edostavka_delivery_tariffs_type( $type ) {
		if( in_array( $type, array( 1, 3, 11, 16, 18, 57, 58, 59, 60, 61, 137, 139 ) ) ) {
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
	
	public static function get_delivery_points( $billing_state ) {
		
		$przlist = array();
		
		if ( class_exists( 'SimpleXmlElement' ) ) {
			$args = array(
				'cityid'	=> absint( $billing_state )
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

	function override_checkout_billing_state( $fields ) {
		if (isset($fields['billing']['billing_state'])) {
			$fields['billing']['billing_state']['label'] = __( 'City', 'woocommerce' );
			$fields['billing']['billing_state']['required'] = true;
		} else {
			$fields['billing']['billing_state'] = array(
				'type' => 'state',
				'label' => __( 'Town / City', 'woocommerce' ),
				'required'  => true
			);
		}
		return $fields;
	}

	public function add_ons_attributes( $checkout_fields ){

		$checkout_fields['billing']['billing_delivery_point'] = array(
			'label'     => __('Пункт выдачи заказов'),
			'required'  => false,
			'class'     => array('form-row-wide', strpos( self::get_chosen_shipping_method(), self::get_method_id() ) === 0 ? '' : 'hidden' ),
			'clear'		=> true
		);

		$delivery_points = self::get_delivery_points( WC()->customer->get_state() );

		if( sizeof( $delivery_points ) > 0 ) {
			$points = array( 0 => __('Выберите пункт выдачи') );

			foreach( $delivery_points as $point ) {
				$points[$point['Code']] = $point['Name'] . ' (' . $point['Address'] . ')';
			}

			$checkout_fields['billing']['billing_delivery_point']['type'] = 'select';
			$checkout_fields['billing']['billing_delivery_point']['options'] = $points;

			if( self::wc_edostavka_delivery_tariffs_type( str_replace( self::get_method_id() . '_', '', self::get_chosen_shipping_method() ) ) == 'stock' ) {
				$checkout_fields['billing']['billing_address_1']['required'] = false;
			}
		}

		return $checkout_fields;
	}

	public function delivery_point_field_process() {

		if ( strpos( self::get_chosen_shipping_method(), self::get_method_id() ) === 0 ) {

			$delivery_points = self::get_delivery_points( esc_attr( $_POST['billing_state'] ) );
			$is_stock = self::wc_edostavka_delivery_tariffs_type( str_replace( self::get_method_id() . '_', '', self::get_chosen_shipping_method() ) ) == 'stock' ? true : false;
			$delivery_point = ( ! isset( $_POST['billing_delivery_point'] ) || empty( $_POST['billing_delivery_point'] ) ) ? true : false;

			if ( sizeof( $delivery_points ) > 0 && $is_stock && $delivery_point ) {
				wc_add_notice(__('Вы выбрали метод доставки СДЕК. Для оформления данного типа доставки необходимо выбрать пункт выдачи заказов.'), 'error');
			}
		}
	}

	public function checkout_field_update_order_meta( $order_id ){
		if ( ! empty( $_POST['billing_delivery_point'] ) ) {
			$state_id = get_post_meta( $order_id, 'billing_state', true );
			$delivery_point = esc_attr( $_POST['billing_delivery_point'] );
			if( $state_id ) {
				$delivery_points = self::get_delivery_points( $state_id );
				if( isset( $delivery_points[ $delivery_point ] ) ) {
					update_post_meta( $order_id, 'delivery_point_name', $delivery_points[ $delivery_point ]['Address'] );
				}
			}
			update_post_meta( $order_id, '_delivery_point', $delivery_point );
		}
	}

	public function email_order_meta( $order_id ){
		$order = wc_get_order( $order_id );
		if( strpos( self::get_order_shipping_method( $order ), self::get_method_id() ) === 0 ) {
			$state = absint( $order->billing_state );
			$delivery_point = get_post_meta( $order->id, '_delivery_point', true );
			$delivery_points = self::get_delivery_points( $state );
			if( isset( $delivery_points[ $delivery_point ] ) ) {
				echo '<h2>Пункт выдачи заказов</h2>';
				echo '<p><strong>Адрес</strong>: ' . $delivery_points[ $delivery_point ]['Address'] . '</p>';
				echo '<p><strong>Режим работы</strong>: ' . $delivery_points[ $delivery_point ]['WorkTime'] . '</p>';
				echo '<p><strong>Телефон:</strong>: ' . $delivery_points[ $delivery_point ]['Phone'] . '</p>';
				echo '<p><strong>Примечания:</strong>: ' . $delivery_points[ $delivery_point ]['Note'] . '</p>';
			}
		}
	}

	public function ajax_update_delivery_points( $fragments ){

		$html_billing_delivery_point = '<input type="text" class="hidden" value="0" name="billing_delivery_point" id="billing_delivery_point" />';

		$delivery_points = self::get_delivery_points( WC()->customer->get_state() );
		if( sizeof( $delivery_points ) > 0 ) {
			$html_billing_delivery_point = '<select name="billing_delivery_point" id="billing_delivery_point">';
			$html_billing_delivery_point .= '<option value="">Выберите пункт выдачи</option>';
			foreach( $delivery_points as $delivery_point ) {
				$html_billing_delivery_point .= sprintf('<option value="%s">%s</option>', $delivery_point['Code'], $delivery_point['Name'] . ' (' . $delivery_point['Address'] . ')');
			}
			$html_billing_delivery_point .= '</select>';
		}
		$fragments['#billing_delivery_point'] = $html_billing_delivery_point;
		return $fragments;
	}

	public function is_door_params( $params ) {
		$params['is_door'] = array( 1, 3, 11, 16, 18, 57, 58, 59, 60, 61, 137, 139 );
		$params['chosen_shipping_method'] = self::get_chosen_shipping_method();
		return $params;
	}

	public function default_checkout_state( $value ){
		if( $value == '' ) {
			$value = WC()->customer->get_state() ? WC()->customer->get_state() : WC()->countries->get_base_state();
		}
		return $value;
	}

}
	add_action( 'plugins_loaded', array( 'WC_Edostavka', 'get_instance' ), 0 );

endif;

add_filter( 'woocommerce_states', 'edostavka_load_states', 999 );

/**
 * @param $states
 * @description load states to woocommerce
 * @return mixed
 */
function edostavka_load_states( $states ) {

	$countries = array(
		'RU',
		'KZ',
		'BY',
		'UA'
	);

	foreach ( $countries as $country ) {
		if ( ! isset( $states[ $country ] ) && file_exists( untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/includes/states/' . $country . '.php' ) ) {
			$states[ $country ] = include( untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/includes/states/' . $country . '.php' );
		}
	}

	return $states;
}


