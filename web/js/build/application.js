define([
	'Underscore',
	'Backbone',
	'build/views/app'
], function(_, Backbone, AppView){
	var initialize = function(){
		console.log('build module');
		var view = new AppView();
		$.when(view.productsCollection.fetch()).done(function() {
			view.render();
		});
	};

	return {
		initialize: initialize
	};
});