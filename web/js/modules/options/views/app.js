define([
	'underscore',
	'backbone',
	'./product',
	'../../product/models/product'
], function(_, Backbone, ProductView, ProductModel){

	var productOptionsView = Backbone.View.extend({
		el: $('#manage-product-options'),
		events: {
			'click #save-options' : 'saveAction'
		},
		initialize: function() {
			$('#save-options').button();

            var splitedUrl = window.location.href.split('/');
            var self = this;
            this.product = new ProductModel({id: splitedUrl[splitedUrl.length - 1]});
            this.product.on('change', this.render, this);
            this.product.fetch();

			//product.on('change:name', this.loadSelections, product);
			//this.productView = new ProductView({model:  product});
		},
		render: function(){
            $('#manage-product-options-main').empty();
            var view = new ProductView({model: this.product});
			$(view.render().el).appendTo('#manage-product-options-main');
			return this;
        },
		saveAction: function(e) {
			var options   = $('.product-options-listing *').serialize();
    		var productId = $(e.target).parent().data('pid');
            $.ajax({
                url: $('#website_url').val() + 'api/quote/products/id/' + this.product.get('id') + '/type/options/',
                type: 'put',
                dataType: 'json',
                data: JSON.stringify({
                    qid: $(e.currentTarget).data('qid'),
                    options: options
                }),
                beforeSend: showSpinner
            }).done(function(response) {
                console.log(response);
            });
		}
	});

	return productOptionsView;
});