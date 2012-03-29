define([
	'Underscore',
	'Backbone',
    'quote/collections/quotes',
    'quote/views/quote',
	'quote/models/quote'
], function(_, Backbone, QuoteCollection, QuoteView, QuoteModel){

	var quoteListView = Backbone.View.extend({
		el: $('#manage-quotes'),
		events: {
			'click #add-new': 'addNewQuote',
			'change .quote-status' : 'updateQuoteStatus',
			'click .remove': 'removeQuote',
			'click th.sortable' : 'sort',
			'click #quotes-next' : 'showNextPage',
			'click #quotes-previous' : 'showPrevPage',
			'change #quotes-check-all': 'toggleCheckAllQuotes',
			'change #mass-action' : 'doMassAction',
			'keyup #quotes-search': 'searchQuotes'
		},
		initialize: function() {
			this.quoteCollection = new QuoteCollection();
			this.quoteCollection.on('add', this.render, this);
			this.quoteCollection.on('remove', this.render, this);
			this.quoteCollection.on('reset', this.render, this);
		},
		render: function(){
            $('table#quotes tbody').empty();
			this.quoteCollection.each(function(quote){
				var view = new QuoteView({model: quote});
				$(view.render().el).appendTo('table#quotes tbody');
            });
        },
		addNewQuote: function() {
			//adding quote from admin interface
			var options = {
				'type' : 'build'
			};
			$.ajax({
				url: $('#websiteUrl').val() + 'plugin/quote/run/quotes/',
				type       : 'post',
				dataType   : 'json',
				data : options,
				beforeSend : function() {showSpinner();},
				success : function(response) {
					window.parent.location.href = $('#websiteUrl').val() + response.responseText.redirectTo;
				}
			});
		},
		updateQuoteStatus: function(e) {
			var quote = this.quoteCollection.get(e.target.id);
			quote.set({'status': e.target.value});
			quote.save(null, {
				success: function(model, response){
					showMessage(response.responseText);
				},
				error: function(model, response) {
					showMessage(response.responseText, true);
				}
			});
		},
		removeQuote: function(e) {
			appView = this;
			showConfirm('You are about to remove a quote! Are you sure?', function() {
				var quote = appView.quoteCollection.get($(e.target).parent().data('sid'));
				quote.destroy({
					success: function(model, response){
						showMessage(response.responseText);
					},
					error: function(model, response){
						showMessage(response.responseText, true);
					}
				});
			});
		},
		sort: function(e) {
			var $el                       = $(e.target);
			var sortKey                   = $(e.target).data('sortkey');
			this.quoteCollection.order.by = sortKey;
			if (!$el.hasClass('sortUp') && !$el.hasClass('sortDown')){
                $el.addClass('sortUp');
                this.quoteCollection.order.asc = true;
            } else  {
                $el.toggleClass('sortUp').toggleClass('sortDown');
                this.quoteCollection.order.asc = !this.quoteCollection.order.asc;
            }
			this.quoteCollection.fetch();
		},
		showNextPage: function() {
			this.quoteCollection.next();
			return false;
		},
		showPrevPage: function() {
			this.quoteCollection.previous();
			return false;
		},
		toggleCheckAllQuotes: function(e) {
			var value = e.target.checked;
            this.quoteCollection.each(function(quote){
	            quote.set({checked: value});
            });
		},
		doMassAction: function(e) {
			var action  = $(e.target).val();
			var pattern = /^mark[a-z]*/i;
			if(pattern.test(action)) {
				this.massChangeStatus(action.replace('mark', '').toLowerCase());
			}
			else {
				action = this[action];
				if(_.isFunction(action)) {
					action.call(this);
				}
				$(e.target).val(0);
			}
		},
		massChangeStatus: function(status) {
			var quotes = this.quoteCollection.checked();
			if(_.isEmpty(quotes)) {
				return false;
			}
			_.each(quotes, function(quote) {
				quote.set({status: status});
				quote.save();
			});
		},
		deleteSelected: function() {
			console.log('mass del');
			var quotes = this.quoteCollection.checked();
			if(_.isEmpty(quotes)) {
				return false;
			}
			showConfirm('You are about to remove quotes! Are you sure?', function(){
                //var ids = _(quotes).pluck('id');
				_.each(quotes, function(quote) {
					quote.destroy();
				});
            });
		},
		searchQuotes: function(e) {
			var searchTerm = $(e.target).val();
			var self       = this;
			clearTimeout(self.searching);
            self.searching = setTimeout(function(){
                self.quoteCollection.search(searchTerm);
            }, 600);
		}
	});

	return quoteListView;
});