define([
	'underscore',
	'backbone'
], function(_, Backbone){

    var quoteModel = Backbone.Model.extend({
        urlRoot : function() {
            var url = $('#website_url').val() + 'api/quote/quotes/';
            if(this.has('type')) {
                url += 'type/' + this.get('type') + '/';
                if (this.has('templateName')) {
                    url += 'templateName/' + this.get('templateName') + '/';
                }
            } else {
                url += 'id/'
            }
            return url;
        }
    });

	return quoteModel;
});