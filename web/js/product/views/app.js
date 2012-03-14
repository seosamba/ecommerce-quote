define([
	'Underscore',
	'Backbone',
    'product/collections/products',
    'product/views/product'
], function(_, Backbone, ProductsCollection, ProductView){

	var quoteListView = Backbone.View.extend({
		el: $('#add-products-quote'),
		events: {
			'click .btn-add' : 'addProductToQuote'
		},
		initialize: function() {
			this.productsCollection = new ProductsCollection();
			this.productsCollection.bind('add', this.render, this);
			this.productsCollection.bind('remove', this.render, this);
            this.productsCollection.bind('reset', this.render, this)
		},
		render: function(){
            $('#products').empty();
			this.productsCollection.each(function(product){
				var view = new ProductView({model: product});
				$(view.render().el).appendTo('#products');
            });
        },
		addProductToQuote: function(e) {
			var productId = $(e.target).data('pid');
			var product   = this.productsCollection.get(productId);
			$.ajax({
				url        : $('#websiteUrl').val() + 'plugin/quote/run/additem/',
				type       : 'post',
				dataType   : 'json',
				data : {
					item : product.toJSON(),
					opts : $('div[data-productid=' + productId + '] *').serialize(),
					qty  : $(e.target).prev('.qty').val()
				},
				beforeSend : function() {showSpinner();},
				success : function(response) {
					hideSpinner();
					showMessage(response.responseText);
				}
			})
		}
	});

	return quoteListView;

});