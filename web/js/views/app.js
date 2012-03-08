define([
	'Underscore',
	'Backbone',
    'collections/quote',
    'views/quote'
], function(_, Backbone, QuoteCollection, QuoteView){

	var quoteListView = Backbone.View.extend({
		el: $('#manage-quotes'),
		events: {
			'click #add-new': 'addNewQuote',
			'change .quote-status' : 'updateQuoteStatus',
			'click .remove': 'removeQuote'
		},
		initialize: function() {
			this.quoteCollection = new QuoteCollection();
			this.quoteCollection.bind('add', this.render, this);
			this.quoteCollection.bind('remove', this.render, this);
            this.quoteCollection.bind('reset', this.render, this)
		},
		render: function(){
            $('#quotes').empty();
			this.quoteCollection.each(function(quote){
				var view = new QuoteView({model: quote});
				$(view.render().el).appendTo('#quotes');
            });
        },
		addNewQuote: function() {
			console.log('add new quote');
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
			showConfirm('You are about to remove a quote! Are you sure?', function() {
				var quote = this.quoteCollection.get($(e.target).parent().data('sid'));
				quote.destroy({
					success: function(model, response){
						showMessage(response.responseText);
					},
					error: function(model, response){
						showMessage(response.responseText, true);
					}
				});
			});
		}
	});

	return quoteListView;
});