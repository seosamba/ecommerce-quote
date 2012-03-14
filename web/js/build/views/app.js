define([
	'Underscore',
	'Backbone',
	'build/views/qsearch',
	'build/collections/products',
	'build/views/product',
	'quote/models/quote'
], function(_, Backbone, QsearchView, ProductsCollection, ProductView, QuoteModel){

	var buildView = Backbone.View.extend({
		el: $('body'),
		events: {
			'click #save-quote' : 'buildQuote'
		},
		initialize: function() {
			$('.quote-controll').button();

			var qsearch  = new QsearchView();
			$(qsearch.render().el).appendTo('#quote-quicksearch');

			this.productsCollection = new ProductsCollection();
			this.productsCollection.bind('add', this.render, this);
			this.productsCollection.bind('remove', this.render, this);
            this.productsCollection.bind('reset', this.render, this);
		},
		render: function(){
			$('#quote-cart table tbody').empty();
			this.productsCollection.each(function(product){
				var view = new ProductView({model: product});
				$(view.render().el).appendTo('#quote-cart table tbody');
            });
        },
		buildQuote: function() {
			var quote = new QuoteModel();
			quote.set({
				'name' : $('#quote-name').val(),
				'status' : 'new',
				'disclaimer': 'test',
				'internalMessage' : 'im',
				'shippingMethod' : $('#shipping-type').val()
			});
			quote.save(null, {
				success: function() {
					console.log('success');
				},
				fail: function() {
					console.log('fail');
				}
			});
		}
	});

	return buildView;
});