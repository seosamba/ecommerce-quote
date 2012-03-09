define([
	'Underscore',
	'Backbone'
], function(_, Backbone) {

	var productView = Backbone.View.extend({
		className : 'product-item',
		template  : $('#productTemplate').template(),
		render: function() {
			$(this.el).html($.tmpl(this.template, this.model.toJSON()));
			this.$('img.lazy').lazyload({
                container: $('#products'),
                effect: 'fadeIn'
            });
			return this;
		}
	});

	return productView;

});