define([
	'underscore',
	'backbone',
    '../models/quote',
    'backbone.paginator'
], function(_, Backbone, QuoteModel){

    var quotesCollection = Backbone.Paginator.requestPager.extend({
        model : QuoteModel,
        order : 'created_at',
        orderType: 'desc',
        paginator_core: {
            dataType : 'json',
            url      : $('#website_url').val() + 'api/quote/quotes/'
        },
        paginator_ui: {
            firstPage: 0,
            currentPage: 0,
            perPage: 10,
            totalPages: 10
        },
        server_api: {
            count: true,
            order: function() { return this.order },
            orderType: function() {return this.orderType},
            limit: function() { return this.perPage; },
            offset: function() { return this.currentPage * this.perPage }
        },
        parse: function (response) {
            this.totalRecords = (this.server_api.count) ? response.total : response.length;
            this.totalPages   = Math.floor(this.totalRecords / this.perPage);
            return (this.server_api.count) ? response.data : response;
        },
        batch: function(method, data) {
            var quotes = this.where({checked: true});
            var url    = this.paginator_core.url
            var ids    = _.pluck(quotes, 'id').join(',');

            url += 'id/' + ids + '/';

            $.ajax({
                type: method,
                url: url,
                dataType: 'json',
                data: JSON.stringify(data)
            }).done(function() {
                appView.quotes.pager();
            })
        },
        init: function(data) {
            var self = this;

            _.defaults(self.paginator_ui, {
                firstPage   : 0,
                currentPage : 1,
                perPage     : 10,
                totalPages  : 10
            });

            // Change scope of 'paginator_ui' object values
            _.each(self.paginator_ui, function(value, key) {
                if( _.isUndefined(self[key]) ) {
                    self[key] = self.paginator_ui[key];
                }
            });
            self.reset(self.parse(data));
        }
    });

	return quotesCollection;
});