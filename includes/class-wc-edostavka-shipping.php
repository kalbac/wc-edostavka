<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Edostavka_Shipping_Method extends WC_Shipping_Method {

	public function __construct( $instance_id = 0  ) {
		$this->id                 = WC_Edostavka::get_method_id();
		$this->instance_id 		  = absint( $instance_id );
		$this->method_title       = __( 'Edostavka' );
		$this->method_description = __( 'Расчёт стоимости доставки СДЭК' );

		$this->supports              = array(
			'shipping-zones',
			'instance-settings'
		);

		$this->init();

	}

	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->enabled            = $this->get_option( 'enabled' );
		$this->title              = $this->get_option( 'title' );
		$this->display_date       = $this->get_option( 'display_date' );
		$this->additional_time    = $this->get_option( 'additional_time' );
		$this->fee                = $this->get_option( 'fee' );
		$this->city_origin_name   = $this->get_option( 'city_origin_name' );
		$this->city_origin        = $this->get_option( 'city_origin' );
		$this->notice_text   	  = $this->get_option( 'notice_text' );
		$this->error_text   	  = $this->get_option( 'error_text' );
		$this->show_error   	  = $this->get_option( 'show_error' );
		$this->show_notice   	  = $this->get_option( 'show_notice' );
		$this->enabled_in_cart    = $this->get_option( 'enabled_in_cart' );
		$this->corporate_service  = $this->get_option( 'corporate_service' );
		$this->disable_required_address = $this->get_option('disable_required_address');
		$this->hide_standart_wc_city = $this->get_option('hide_standart_wc_city');
		$this->autoselect_edostavka_shipping_method = $this->get_option('autoselect_edostavka_shipping_method');
		$this->replace_shipping_label_door = $this->get_option( 'replace_shipping_label_door' );
		$this->shipping_label_door = $this->get_option( 'shipping_label_door' );
		$this->replace_shipping_label_stock = $this->get_option( 'replace_shipping_label_stock' );
		$this->shipping_label_stock = $this->get_option( 'shipping_label_stock' );
		$this->contract_number	  = $this->get_option( 'contract_number' );
		$this->login              = $this->get_option( 'login' );
		$this->password           = $this->get_option( 'password' );
		$this->minimum_weight	  = $this->get_option( 'minimum_weight' );
		$this->minimum_height     = $this->get_option( 'minimum_height' );
		$this->minimum_width      = $this->get_option( 'minimum_width' );
		$this->minimum_length     = $this->get_option( 'minimum_length' );
		$this->debug              = $this->get_option( 'debug' );
		$this->countries          = apply_filters('woocommerce_edostavka_countries', $this->get_option( 'countries' ) );
		$this->availability		  = $this->get_option( 'availability' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( 'yes' == $this->debug ) {
			$this->log = WC_Edostavka::logger();
		}
	}

	protected function woocommerce_method() {
		if ( function_exists( 'WC' ) ) {
			return WC();
		}
	}

	public function init_form_fields() {

		$this->instance_form_fields = array(
			'enabled' => array(
				'title' 		=> __( 'Вкл/Выкл' ),
				'type' 			=> 'checkbox',
				'label' 		=> __( 'Включить метод доставки' ),
				'default' 		=> 'yes'
			),
			'title' => array(
				'title' 		=> __( 'Название метода доставки' ),
				'type' 			=> 'text',
				'description' 	=> __( 'Это название будет показыватся пользователям при оформлении заказа' ),
				'default'		=> __( 'еДоставка' ),
				'desc_tip'      => true,
			),
			'availability' => array(
				'title' 		=> __( 'Доступность' ),
				'type' 			=> 'select',
				'description' 	=> '',
				'default' 		=> 'including',
				'options' 		=> array(
					'including' 	=> __( 'Выбранные страны' ),
					'excluding' 	=> __( 'Исключая выбранные страны' ),
				)
			),
			'countries' => array(
				'title' 		=> __( 'Страны' ),
				'type' 			=> 'multiselect',
				'class'			=> 'chosen_select',
				'css'			=> 'width: 450px;',
				'default' 		=> 'RU',
				'options'		=> WC()->countries->get_allowed_countries()
			),
			'city_origin_name' => array(
				'title' 		=> __( 'Город отправитель' ),
				'description'	=> __( 'Укажите город откуда будут отправлять посылки.' ),
				'desc_tip'      => true
			),
			'city_origin' => array(
				'title' 		=> __( 'ID Города отправитель' ),
				'type' 			=> 'hidden'
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
			'enabled_in_cart' => array(
				'title'            => __( 'Подсчёт в корзине' ),
				'type'             => 'checkbox',
				'label'            => $this->enabled_in_cart == 'yes' ? __('Выключить') : __( 'Включить' ),
				'description'      => __( 'Включить данный метод расчёта доставки в корзине?' ),
				'desc_tip'         => true,
				'default'          => 'no'
			),
			'show_notice' => array(
				'title'            => __( 'Текст в корзине' ),
				'type'             => 'checkbox',
				'label'            => $this->show_notice == 'yes' ? __('Выключить') : __( 'Включить' ),
				'description'      => __( 'Показывать информационный текст на странице корзины?' ),
				'desc_tip'         => true,
				'default'          => 'no'
			),
			'notice_text' => array(
				'title' 		=> __( 'Информационный текст' ),
				'type' 			=> 'textarea',
				'description'	=> __( 'Напишите информационный текст который будет отображатся на странице корзины.' ),
				'default'		=> sprintf(__('Расчёт стоимости доставки через курьерскую службу %s, может быть не точный. Что бы узнать точную стоимость доставки, на <a href="%s">странице оформления заказа</a> необходимо указать точный адрес доставки.'), $this->title, wc_get_page_permalink('checkout') ),
				'desc_tip'      => true
			),
			'show_error' => array(
				'title'            => __( 'Уведомление в оформление заказа' ),
				'type'             => 'checkbox',
				'label'            => $this->show_error == 'yes' ? __('Выключить') : __( 'Включить' ),
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
			'disable_required_address' => array(
				'title' 		=> __( 'Адрес не обязательный' ),
				'description'       => __('Сделать поле "Адрес" не обязательным для заполнения если выбран другой метод доставки (не СДЕК) или выбран метод доставки до ПВЗ.'),
				'type'        => 'checkbox',
				'label'       => $this->disable_required_address == 'yes' ? __('Да') : __('Нет'),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'hide_standart_wc_city' => array(
				'title'       => __('Скрыть стандартное поле ввода "Населенный пункт"'),
				'type'        => 'checkbox',
				'label'       => $this->hide_standart_wc_city == 'yes' ? __('Нет') : __('Да'),
				'default'     => 'no'
			),
			'services' => array(
				'title'            => __( 'Настройка для СДЕК' ),
				'type'             => 'title'
			),
			'contract_number'	=> array(
				'title' 		=> __( 'Номер договора СДЕК' ),
				'type' 			=> 'text',
				'description'	=> __( 'Введите номер договора заключённый с компанией СДЕК.' ),
				'desc_tip'      => true
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
			'corporate_service' => array(
				'title' 		=> __( 'Разрешенные тарифы' ),
				'description'	=> __( 'Укажите какие тарифы могут использоваться при расчёте стоимости доставки.' ),
				'desc_tip'      => true,
				'type' 			=> 'multiselect',
				'class'			=> 'chosen_select',
				'options'		=> WC_Edostavka::wc_edostavka_delivery_tariffs()
			),
			'autoselect_edostavka_shipping_method' => array(
				'title'            => __( 'Автоматически выбирать доставку СДЭК, если она доступна' ),
				'type'             => 'checkbox',
				'label'            => $this->autoselect_edostavka_shipping_method == 'yes' ? __('Нет') : __( 'Да' ),
				'description'      => __( 'Если заказ может быть доставлен хотя бы по одному тарифу СДЭК, автоматически выбрать этот способ доставки.' ),
				'desc_tip'         => true,
				'default'          => 'no'
			),
			'replace_shipping_label_door' => array(
				'title'			=> __( 'Заменять названия тарифов СДЭК до двери' ),
				'type'			 => 'checkbox',
				'label'			=> $this->replace_shipping_label_door == 'yes' ? __('Нет') : __( 'Да' ),
				'description'	  => __( 'Показывать введенное в поле "Название тарифов СДЭК до двери" значение вместо названия тарифа определенное СДЭК?' ),
				'desc_tip'		 => true,
				'default'		  => 'no'
			),
			'shipping_label_door' => array(
				'title' 		=> __( 'Название тарифов СДЭК до двери' ),
				'type' 			=> 'textarea',
				'description'	=> __( 'Напишите текст, который будет отображатся вместо названия тарифов СДЭК до двери.' ),
				'default'		=> __( 'Доставка курьером до двери.' ),
				'desc_tip'	  => true
			),
			'replace_shipping_label_stock' => array(
				'title'			=> __( 'Заменять названия тарифов СДЭК до склада' ),
				'type'			 => 'checkbox',
				'label'			=> $this->replace_shipping_label_stock == 'yes' ? __('Нет') : __( 'Да' ),
				'description'	  => __( 'Показывать введенное в поле "Название тарифов СДЭК до ПВЗ" значение вместо названия тарифа определенное СДЭК?' ),
				'desc_tip'		 => true,
				'default'		  => 'no'
			),
			'shipping_label_stock' => array(
				'title' 		=> __( 'Название тарифов СДЭК до ПВЗ' ),
				'type' 			=> 'textarea',
				'description'	=> __( 'Напишите текст, который будет отображатся вместо названия тарифов СДЭК до склада.' ),
				'default'		=> __( 'Доставка до пункта выдачи заказов.' ),
				'desc_tip'	  => true
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
				'description'      => sprintf( __( 'Все логи будут записаны в %s.' ), '<code>' .WC_LOG_DIR . 'edostavka-' . sanitize_file_name( wp_hash( 'edostavka' ) ) . '.txt</code>' )
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

	private function fix_format( $value ) {
		$value = str_replace( '.', '', $value );
		$value = str_replace( ',', '.', $value );
		return $value;
	}

	protected function edostavka_services() {
		return $this->corporate_service;
	}

	function is_available( $package ) {
		if ( $this->enabled == "no" ) return false;

		if ( $this->get_option( 'availability' ) == 'including' ) :

			if ( is_array( $this->countries ) ) :
				if ( ! in_array( $package['destination']['country'], $this->countries ) ) return false;
			endif;

		else :

			if ( is_array( $this->countries ) ) :
				if ( in_array( $package['destination']['country'], $this->countries ) ) return false;
			endif;

		endif;

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true );
	}


	protected function edostavka_calculate( $package ) {

		$connect  = new WC_Edostavka_Connect();
		$connect->set_services( $this->edostavka_services() );
		$_package = $connect->set_package( $package );
		$_package->set_minimum_height( $this->minimum_height );
		$_package->set_minimum_width( $this->minimum_width );
		$_package->set_minimum_length( $this->minimum_length );
		$_package->set_minimum_weight( $this->minimum_weight );
		$connect->set_city_origin( $this->city_origin );
		$connect->set_debug( $this->debug );
		$connect->set_login( $this->login );
		$connect->set_password( $this->password );

		if( ! empty( $package['destination']['state_id'] ) && is_numeric( $package['destination']['state_id'] ) ) {
			$connect->set_city_destination( $package['destination']['state_id'] );
		} elseif ( 'yes' == $this->debug ) {
			$this->log->add( 'edostavka', 'Не удалось получить данные о городе получателя.' );
		}

		if( isset( $package['post_data']['billing_date'] ) && ! empty( $package['post_data']['billing_date'] ) ) {
			// Конвертируем дату доствки в нужный формат для СДЕК
			$date_string = $package['post_data']['billing_date'];
			$date_time = strtotime( $date_string );
			$date_conv = date( 'Y-m-d', $date_time );
			$connect->set_date( $date_conv );
		}

		$shipping = $connect->get_shipping();

		if ( ! empty( $shipping ) ) {
			return $shipping;
		} else {

			if ( 'yes' == $this->debug ) {
				$this->log->add( 'edostavka', 'Не удалось получить ни одного метода доставки.' );
			}
			return array();
		}
	}

	public function calculate_shipping( $package = array() ) {

		if( $this->enabled_in_cart !== 'yes' && is_cart() ) return;

		$rates           = array();
		$errors          = array();

		$shipping_values = $this->edostavka_calculate( $package );

		if ( ! empty( $shipping_values ) ) {
			$chosen_method = null;
			foreach ( $shipping_values as $code => $shipping ) {


				if ( ! isset( $shipping['result'] ) ) {
					continue;
				}

				if ($this->replace_shipping_label_door === 'yes'
					&& WC_Edostavka::wc_edostavka_delivery_tariffs_type($code) === 'door'
				) {
					$name = $this->shipping_label_door;
				} elseif ($this->replace_shipping_label_stock === 'yes'
					&& WC_Edostavka::wc_edostavka_delivery_tariffs_type($code) === 'stock'
				) {
					$name = $this->shipping_label_stock;
				} else {
					$name = WC_Edostavka_Connect::get_service_name($code);
				}
				$price		   = $shipping['result']['price'];

				$label = ( 'yes' == $this->display_date ) ? WC_Edostavka_Connect::estimating_delivery( apply_filters( 'woocommerce_edostavka_label_name', $name ), $shipping['result']['deliveryPeriodMax'], $this->additional_time ) : $name;

				$cost  = $this->fix_format( esc_attr( $price ) );
				$fee   = $this->get_fee( $this->fix_format( $this->fee ), $cost );
				array_push(
					$rates,
					array(
						'id'    	=> $this->id . '_' . $code,
						'label' 	=> $label,
						'cost'  	=> $cost + $fee,
						'package'   => $package,
					)
				);
				$chosen_method = $this->id . '_' . $code;
			}

			$rates = apply_filters( 'woocommerce_edostavka_shipping_methods', $rates, $package );
			if ($this->autoselect_edostavka_shipping_method === 'yes'
				&& $chosen_method !== null) {
				WC()->session->set( 'chosen_shipping_methods', array($chosen_method) );
			}

			if( sizeof( $rates ) > 0 ) {
				foreach ( $rates as $rate ) $this->add_rate( $rate );
				if( is_cart() && 'yes' == $this->show_notice )
					wc_add_notice( $this->notice_text, 'notice');
			} else {
				if( is_checkout() && strpos( WC_Edostavka::get_chosen_shipping_method(), $this->id ) === 0 && 'yes' == $this->show_error )
					wc_add_notice( $this->error_text, 'error');
			}
		}
	}
}
