<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Edostavka_Connect {
	
	private $api_version = "1.0";
	private $api_url = 'https://api.cdek.ru/calculator/calculate_price_by_json.php';
	
	public function __construct( $package = array(), $args = array() ) {
		$this->date				= current_time( 'mysql' );
		$this->login			= $args['login'];
		$this->package 			= $package;
		$this->tariffs 			= $args['tariffs'];		
		$this->password			= $args['password'];
		$this->sender_city 		= $args['sender_city'];
		$this->receiver_city 	= $args['receiver_city'];
		
		
		foreach( array( 'height', 'width', 'length', 'weight' ) as $dimension ) {
			add_filter( 'woocommerce_product_' . $dimension, array( $this, 'add_dimension' ) );
		}
	}
	
	public function add_dimension( $value ) {
		if( empty( $value ) OR 0 == $value ) {
			$filter = current_filter();
			if( strstr( $filter, 'woocommerce_product_' ) ) {
				$option = wc_edostavka_get_option( 'minimum_' . str_replace( 'woocommerce_product_', '', $filter ), 0 );
				if( $option > 0 ) $value = $option;
			}
		}
		return $value;
	}
	
	private function fix_format( $value ) {
		$value = str_replace( ',', '.', $value );
		return $value;
	}
	
	private function get_goods() {
		
		$goods = array();
		$count = 0;
		
		foreach ( $this->package['contents'] as $item_id => $values ) {
			$product = $values['data'];
			$qty     = $values['quantity'];
			if ( $qty > 0 && $product->needs_shipping() ) {
			
				$goods[] = array(
					'height' 	=> wc_get_dimension( $this->fix_format( $product->get_height() ), 'cm' ) * $qty,
					'width'		=> wc_get_dimension( $this->fix_format( $product->get_width() ), 'cm' ) * $qty,
					'length'	=> wc_get_dimension( $this->fix_format( $product->get_length() ), 'cm' ) * $qty,
					'weight'	=> wc_get_weight( $this->fix_format( $product->get_weight() ), 'kg' ) * $qty
				);
			}
		}		
		
		return $goods;
	}
	
	public function get_rates() {
		$rates = array();
		
		$args = array(
			'version'			=> $this->api_version,
			'dateExecute'		=> $this->date,
			'receiverCityId'	=> $this->receiver_city,
			'senderCityId'		=> $this->sender_city,
			'goods'				=> $this->get_goods()
		);
		
		if( ! empty( $this->login ) && ! empty( $this->password ) ) {
			$args['authLogin'] = $this->login;
			$args['secure'] = md5( $this->date . '&' . $this->password );
		}
		
		wc_edostavka_add_log( 'Вес и объём для каждого товара: ' . print_r( $this->get_goods(), true ) );
		
		foreach( $this->tariffs as $tariff ) {
			
			$args['tariffId'] = $tariff;
			
			$response = wp_safe_remote_post( apply_filters( 'woocommerce_edostavka_webservice_url', $this->api_url ), array(
					'timeout' => 10,
					'sslverify' => false,
					'headers' => array('Content-Type' => 'application/json'),
					'body' => json_encode( $args )
				)
			);
			
			if ( is_wp_error( $response ) ) {
				wc_edostavka_add_log( sprintf('Ошибка при отправке запроса к серверу СДЭК: %s', $response->get_error_message() ) );
			} elseif ( wp_remote_retrieve_response_code( $response ) == 200 ) {
				
				$result = json_decode( wp_remote_retrieve_body( $response ), true );
				
				wc_edostavka_add_log( sprintf('Ответ на запрос тарифа [%s]', wc_edostavka_get_delivery_tariff_name( $tariff ) ) );
				
				if( isset( $result['error'] ) ) {
					wc_edostavka_add_log( sprintf('Сервер СДЭК вернул ошибку: %s', print_r( wp_list_pluck( $result['error'], 'text', 'code' ), true ) ) );
				}
				
				if ( ! isset( $result['result'] ) ) {
					continue;
				}
				
				wc_edostavka_add_log( sprintf('Сервер СДЭК вернул результат: %s', print_r( $result['result'], true ) ) );
				
				$rates[ $tariff ] = $result['result'];
				
			} else {
				wc_edostavka_add_log( sprintf('Сервер СДЭК вернул не корректный статус: %s', wp_remote_retrieve_response_code( $response ) ) );
			}
		}
		
		return $rates;
	}
}