define([
    'libs/underscore/underscore',
    'libs/backbone/backbone'
], function(_, Backbone) {

	var productView = Backbone.View.extend({
		className : 'product-item',
		template  : _.template($('#productTemplate').text()),
		render: function() {
			$(this.el).html(this.template(this.model.toJSON()));
			this.$('img.lazy').lazyload({
                container: $('#products'),
                effect: 'fadeIn'
            });
			return this;
		}
	});

	return productView;

});