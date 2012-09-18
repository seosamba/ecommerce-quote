define([
	'underscore',
	'backbone',
    'text!../templates/item.html'
], function(_, Backbone, ItemTmpl) {

	var productView = Backbone.View.extend({
		className : 'product-item',
		template  : _.template(ItemTmpl),
		render: function() {
			$(this.el).html(this.template(this.model.toJSON()));
			return this;
		}
	});

	return productView;

});