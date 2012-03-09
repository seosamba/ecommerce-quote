define([
	'Underscore',
	'Backbone'
], function(_, Backbone){

    var productModel = Backbone.Model.extend({
        urlRoot  : $('#websiteUrl').val()+'plugin/shopping/run/getdata/type/product/'
    });

	return productModel;
});