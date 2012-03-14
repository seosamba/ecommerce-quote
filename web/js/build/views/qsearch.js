define([
	'Underscore',
	'Backbone'
], function(_, Backbone){

	var qsearchView = Backbone.View.extend({
		id: 'quicksearch',
		template: $('#qsearchTemplate').template(),
		events: {
			'change #qsearch-text' : 'search'
		},
		initialize: function() {

		},
		render: function(){
			$(this.el).html($.tmpl(this.template));
			return this;
        },
		search: function() {
			console.log('search');
		}
	});

	return qsearchView;
});