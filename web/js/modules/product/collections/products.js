define([
    'libs/underscore/underscore',
    'libs/backbone/backbone',
    'modules/product/models/product'
], function(_, Backbone, ProductModel){

    var productsCollection = Backbone.Collection.extend({
        model: ProductModel,
        url: $('#website_url').val() + 'api/store/products/'
    });

	return productsCollection;
});