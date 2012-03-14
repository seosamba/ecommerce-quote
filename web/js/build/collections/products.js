define([
	'Underscore',
	'Backbone',
    'product/models/product'
], function(_, Backbone, ProductModel){

    var productsCollection = Backbone.Collection.extend({
        model: ProductModel,
        url: $('#websiteUrl').val()+'plugin/cart/run/cart/'
    });

	return productsCollection;
});