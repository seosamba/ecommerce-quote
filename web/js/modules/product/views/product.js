define([
    'underscore',
    'backbone'
], function(_, Backbone) {

	var productView = Backbone.View.extend({
		className : 'product-item grid_2',
		template  : _.template($('#productTemplate').text()),
		render: function() {
            $(this.el).html(this.template(this.model.toJSON()));
			return this;
		}
	});

	return productView;

});