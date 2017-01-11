<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* Shipping method class
*/
class WC_Edostavka_Shipping_Method extends WC_Shipping_Method {
	
	var $available_rates;
	var $id;
	var $instance_id;

	
	public function __construct( $instance_id = 0 ) {
		global $wpdb;

		$this->id                 = WC_Edostavka_Shipping::get_method_id();
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = 'Edostavka (СДЭК)';
		$this->method_description = 'Расчёт стоимости доставки СДЭК';
		$this->title              = $this->method_title;
		$this->has_settings       = true;
		$this->supports           = array( 'zones', 'shipping-zones', 'instance-settings', 'settings' );

		$this->init_form_fields();

		$this->enabled            = 'yes';
		$this->title              = $this->get_option( 'title' );
		$this->display_date       = $this->get_option( 'display_date' );
		$this->additional_time    = $this->get_option( 'additional_time' );
		$this->fee                = $this->get_option( 'fee' );
		$this->error_text   	  = $this->get_option( 'error_text' );
		$this->show_error   	  = $this->get_option( 'show_error' );
		$this->available_tariffs  = $this->get_option( 'available_tariffs' );

		$this->available_rates = array();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	
	public function init_form_fields() {
		$this->form_fields     = array(
			'services' => array(
				'title'            => __( 'Настройка для СДЕК' ),
				'type'             => 'title'
			),
			'login' => array(
				'title' 		=> __( 'API логин' ),
				'type' 			=> 'text',
				'description'	=> __( 'Логин, выдается компанией СДЭК по вашему запросу. Обязательны для учета индивидуальных тарифов и учета условий доставок по тарифам «посылка». Запрос необходимо отправить на адрес integrator@cdek.ru с указанием номера договора со СДЭК. Важно: Учетная запись для интеграции не совпадает с учетной записью доступа в Личный Кабинет СДЭК.' ),
				'default'		=> '',
				'desc_tip'      => true
			),
			'password' => array(
				'title' 		=> __( 'API секретный ключ' ),
				'type' 			=> 'text',
				'description'	=> __( 'Пароль, выдаётся компанией СДЭК по вашему запросу' ),
				'default'		=> '',
				'desc_tip'      => true
			),
			'checkout' => array(
				'title'            => __( 'Настройки для расчтёта' ),
				'type'             => 'title'
			),
			'city_origin_name' => array(
				'title' 		=> __( 'Город отправитель' ),
				'description'	=> __( 'Укажите город откуда будут отправлять посылки.' ),
				'desc_tip'      => true,
				'default'		=> ''
			),
			'city_origin' => array(
				'title' 		=> __( 'ID Города отправитель' ),
				'type' 			=> 'hidden',
				'default'		=> ''
			),
			'address_required' => array(
				'title' 	  => __( 'Адрес не обязательный' ),
				'description' => __('Сделать поле "Адрес" не обязательным для заполнения если выбран другой метод доставки (не СДЕК) или выбран метод доставки до ПВЗ.'),
				'type'        => 'checkbox',
				'label'       => 'Да',
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'hide_city' => array(
				'title'       => __('Скрыть стандартное поле ввода "Населенный пункт"'),
				'type'        => 'checkbox',
				'label'       => 'Да',
				'default'     => 'no'
			),
			'package_standard' => array(
				'title'            => __( 'Параметры товара' ),
				'type'             => 'title',
				'description'      => __( 'Укажите параметры товара по умолчанию' ),
				'desc_tip'         => true,
			),
			'minimum_weight' => array(
				'title' 		=> __( 'Масса по умолчанию, (кг.)' ),
				'type' 			=> 'text',
				'description' 	=> __('Укажите массу одного товара по умолчанию. Эта масса будет использоваться в расчете доставки одной единицы товара, если у товара не будет указана его масса в карточке товара.'),
				'default'		=> 0.5,
				'desc_tip'      => true,
			),
			'minimum_height' => array(
				'title' 		=> __( 'Высота по умолчанию, (см.)' ),
				'type' 			=> 'text',
				'description' 	=> __('Укажите высоту одного товара по умолчанию.'),
				'default'		=> 15,
				'desc_tip'      => true,
			),
			'minimum_width' => array(
				'title' 		=> __( 'Ширина по умолчанию, (см.)' ),
				'type' 			=> 'text',
				'description' 	=> __('Укажите ширину одного товара по умолчанию.'),
				'default'		=> 15,
				'desc_tip'      => true,
			),
			'minimum_length' => array(
				'title' 		=> __( 'Длина по умолчанию, (см.)' ),
				'type' 			=> 'text',
				'description' 	=> __('Укажите длину одного товара по умолчанию.'),
				'default'		=> 15,
				'desc_tip'      => true,
			),
			'testing' => array(
				'title'            => __( 'Тестирование' ),
				'type'             => 'title'
			),
			'debug' => array(
				'title'            => __( 'Режим отладки' ),
				'type'             => 'checkbox',
				'label'            => __( 'Включить логирование в режиме отладки' ),
				'default'          => 'no',
				'description'      => sprintf( __( '<p>Все логи будут записаны в <code>%s</code>.</p><p>Посмотреть логи можно на <a href="%s">странице отчётов</a></p>' ), wc_get_log_file_path( $this->id ), add_query_arg( array( 'page' => 'wc-status', 'tab' => 'logs' ), admin_url( 'admin.php') ) )
			),
			'donate' => array(
				'title'			=> 'Поддержать автора',
				'type'			=> 'title',
				'default'		=> '',
				'description'	=> sprintf('Спасибо, что используете мой плагин. Если Вам понравился плагин и вы заинтересованы в его развитии, то вы можете поддержать автора <a href="%s" target="_blank">сделав пожертвование</a>.', add_query_arg( array( 'account' => '41001231735306', 'quickpay' => 'donate', 'payment-type-choice' => 'on', 'default-sum' => 1000, 'targets' => urlencode('Пожервование на поддержку плагина WC eDostavka'), 'target-visibility' => 'on', 'button-text' => '05' ), esc_url( 'https://money.yandex.ru/embed/donate.xml' ) ) )
			)
		);
		$this->instance_form_fields = array(
			'title' => array(
				'title' 		=> __( 'Название метода доставки' ),
				'type' 			=> 'text',
				'description' 	=> __( 'Это название будет показыватся пользователям при оформлении заказа' ),
				'default'		=> __( 'еДоставка' ),
				'desc_tip'      => true,
			),
			'display_date' => array(
				'title'            => __( 'Срок доставки' ),
				'type'             => 'checkbox',
				'label'            => __( 'Включить' ),
				'description'      => __( 'Показывать количество дней доставки?' ),
				'desc_tip'         => true,
				'default'          => 'no'
			),
			'additional_time' => array(
				'title'            => __( 'Добавочные дни к доставке' ),
				'type'             => 'text',
				'description'      => __( 'Укажите сколько дней прибавлять к сроку доставки.' ),
				'desc_tip'         => true,
				'default'          => '0',
				'placeholder'      => '0'
			),
			'fee' => array(
				'title' 		=> __( 'Наценка' ),
				'type' 			=> 'text',
				'description'	=> __( 'Укажите наценку за обработку заказа в рублях или в процентах. Например 200 или 5%' ),
				'default'		=> '',
				'desc_tip'      => true,
				'placeholder'	=> '0.00'
			),
			'show_error' => array(
				'title'            => __( 'Уведомление в оформление заказа' ),
				'type'             => 'checkbox',
				'label'            => 'Включить',
				'description'      => __( 'Показывать текст на странице оформления заказа, если не найден ни однин тариф по указанному направлению?' ),
				'desc_tip'         => true,
				'default'          => 'no'
			),
			'error_text' => array(
				'title' 		=> __( 'Уведомление' ),
				'type' 			=> 'textarea',
				'description'	=> __( 'Напишите текст который будет отображатся на странице оформления заказа в случае если по указоному маршруту не найдено ни одного тарифа.' ),
				'default'		=> __( 'Нет ни одного доступного тарифа в указанный город/область.' ),
				'desc_tip'      => true
			),
			'available_tariffs' => array(
				'title' 		=> __( 'Разрешенные тарифы' ),
				'description'	=> __( 'Укажите какие тарифы могут использоваться при расчёте стоимости доставки.' ),
				'desc_tip'      => true,
				'type' 			=> 'multiselect',
				'class'			=> 'chosen_select',
				'options'		=> wp_list_pluck( wc_edostavka_get_delivery_tariffs(), 'label', 'id' ),
				'default'		=> ''
			)
		);
	}
	
	public function generate_hidden_html( $key, $data ) {
		$field    = $this->get_field_key( $key );
		$defaults = array(
			'type'              => 'hidden',
		);

		$data = wp_parse_args( $data, $defaults );
		
		printf('<input type="%s" name="%s" id="%s" value="%s" />', $data['type'], esc_attr( $field ), esc_attr( $field ), esc_attr( $this->get_option( $key ) ) );
	}
	
	public function is_available( $package ) {
		$available = true;

		if ( ! $this->get_rates( $package ) ) {
			$available = false;
		}

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $available, $package, $this );
	}
	
	private function get_receiver_city( $package ) {
		$city_origin = wc_edostavka_get_option( 'city_origin' );
		if( ! empty( $package['destination']['state_id'] ) && is_numeric( $package['destination']['state_id'] ) ) {
			return absint( $package['destination']['state_id'] );
		} elseif ( ! empty( WC()->customer->state_id ) ) {
			return absint( WC()->customer->state_id );
		} elseif( ! empty( $city_origin ) ) {
			return absint( $city_origin );
		}
	}
	
	private function fix_format( $value ) {
		$value = str_replace( '.', '', $value );
		$value = str_replace( ',', '.', $value );
		return $value;
	}
	
	public function get_rates( $package ) {
		global $wpdb;

		if ( ! $this->instance_id )
			return false;

		$rates = array();
		
		$args = array(
			'tariffs'			=> $this->available_tariffs,
			'login'				=> wc_edostavka_get_option( 'login' ),
			'password'			=> wc_edostavka_get_option( 'password' ),
			'sender_city'		=> wc_edostavka_get_option( 'city_origin' ),
			'receiver_city'		=> $this->get_receiver_city( $package )
		);
		
		wc_edostavka_add_log( 'Параметры запроса: ' . print_r( $args, true ) );
		
		if( ! class_exists( 'WC_Edostavka_Connect' ) ) {
			include_once( 'class-wc-edostavka-connect.php' );
		}
		
		$connect = new WC_Edostavka_Connect( $package, $args );
		
		$rates = $connect->get_rates();

		if ( sizeof( $rates ) == 0 ) {
			wc_edostavka_add_log( 'Нет ни одного доступного тарифа.');
			return false;
		}
		
		foreach( $rates as $tariff_id => $data ) {
			
			$price	= $data['price'];
			$cost  	= $this->fix_format( esc_attr( $price ) );
			$fee   	= $this->get_fee( $this->fix_format( $this->fee ), $cost );
			$date	= $data['deliveryPeriodMax'];
			
			array_push(
				$this->available_rates,
				array(
					'id'    	=> is_callable( array( $this, 'get_rate_id' ) ) ? $this->get_rate_id( $tariff_id ) : $this->instance_id . ':' . $tariff_id,
					'label' 	=> $this->get_rate_label( $tariff_id, $date ),
					'cost'  	=> $cost + $fee,
					'package'   => $package,
				)
			);
		}

		return true;
	}

	public function calculate_shipping( $package = array() ) {
		if ( $this->available_rates ) {
			foreach ( $this->available_rates as $rate ) {
				$this->add_rate( $rate );
			}
		} elseif( is_checkout() && 'yes' == $this->show_error && in_array( WC_Edostavka_Shipping::get_method_id(), wc_get_chosen_shipping_method_ids() ) ) {
			wc_add_notice( $this->error_text, 'error');
		}
	}
	
	private function get_rate_label( $tariff_id, $date ) {
		
		$name = wc_edostavka_get_delivery_tariff_name( $tariff_id, $date );
		
		if( 'yes' == $this->display_date ) {
			
			$additional_time = intval( $this->additional_time );
			if ( $additional_time > 0 ) {
				$date += intval( $additional_time );
			}
			if ( $date > 0 ) {
				$name .= sprintf(' (<span title="Срок доставки">%s</span>)',  human_time_diff( strtotime("+$date day") ) );
			}
		} 
		
		return $name;
	}
}