<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Edostavka_Connect {
	
	private $version = "1.0";
	private $_webservice = 'http://api.edostavka.ru/calculator/calculate_price_by_json.php';
	
	protected $services = array();
	protected $package = array();
	protected $city_origin = '';
	protected $city_destination = '';
	protected $height = 0;
	protected $width = 0;
	protected $length = 0;
	protected $weight = 0;
	protected $dateExecute = '';
	protected $login = '';
	protected $password = '';
	protected $debug = 'no';
	
	public function __construct() {
		$this->id = WC_Edostavka::get_method_id();
		$this->dateExecute = current_time( 'mysql' );
		if ( class_exists( 'WC_Logger' ) ) {
			$this->log = new WC_Logger();
		}
	}
	
	public function set_services( $services = array() ) {
		$this->services = $services;
	}
	
	public function set_package( $package = array() ) {
		$this->package = new WC_Edostavka_Package( $package );
		return $this->package;
	}
	
	public function set_city_origin( $city_origin = '' ) {
		$this->city_origin = $city_origin;
	}
	
	public function set_city_destination( $city_destination = '' ) {
		$this->city_destination = $city_destination;
	}
	
	public function set_height( $height = 0 ) {
		$this->height = $height;
	}
	
	public function set_width( $width = 0 ) {
		$this->width = $width;
	}
	
	public function set_length( $length = 0 ) {
		$this->length = $length;
	}
	
	public function set_weight( $weight = 0 ) {
		$this->weight = $weight;
	}
	
	public function set_date( $date ) {
		$this->dateExecute = $date;
	}
	
	public function set_login( $login = '' ) {
		$this->login = $login;
	}
	
	public function set_password( $password = '' ) {
		$this->password = $password;
	}
	
	private function _getSecureAuthPassword() {
		return md5( $this->dateExecute . '&' . $this->password );
	}
	
	public function set_debug( $debug = 'no' ) {
		$this->debug = $debug;
	}
	
	protected function float_to_string( $value ) {
		$value = str_replace( '.', ',', $value );
		return $value;
	}
    
    public static function get_fee( $fee, $total ) {
		if ( strstr( $fee, '%' ) ) {
			$fee = ( $total / 100 ) * str_replace( '%', '', $fee );
		}
		return $fee;
	}
	
	public static function get_service_name( $code ) {
		
		$name = WC_Edostavka::wc_edostavka_delivery_tariffs();
		
		if ( ! isset( $name[ $code ] ) ) {
			return '';
		}
		return $name[ $code ];
	}
	
	public static function estimating_delivery( $label, $date, $additional_time = 0 ) {
		$name = $label;
		$additional_time = intval( $additional_time );
		if ( $additional_time > 0 ) {
			$date += intval( $additional_time );
		}
		if ( $date > 0 ) {
			$name .= ' (' . sprintf( __( '<span title="Срок доставки">%s</span>' ),  human_time_diff( strtotime("+$date day") ) ) . ')';
		}
		return $name;
	}
	
	public function get_shipping() {
		$values = array();

		if (
			! is_array( $this->services )
			|| empty( $this->services )
			|| empty( $this->city_destination )
			|| empty( $this->city_origin )
		) {
			return $values;
		}
		if (
			0 == $this->height
			&& 0 == $this->width
			&& 0 == $this->length
			&& 0 == $this->weight
			&& ! empty( $this->package )
		) {
			$package = $this->package->get_data();
			$this->height = $package['height'];
			$this->width  = $package['width'];
			$this->length = $package['length'];
			$this->weight = $package['weight'];
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'edostavka', 'Вес и объём заказа: ' . print_r( $package, true ) );
			}
		} else {
			if ( 'yes' == $this->debug ) {
				$package = array(
					'weight' => $this->weight,
					'height' => $this->height,
					'width'  => $this->width,
					'length' => $this->length
				);
				$this->log->add( 'edostavka', 'Вес и объём заказа: ' . print_r( $package, true ) );
			}
		}
		
		$args = apply_filters( 'woocommerce_edostavka_shipping_args', array(
			'version'			  => $this->version,
			'dateExecute'		  => $this->dateExecute,
			'receiverCityId'      => $this->city_destination,
			'senderCityId'        => $this->city_origin,
			'goods'				  => $this->package->get_goods()
		) );
		
		if( ! empty( $this->login ) && ! empty( $this->password ) ) {			
			$args['authLogin'] = $this->login;
			$args['secure'] = $this->_getSecureAuthPassword();			
		}
		
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'Вес и объём для каждого товара: ' . print_r( $this->package->get_goods(), true ) );
		}
		
		$url = apply_filters( 'woocommerce_edostavka_webservice_url', $this->_webservice );
		
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, 'Отправка запроса к серверу СДЕК.' . print_r( $args, true ) );
		}
		
		foreach( $this->services as $service ) {
			
			$args['tariffId'] = $service;
			
			$response = wp_remote_post( $url, array(
				'timeout' => 30,
				'sslverify' => false,
				'httpversion' => '1.0',
				'cookies' => array(),
				'blocking' => true,
				'headers' => array('Content-Type' => 'application/json'),
				'body' => json_encode( $args )
				)
			);
			
			if ( is_wp_error( $response ) ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, 'WP_Error: ' . $response->get_error_message() );
				}
			} elseif ( $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
				
				$result = json_decode( wp_remote_retrieve_body( $response ), true );
				
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, 'Ответ от сервера СДЕК [' . self::get_service_name( $service ) . ']: ' . print_r( $result, true ) );
				}
				$values[ $service ] = $result;
				
			} else {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, 'Ошибка доступа к серверу СДЕК: ' . print_r( $response, true ) );
				}
			}
		}
		
		return $values;
	}
}