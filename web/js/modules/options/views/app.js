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

            //@todo modify this, to parse url options diferent way
            this.currentSelection = splitedUrl[splitedUrl.length - 5];

		},
		render: function() {
            $('#manage-product-options-main').empty();
            var view = new ProductView({model: this.product});
			$(view.render().el).appendTo('#manage-product-options-main');
            $('#manage-product-options-main').data({currentSelection: this.currentSelection})
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
                    qid  : $(e.currentTarget).data('qid'),
                    type : 'options',
                    value: options
                }),
                beforeSend: showSpinner()
            }).done(function(response) {
                hideSpinner();
                showMessage('Changes saved. Refreshing a quote page...');
                window.parent.location.reload();
            });
		}
	});

	return productOptionsView;
});