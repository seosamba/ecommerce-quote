define([
    'underscore',
    'backbone',
    '../models/product',
    'backbone.paginator'
], function(_, Backbone, ProductModel){

    var productsCollection = Backbone.Paginator.requestPager.extend({
        model: ProductModel,
        paginator_core: {
            dataType: 'json',
            url:  $('#website_url').val() + 'api/store/products/'
        },
        paginator_ui: {
            firstPage:    0,
            currentPage:  0,
            perPage:     10,
            totalPages:  10
        },
        server_api: {
            count: true,
            limit: function() { return this.perPage; },
            offset: function() { return this.currentPage * this.perPage }
        },
        parse: function(response){
            if (this.server_api.count){
                this.totalRecords = response.totalRecords;
            } else {
                this.totalRecords = response.length;
            }
            this.totalPages = Math.floor(this.totalRecords / this.perPage);
            return this.server_api.count ? response.data : response;
        }
        //url: $('#website_url').val() + 'api/store/products/'
    });

	return productsCollection;
});