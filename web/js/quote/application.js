define([
	'Underscore',
	'Backbone',
	'quote/views/app'
], function(_, Backbone, AppView){
	var initialize = function(){
		var view = new AppView();
		$.when(view.quoteCollection.fetch()).done(function() {
			view.render();
		});
	};

	return {
		initialize: initialize
	};
});