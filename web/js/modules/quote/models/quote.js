define([
	'Underscore',
	'Backbone'
], function(_, Backbone){

    var quoteModel = Backbone.Model.extend({
        urlRoot  : $('#website_url').val()+'plugin/quote/run/quotes/qid/'
    });

	return quoteModel;
});