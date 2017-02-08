<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function wc_edostavka_get_customer_state_id() {
	return ! empty( WC()->customer->state_id ) ? WC()->customer->state_id : wc_edostavka_get_option( 'city_origin' );
}

function wc_edostavka_get_chosen_shipping_method() {
	$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
	$method = $instance = $tariff = 0;
	$chosen_method = explode( ':', current( $chosen_methods ) );
	if( count( $chosen_method ) == 3 ) {
		list( $method, $instance, $tariff ) = $chosen_method;
	} elseif( count( $chosen_method ) == 2 ) {
		list( $method, $instance ) = $chosen_method;
	}

	return array(
		'method'	=> $method,
		'instance'	=> $instance,
		'tariff'	=> $tariff
	);
}

if( ! function_exists( 'wc_get_chosen_shipping_method_ids' ) && ! is_admin() ) {
	function wc_get_chosen_shipping_method_ids() {
		$method_ids     = array();
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		foreach ( $chosen_methods as $chosen_method ) {
			$chosen_method = explode( ':', $chosen_method );
			$method_ids[]  = current( $chosen_method );
		}
		return $method_ids;
	}
}

function wc_edostavka_get_option( $option_name, $default = null ) {
	$settings = get_option( 'woocommerce_' . WC_Edostavka_Shipping::get_method_id() . '_settings' );
	if( $option_name && isset( $settings[ $option_name ] ) ) {
		return $settings[ $option_name ];
	}

	return $default;
}

/*
* Функция возвращает экземпляр класса WC_Logger для создания файлов логирования.
*/

function wc_edostavka_logger() {
	if ( class_exists( 'WC_Logger' ) ) {
		return new WC_Logger();
	}
}

function wc_edostavka_add_log( $message ) {
	if( 'yes' == wc_edostavka_get_option( 'debug' ) && ! empty( $message ) ) {
		$logger = wc_edostavka_logger();
		$logger->add( WC_Edostavka_Shipping::get_method_id(), $message );
	}
}

/*
* Функция возвращает название тарифа по переданному в качестве аргумента айдишнику данного тарифа.
* Название тарифа можно изменять черех фильтр edostavka_delivery_tariff_name
*/

function wc_edostavka_get_delivery_tariff_name( $tariff_id = 0 ) {
	$tariffs = wc_edostavka_get_delivery_tariffs();
	$tariff = array_shift( wp_list_filter( $tariffs, array( 'id' => $tariff_id ) ) );
	if( ! empty( $tariff['label'] ) ) {
		return apply_filters( 'edostavka_delivery_tariff_name', $tariff['label'], $tariff_id );
	}

	return 'Тариф без названия';
}

/*
* Функция возвращает либо тип тарифного плана либо айдишники тарифов в зависимости от переданного аргумента.
* В случае есть передать в качестве аргумента айдишник тарифа, то функция вернет тип тарифа 'door', 'stock'.
* Если передать в качестве аргумента тип тарифа (см.выше) то вернётся массив содержащий айдишники всех тарифов подходящие под этот тип.
* В противном случае (не верный аргумент) вернётся булево false.
*/

function wc_edostavka_get_delivery_tariff_type( $num_or_type = 0 ) {
	$tariffs = wc_edostavka_get_delivery_tariffs();
	if( is_numeric( $num_or_type ) ) {
		$type = wp_list_filter( $tariffs, array( 'id' => $num_or_type ) );
		if( count( $type ) > 0 ) {
			$type = array_shift( $type );
			if( ! empty( $type['type'] ) ) {
				return $type['type'];
			}
		}
	} elseif( in_array( $num_or_type, array( 'door', 'stock' ) ) ) {
		return wp_list_pluck( wp_list_filter( $tariffs, array( 'type' => $num_or_type ) ), 'id' );
	}
	return false;
}

/*
* Функция возвращает массив содержащий онформацию о тарифах СДЭК.
* Данной функцией можно усправлять хуком edostavka_delivery_tariffs
*/
function wc_edostavka_get_delivery_tariffs() {
	return apply_filters( 'edostavka_delivery_tariffs', array(
		array(
			'id'	=> 1,
			'label'	=> 'Экспресс лайт дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 3,
			'label'	=> 'Супер-экспресс до 18',
			'type'	=> 'door'
		),
		array(
			'id'	=> 5,
			'label'	=> 'Экономичный экспресс склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 7,
			'label'	=> 'Международный экспресс документы дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 8,
			'label'	=> 'Международный экспресс грузы дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 10,
			'label'	=> 'Экспресс лайт склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 11,
			'label'	=> 'Экспресс лайт склад-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 12,
			'label'	=> 'Экспресс лайт дверь-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 15,
			'label'	=> 'Экспресс тяжеловесы склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 16,
			'label'	=> 'Экспресс тяжеловесы склад-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 17,
			'label'	=> 'Экспресс тяжеловесы дверь-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 18,
			'label'	=> 'Экспресс тяжеловесы дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 57,
			'label'	=> 'Супер-экспресс до 9',
			'type'	=> 'door'
		),
		array(
			'id'	=> 58,
			'label'	=> 'Супер-экспресс до 10',
			'type'	=> 'door'
		),
		array(
			'id'	=> 59,
			'label'	=> 'Супер-экспресс до 12',
			'type'	=> 'door'
		),
		array(
			'id'	=> 60,
			'label'	=> 'Супер-экспресс до 14',
			'type'	=> 'door'
		),
		array(
			'id'	=> 61,
			'label'	=> 'Супер-экспресс до 16',
			'type'	=> 'door'
		),
		array(
			'id'	=> 62,
			'label'	=> 'Магистральный экспресс склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 63,
			'label'	=> 'Магистральный супер-экспресс склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 136,
			'label'	=> 'Посылка склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 137,
			'label'	=> 'Посылка склад-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 138,
			'label'	=> 'Посылка дверь-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 139,
			'label'	=> 'Посылка дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 180,
			'label'	=> 'Международный экспресс грузы дверь-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 183,
			'label'	=> 'Международный экспресс документы дверь-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 184,
			'label'	=> 'Международный экономичный экспресс дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 185,
			'label'	=> 'Международный экономичный экспресс дверь-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 233,
			'label'	=> 'Экономичная посылка склад-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 234,
			'label'	=> 'Экономичная посылка склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 291,
			'label'	=> 'CDEK Express склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 293,
			'label'	=> 'CDEK Express дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 294,
			'label'	=> 'CDEK Express склад-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 295,
			'label'	=> 'CDEK Express дверь-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 243,
			'label'	=> 'Китайский экспресс склад-склад',
			'type'	=> 'stock'
		),
		array(
			'id'	=> 245,
			'label'	=> 'Китайский экспресс дверь-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 246,
			'label'	=> 'Китайский экспресс склад-дверь',
			'type'	=> 'door'
		),
		array(
			'id'	=> 247,
			'label'	=> 'Китайский экспресс дверь-склад',
			'type'	=> 'stock'
		)
	));
}
