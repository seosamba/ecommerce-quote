define([
	'Underscore',
	'Backbone'
], function(_, Backbone) {

	var quoteView = Backbone.View.extend({
		tagName: 'tr',
		className : 'quote-item',
		template  : $('#quoteTemplate').template(),
		events: {
            'change input[name^=select]': 'toggle'
        },
		initialize: function() {
            this.model.on('change:checked', this.render, this);
			this.model.on('change:status', this.render, this);
        },
		render: function() {
			$(this.el).html($.tmpl(this.template, this.model.toJSON()));
            return this;
		},
		toggle: function(e) {
			this.model.set({checked: e.target.checked});
		}
	});

	return quoteView;

});