define([
	'Underscore',
	'Backbone',
	'product/views/app'
], function(_, Backbone, AppView){
	var initialize = function(){
		var view = new AppView();
		$.when(view.productsCollection.fetch()).done(function() {
			view.render();
		});
	};

	return {
		initialize: initialize
	};
});