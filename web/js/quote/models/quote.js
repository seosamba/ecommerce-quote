define([
	'Underscore',
	'Backbone'
], function(_, Backbone){

    var quoteModel = Backbone.Model.extend({
        urlRoot  : $('#websiteUrl').val()+'/plugin/quote/run/quote/qid'
    });

	return quoteModel;
});