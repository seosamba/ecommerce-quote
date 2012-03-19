define([
	'Underscore',
	'Backbone',
    'product/collections/products',
    'product/views/product'
], function(_, Backbone, ProductsCollection, ProductView){

	var quoteListView = Backbone.View.extend({
		el: $('#add-products-quote'),
		events: {
			'click .btn-add' : 'addProductToQuote',
			'keypress #product-list-search': 'filterProducts'
		},
		initialize: function() {
			this.productsCollection = new ProductsCollection();
			this.productsCollection.on('add', this.render, this);
			this.productsCollection.on('remove', this.render, this);
		},
		render: function(){
            $('#products').empty();
			this.productsCollection.each(function(product){
				var view = new ProductView({model: product});
				$(view.render().el).appendTo('#products');
            });
        },
		addProductToQuote: function(e) {
			var productId        = $(e.target).data('pid');
			var splitedParentUrl = window.parent.location.href.split('/');
			$.ajax({
				url        : $('#websiteUrl').val() + 'plugin/quote/run/products/',
				type       : 'post',
				dataType   : 'json',
				data : {
					pid  : productId,
					opts : $('div[data-productid=' + productId + '] *').serialize(),
					qty  : $(e.target).prev('.qty').val(),
					qid  : splitedParentUrl[splitedParentUrl.length-1]
				},
				beforeSend : function() {showSpinner();},
				success : function(response) {
					hideSpinner();
					showMessage(response.responseText);
				}
			})
		} ,
		filterProducts: function() {

		}
	});

	return quoteListView;

});