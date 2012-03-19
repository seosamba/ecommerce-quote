define([
	'Underscore',
	'Backbone',
	'options/views/product',
	'product/models/product'
], function(_, Backbone, ProductView, ProductModel){

	var productOptionsView = Backbone.View.extend({
		el: $('#manage-product-options'),
		events: {
			'click #save-options' : 'saveOptions'
		},
		initialize: function() {
			$('#save-options').button();
			this.productView = new ProductView({model:  new ProductModel()});
		},
		render: function(){
            $('#manage-product-options-main').empty();
			$(this.productView.render().el).appendTo('#manage-product-options-main');
			return this;
        },
		saveOptions: function(e) {
			var options   = $('.product-options-listing *').serialize();
			var productId = $(e.target).data('pid');
			var splitedParentUrl = window.parent.location.href.split('/');
			$.post($('#websiteUrl').val() + 'plugin/quote/run/options/', {
				qid: splitedParentUrl[splitedParentUrl.length-1],
				options: options,
				pid: productId
			}, function(response) {
				showMessage(response.responseText, response.error);
			}, 'json');
		}
	});

	return productOptionsView;
});