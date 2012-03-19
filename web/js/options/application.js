define([
	'Underscore',
	'Backbone',
	'options/views/app'
], function(_, Backbone, AppView){
	var initialize = function(){
		var view = new AppView();
		view.productView.model.set({'id': $('#product-id').val()});
		$.when(view.productView.model.fetch()).done(function() {
			view.render();
		});
	};
	return {
		initialize: initialize
	};
});