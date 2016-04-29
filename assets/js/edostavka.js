jQuery(function($){

    $(document).ready(function(){

        if( typeof( wc_checkout_params ) !== "undefined" && wc_checkout_params.is_checkout == 1 ) {

            var delivery_point_select2 = function() {
                $( 'select#billing_delivery_point:visible' ).select2( {
                    minimumResultsForSearch: 10,
                    placeholder: 'Выберите ПВЗ',
                    placeholderOption: 'first',
                    width: '100%',
                } );
            };

            var delivery_points_map = function() {

                ymaps.ready(function () {

                    var contactmap;
                    var map_container = 'edostavka_map';
                    var points = $( '#' + map_container ).data('points');

                    ymaps.geocode( $( '#' + map_container ).data('state-name') , { results: 1 }).then( function( response ){

                        var getCoordinats = response.geoObjects.get(0).geometry.getCoordinates();
                        contactmap = new ymaps.Map( map_container, {
                            center: getCoordinats,
                            zoom: 14,
                            behaviors: ['default', 'scrollZoom'],
                            controls: []
                        });

                        $.map( points, function( point ) {
                            addPointToMap( point );
                        });

                        function addPointToMap( point ) {

                            placemark = new ymaps.Placemark( [ point.coordY, point.coordX ], {
                                balloonContentBody: [
                                    '<address>',
                                    '<strong>' + point.Name + '</strong>',
                                    '<br/>',
                                    'Адрес: г.' + point.City + ' ул.' + point.Address,
                                    '<br/>',
                                    'Телефон: ' + point.Phone,
                                    '<br/>',
                                    'Время работы: ' + point.WorkTime,
                                    '<br/>',
                                    'Дополнительно: ' + point.Note,
                                    '</address>'
                                ].join('')
                            } );

                            placemark.events.add('click', function( event ) {
                                $("select#billing_delivery_point")
                                    .val( point.Code )
                                    .select2( 'val', point.Code );
                            } );

                            contactmap.geoObjects.add( placemark );
                        };

                        if( contactmap.geoObjects.getLength() > 1 ) {
                            contactmap.setBounds( contactmap.geoObjects.getBounds() );
                        } else {
                            contactmap.setCenter( contactmap.geoObjects.get(0).geometry.getCoordinates() );
                        }

                    });

                });
            };



            var load_autocomplate_states = function() {

                try {
					var self = $('#billing_state');
				
					if( self.length > 0 && $().select2 ) {
						
						var edostavka_request = {
							updateTimer: false,
							xhr: false,
							default_value: self.val() !== '' ? self.val() : woocommerce_params.default_state_name,
							init:function(){
								$( document.body ).bind( 'init_edostavka', this.init_edostavka );
								
								if ( wc_checkout_params.is_checkout === '1' ) {
									$( document.body ).trigger( 'init_edostavka' );
								}
							},
							init_edostavka:function(){
								
								self.addClass('state_select').select2({
									placeholder: self.attr( 'placeholder' ) !== '' ? self.attr( 'placeholder' ) : 'Выберите город',
									placeholderOption: 'first',
									width: '100%',
									ajax: {
										url: woocommerce_params.geo_json_url,
										method: 'POST',
										dataType: "jsonp",
										delay: 250,
										data: function (params) {
											this.xhr = true;
										  return {
											q: params,
											name_startsWith: params,
											countryCodeList: function () { return [$('#billing_country').val()] }
										  };
										  
										},
										processResults: function ( data ) {
											return {
												results: $.map( data.geonames || {}, function ( item ) {
													if( ! item || item.countryIso == null || item.countryIso !== $('#billing_country').val() ) return;
													return {
														id: item.name,
														city_id: item.id,
														city_name: item.cityName,
														text: item.name
													}
												})
											};
										},
										cache: false
									},
									createSearchChoice: function( term, results ) {
										if ( results.length===0 ) {
											return {
												id:term,
												text:term,
												city_id:0,
												city_name:term
											};
										}
									},
									formatSelection: function( data ) { 
										return data.text; 
									},
									initSelection: function (element, callback) {
										element.on('select2-selecting', function( event ) {
											$('#billing_state_id').val( event.object.city_id );
											$('#billing_city').val( event.object.city_name );
											$( document.body ).trigger( 'update_checkout' );					
										});
										callback({
											'id': element.val() !== '' ? element.val() : this.default_value,
											'text': element.val() !== '' ? element.val() : this.default_value
										});
									},
									minimumInputLength: 1,
									formatMatches: function( matches ) {
										if ( 1 === matches ) {
											return wc_country_select_params.i18n_matches_1;
										}

										return wc_country_select_params.i18n_matches_n.replace( '%qty%', matches );
									},
									formatNoMatches: function() {
										return wc_country_select_params.i18n_no_matches;
									},
									formatAjaxError: function( jqXHR, textStatus, errorThrown ) {
										return wc_country_select_params.i18n_ajax_error;
									},
									formatInputTooShort: function( input, min ) {
										var number = min - input.length;

										if ( 1 === number ) {
											return wc_country_select_params.i18n_input_too_short_1
										}

										return wc_country_select_params.i18n_input_too_short_n.replace( '%qty%', number );
									},
									formatInputTooLong: function( input, max ) {
										var number = input.length - max;

										if ( 1 === number ) {
											return wc_country_select_params.i18n_input_too_long_1
										}

										return wc_country_select_params.i18n_input_too_long_n.replace( '%qty%', number );
									},
									formatSelectionTooBig: function( limit ) {
										if ( 1 === limit ) {
											return wc_country_select_params.i18n_selection_too_long_1;
										}

										return wc_country_select_params.i18n_selection_too_long_n.replace( '%qty%', number );
									},
									formatLoadMore: function( pageNumber ) {
										return wc_country_select_params.i18n_load_more;
									},
									formatSearching: function() {
										return wc_country_select_params.i18n_searching;
									}
								});
							}
							
						}
						
						edostavka_request.init();

					}
				} catch( error ) {
					console.log( error );
				}
            };

            //load_autocomplate_states();

            $( 'body' ).on('updated_checkout', function(){
                
				if ( $('#billing_delivery_point option').length > 0 && $().select2 ) {

                    $( '#billing_delivery_point_field' ).find( '.select2-container' ).remove();
                    $('div#edostavka_map').empty();
                    delivery_point_select2();
                    delivery_points_map();

                }
				
				var method = woocommerce_params.chosen_shipping_method;

                $( 'select.shipping_method, input[name^=shipping_method][type=radio]:checked, input[name^=shipping_method][type=hidden]' ).each( function( index, input ) {
                    method = $( this ).val();
                } );

                if( method && method.indexOf('edostavka_') >= 0 ) {
                    //Если СДЕК
                    var tatiff_id = method.replace('edostavka_','');

                    if( $.inArray( parseInt( tatiff_id ), woocommerce_params.is_door ) >= 0 ) {
                        //Если СДЕК до двери
                        $( '#billing_delivery_point_field, #edostavka_map' ).hide().addClass('hidden');
                        $( '#billing_address_1_field, #billing_address_2_field').show().removeClass('hidden');
                    } else {
                        //Если СДЕК до склада
                        $( '#billing_delivery_point_field, #edostavka_map' ).show().removeClass('hidden');
                        $( '#billing_address_1_field, #billing_address_2_field' ).hide().addClass('hidden');
                    }

                } else {
                    // Для всех остальных методов
                    $( '#billing_delivery_point_field, #edostavka_map' ).hide().addClass('hidden'); //Прячем ПВЗ
                    $( '#billing_address_1_field, #billing_address_2_field' ).show().removeClass('hidden'); //Показываем адрес
                }

                load_autocomplate_states();
            } );
        }
    });
});