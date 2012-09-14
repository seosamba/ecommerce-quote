define([
    'underscore',
    'backbone',
    'modules/product/collections/products',
    'modules/product/views/product'
], function(_, Backbone, ProductsCollection, ProductView){

	var quoteListView = Backbone.View.extend({
		el: $('#products'),
		events: {
			'click .btn-add' : 'addProductToQuote',
			'keypress #product-list-search': 'filterProducts'
		},
		initialize: function() {

            this.productsCollection = new ProductsCollection();
            this.productsCollection.on('reset', this.render, this);
            this.productsCollection.on('add', this.render, this);
			this.productsCollection.on('remove', this.render, this);
            this.productsCollection.fetch();

		},
		render: function(){
            $('#products').empty();
			this.productsCollection.each(function(product){
				var view = new ProductView({model: product});
				$(view.render().el).appendTo('#products');
            });

            this.$('img.lazy').lazyload({
                container: $('#products'),
                effect: 'fadeIn'
            });
        },
		addProductToQuote: function(e) {
			var productId        = $(e.target).data('pid');
			var splitedParentUrl = window.parent.location.href.split('/');
			$.ajax({
				url        : $('#website_url').val() + 'api/quote/products/',
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
		},
        waypointCallback: function(){
            var self = this;
            $('.product-item:last', '#products').waypoint(function(){
                $(this).waypoint('remove');
                self.productsCollection.requestNextPage()
            }, {context: '#products', offset: '130%' } );
        },
		filterProducts: function() {

		}
	});

	return quoteListView;

});