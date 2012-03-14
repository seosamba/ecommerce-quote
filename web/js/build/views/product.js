define([
	'Underscore',
	'Backbone'
], function(_, Backbone) {

	var buildProductView = Backbone.View.extend({
		tagName: 'tr',
		className : 'product-item-row',
		template  : $('#productTemplate').template(),
		events: {
			'blur .product-cty' : 'updateQty'
		},
		render: function() {
			$(this.el).html($.tmpl(this.template, this.model.toJSON()));
			return this;
		},
		updateQty: function(e) {
			$.ajax({
				url      : $('#websiteUrl').val() + '/plugin/cart/run/cart',
				type     : 'put',
				dataType : 'json',
				data     : {
					sid: sid,
					qty: qty
				},
				beforeSend : function() {showSpinner();},
				success : function(response) {
	                    hideSpinner();
						refreshPrice(sid);
						refreshCartSummary();
	            },
	            error: function(xhr, errorStatus) {
	                showMessage(errorStatus, true);
	            }
			});
		}
	});

	return buildProductView;

});