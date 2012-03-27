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
			var product = new ProductModel();
			product.on('change:name', this.loadSelections, product);
			this.productView = new ProductView({model:  product});
		},
		render: function(){
            $('#manage-product-options-main').empty();
			$(this.productView.render().el).appendTo('#manage-product-options-main');
			return this;
        },
		saveOptions: function(e) {
			var options   = $('.product-options-listing *').serialize();
			var productId = $(e.target).parent().data('pid');
			var splitedParentUrl = window.parent.location.href.split('/');
			showSpinner();
			$.post($('#websiteUrl').val() + 'plugin/quote/run/options/', {
				qid: splitedParentUrl[splitedParentUrl.length-1],
				options: options,
				pid: productId
			}, function(response) {
				if(!response.error) {
					top.location.reload();
				} else {
					showMessage(response.responseText, response.error);
				}
			}, 'json');
		},
		loadSelections: function() {
			var splitedParentUrl = window.parent.location.href.split('/');
			$.getJSON($('#websiteUrl').val() + 'plugin/quote/run/loadselections/', {
				qid: splitedParentUrl[splitedParentUrl.length-1],
				pid: this.get('id')
			}, function(response) {

			})
		}
	});

	return productOptionsView;
});