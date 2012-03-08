define([
	'Underscore',
	'Backbone',
    'models/quote'
], function(_, Backbone, QuoteModel){

    var quoteCollection = Backbone.Collection.extend({
        model: QuoteModel,
        url: $('#websiteUrl').val()+'plugin/quote/run/quote/qid'
    });

	return quoteCollection;
});