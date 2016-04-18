jQuery(function( $ ){
	
	if( ! $().select2() || typeof( wc_params ) == "undefined" ) return;
	
	$('#woocommerce_edostavka_city_origin_name').select2({
		placeholder: 'Выберите город',
		placeholderOption: 'first',
		width: '100%',
		ajax: {
			url: wc_params.api_url,
			method: 'POST',
			dataType: "jsonp",
			delay: 250,
			data: function (params) {
				return {
					q: params,
					name_startsWith: params,
					countryCodeList: function () { return [wc_params.default_country] }
				};
								  
			},
			processResults: function ( data ) {
				return {
					results: $.map( data.geonames, function ( item ) {
						if( ! item || item.countryIso == null || item.countryIso !== wc_params.default_country ) return;
						return {
							id: item.name,
							city_id: item.id,
							text: item.cityName
						}
					})
				};
			},
			cache: true
		},
		minimumInputLength: 1,
		initSelection: function (element, callback) {
			callback({
				'id': element.val(),
				'text': element.val()
			});
		},
		formatSelection: function( data ) { 
			return data.text; 
		},
	}).on('select2-selecting', function( event ) {
		$('#woocommerce_edostavka_city_origin').val( event.object.city_id );					
	});;
	
});