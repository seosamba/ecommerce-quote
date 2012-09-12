define([
    'libs/underscore/underscore',
    'libs/backbone/backbone',
], function(_, Backbone){

    var productModel = Backbone.Model.extend({
        urlRoot  : $('#website_url').val() + 'api/store/products/'
    });

	return productModel;
});