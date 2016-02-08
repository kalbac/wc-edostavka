/**
 * Created by admin on 08.02.2016.
 */
jQuery( function( $ ) {

    if (typeof wc_cart_params === 'undefined') {
        return false;
    }

    var calc_shipping_state = function() {
        $('#calc_shipping_state').each( function(){
            var _self = $( this ),
                _container = _self.parent(),
                _clone = _container.clone();

            _container.addClass( 'hidden' );

            var $new_field = $('<input />', {
                class: _self.attr('class'),
                type: 'text',
                name: _self.attr('class') + '_name',
                id: _self.attr('id') + '_name',
                value: woocommerce_params.default_state_name
            }).autocomplete({
                source: function(request,response) {
                    $.ajax({
                        url: woocommerce_params.geo_json_url,
                        method: 'POST',
                        dataType: woocommerce_params.is_ssl == 1 ? 'json' : "jsonp",
                        beforeSend: function( xhr ) {
                            $new_field.addClass( 'is_loading' );
                        },
                        data: {
                            q: function () { return $new_field.val(); },
                            name_startsWith: function () { return $new_field.val(); },
                            countryCodeList: function () { return [$('#calc_shipping_country').val()] }
                        },
                        success: function( data ) {
                            $new_field.toggleClass( 'is_loading', 'is_loaded' );
                            data = data.geonames ? data.geonames : data;
                            response( $.map ( data, function(item) {
                                if( item.countryCode && item.countryCode == $('#calc_shipping_country').val() ) {
                                    return {
                                        label: item.name,
                                        value: item.name,
                                        id: item.id
                                    }
                                }

                            }));
                        }
                    });

                },
                minLength: 0,
                select: function( event, ui ) {
                    _self.val( ui.item.id );

                    $.post( wc_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'update_shipping_method' ), {
                        security: wc_cart_params.update_shipping_method_nonce,
                        shipping_state: ui.item.id
                    }, function( response ) {
                        //$( 'div.cart_totals' ).replaceWith( response );
                        $( document.body ).trigger( 'updated_shipping_method' );
                    });
                }
            }).on('focus', function() {
                $( this ).autocomplete('search');
            });

            _container.after( _clone.empty().html( $new_field ) );
        });
    };

    calc_shipping_state();

    $( 'body' ).on( 'updated_shipping_method', function(){
        calc_shipping_state();
    } );

});