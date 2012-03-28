define([
	'Underscore',
	'Backbone',
    'quote/models/quote'
], function(_, Backbone, QuoteModel){

    var quotesCollection = Backbone.Collection.extend({
        model: QuoteModel,
	    paginator: {
            limit: 30,
            offset: 0,
            last: false
        },
	    order: {
            by: null,
            asc: true
        },
        url: function() {
	        var url = $('#website_url').val()+'plugin/quote/run/quotes/';
	        url    += '?limit=' + this.paginator.limit + '&offset=' + this.paginator.offset;
	        if (this.order.by) {
                url += '&order=' + this.order.by + ' ' + (this.order.asc ? 'asc' : 'desc');
            }
	        return url;
        }
    });

	return quotesCollection;
});