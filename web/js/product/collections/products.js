define([
	'Underscore',
	'Backbone',
    'product/models/product'
], function(_, Backbone, ProductModel){

    var productsCollection = Backbone.Collection.extend({
        model: ProductModel,
        url: $('#websiteUrl').val()+'plugin/shopping/run/getdata/type/product/'
    });

	return productsCollection;
});