define([
    'underscore',
    'backbone',
    '../collections/products',
    '../views/product'
], function(_, Backbone, ProductsCollection, ProductView){

	var quoteListView = Backbone.View.extend({
		el: $('#products-container'),
		events: {
			'keypress #search': 'searchAction',
            'click .add-products': 'addAction'
		},
		initialize: function() {
            this.products = new ProductsCollection();
            this.products.on('reset', this.render, this);
            this.products.fetch();

            //init autocomplete
            $.getJSON($('#website_url').val() + 'plugin/shopping/run/searchindex', function(response){
                $('#search').autocomplete({
                    minLength: 2,
                    source: response,
                    select: function(event, ui){
                        $('#search').val(ui.item.value).trigger('keypress', true);
                    }
                });
            });
		},
        addAction: function(e) {
            var splitedUrl = window.location.href.split('/');
            var quoteId    = splitedUrl[splitedUrl.length - 1];
            this.products.batch('post', {qid: quoteId}, {success: function(response) {
                hideSpinner();
                showMessage('Products added to the quote. Refreshing the quote page...');
                window.parent.location.reload();
            }});
        },
		render: function(){
            this.$('#products').empty();
			this.products.each(function(product){
				var view = new ProductView({model: product});
				$(view.render().el).appendTo('#products');
            });

            this.$('img.lazy').lazyload({
                container: this.$('#products'),
                effect: 'fadeIn'
            });
        },
        searchAction: function(e, force) {
            if(e.keyCode == 13 || force) {
                this.products.server_api.key = function() {return e.currentTarget.value; }
                this.products.pager();
                $(e.target).autocomplete('close');
            }
		}
	});

	return quoteListView;

});