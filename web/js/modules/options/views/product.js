define([
	'Underscore',
	'Backbone'
], function(_, Backbone) {

	var productView = Backbone.View.extend({
		className : 'product-item',
		template  : $('#singleProductTemplate').template(),
		render: function() {
			$(this.el).html($.tmpl(this.template, this.model.toJSON()));
			return this;
		}
	});

	return productView;

});