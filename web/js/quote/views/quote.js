define([
	'Underscore',
	'Backbone'
], function(_, Backbone) {

	var quoteView = Backbone.View.extend({
		tagName: 'tr',
		className : 'quote-item grid_12',
		template  : $('#quoteTemplate').template(),
		render: function() {
			$(this.el).html($.tmpl(this.template, this.model.toJSON()));
            return this;
		}
	});

	return quoteView;

});