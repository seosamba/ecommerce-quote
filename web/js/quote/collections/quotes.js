define([
	'Underscore',
	'Backbone',
    'quote/models/quote'
], function(_, Backbone, QuoteModel){

    var quotesCollection = Backbone.Collection.extend({
        model: QuoteModel,
        url: $('#websiteUrl').val()+'plugin/quote/run/quotes/'
    });

	return quotesCollection;
});