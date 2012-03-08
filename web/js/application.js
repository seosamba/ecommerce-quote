define([
	'Underscore',
	'Backbone',
	'views/app'
], function(_, Backbone, AppView){
	var initialize = function(){
		console.log('init app');
		var view = new AppView();
		$.when(view.quoteCollection.fetch()).done(function() {
			view.render();
		});
	};

	return {
		initialize: initialize
	};
});