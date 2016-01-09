jQuery(function($){

    $(document).ready(function(){

        if( typeof( wc_checkout_params ) !== "undefined" && wc_checkout_params.is_checkout == 1 ) {

            $( 'body' ).on('updated_checkout', function () {

                var method = woocommerce_params.chosen_shipping_method;

                if ( $('select#shipping_method').size() > 0 ) {
                    method = $('select#shipping_method').val();
                } else if ( typeof ( $('input.shipping_method:checked').val() ) != 'undefined' ) {
                    method = $('input.shipping_method:checked').val();
                }


                if( method.indexOf('edostavka_') >= 0 ) {
                    //Если СДЕК
                    var tatiff_id = method.replace('edostavka_','');

                    if( $.inArray( parseInt( tatiff_id ), woocommerce_params.is_door ) >= 0 ) {
                        //Если СДЕК до двери
                        $( '#billing_delivery_point_field' ).hide().addClass('hidden');
                        //Для корректной работы с Select2
                        $( '#billing_delivery_point').hide().addClass('hidden');
                        $( '#s2id_billing_delivery_point').hide().addClass('hidden');
                        $( '#billing_address_1_field, #billing_address_2_field').show().removeClass('hidden');
                    } else {
                        //Если СДЕК до склада
                        $( '#billing_delivery_point_field' ).show().removeClass('hidden');
                        //Для корректной работы с Select2
                        if (document.getElementById("s2id_billing_delivery_point") !== null) {
                            $('#s2id_billing_delivery_point').show().removeClass('hidden');
                            $( '#billing_delivery_point').hide().addClass('hidden');
                        } else {
                            $('#billing_delivery_point').show().removeClass('hidden');
                        }

                        $( '#billing_address_1_field, #billing_address_2_field' ).hide().addClass('hidden');
                    }

                } else {
                    // Для всех остальных методов
                    $( '#billing_delivery_point_field' ).hide().addClass('hidden'); //Прячем ПВЗ
                    $( '#billing_address_1_field, #billing_address_2_field' ).show().removeClass('hidden'); //Показываем адрес
                }

            } );

            $( 'body' ).on( 'change updated_checkout', 'select.state_select', function( event ) {
                $( 'body' ).trigger('update_checkout');
            });
        }
    });

});
