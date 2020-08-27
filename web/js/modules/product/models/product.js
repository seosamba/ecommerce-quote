define([
    'underscore',
    'backbone'
], function(_, Backbone){

    var productModel = Backbone.Model.extend({
        urlRoot  : function(){ return $('#website_url').val() + 'api/store/products/id/'; }
    });

    return productModel;
});