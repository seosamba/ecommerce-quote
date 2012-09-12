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
	    searchTerm: '',
	    initialize: function(){
            this.bind('reset', this.updatePaginator, this);
        },
        url: function() {
	        var url = $('#website_url').val()+'plugin/quote/run/quotes/';
	        url    += '?limit=' + this.paginator.limit + '&offset=' + this.paginator.offset;
	        if (this.order.by) {
                url += '&order=' + this.order.by + ' ' + (this.order.asc ? 'asc' : 'desc');
            }
	        if(this.searchTerm) {
		        url += '&search=' + this.searchTerm;
	        }
	        return url;
        },
	    next: function(callback) {
		    if (!this.paginator.last) {
                this.paginator.offset += this.paginator.limit;
                return this.fetch().done(callback);
            }
            console.log('Last reached');
	    },
	    previous: function(callback) {
		    if (this.paginator.offset >= this.paginator.limit){
                this.paginator.offset -= this.paginator.limit;
                return this.fetch().done(callback);
            }
            console.log('First reached');
	    },
	    updatePaginator: function() {
            if (this.length === 0){
                this.previous();
            } else {
                this.paginator.last = (this.length < this.paginator.limit);
            }
        },
	    checked: function(){
            return this.filter(function(quote){ return quote.has('checked') && quote.get('checked'); });
        },
	    search: function(term){
            if (term !== this.searchTerm){
                this.searchTerm = encodeURIComponent(term);
                this.paginator.offset = 0;
                this.paginator.last = false;
                this.paginator.order = {by: null,asc: true};
                return this.fetch();
            }
        }
    });

	return quotesCollection;
});