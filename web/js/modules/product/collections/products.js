define([
    'underscore',
    'backbone',
    '../models/product',
    'backbone.paginator'
], function (_, Backbone, ProductModel) {

    var productsCollection = Backbone.Paginator.requestPager.extend({
        model: ProductModel,
        paginator_core: {
            dataType: 'json',
            url: $('#website_url').val() + 'api/store/products/'
        },
        paginator_ui: {
            firstPage: 1,
            currentPage: 1,
            perPage: 24,
            last: false,
            totalPages: 10
        },
        server_api: {
            os: 1,
            count: false,
            onlyEnabled: true,
            limit: function() { return this.perPage; },
            offset: function(){ return (this.currentPage - this.firstPage) * this.perPage; },
            key: function(){ return this.key; }
        },
        parse: function (response) {
            this.totalCount = _.has(response, 'totalCount') ? response.totalCount : response.length;
            this.totalPages = Math.ceil(this.totalCount / this.perPage);
            return _.has(response, 'data') ? response.data : response;

        },
        batch: function (method, data, options) {
            var checkedProducts = $('#checkedProducts').val();
            //var checked = this.where({checked: true});
            var url = $('#website_url').val() + 'api/quote/products/';
            var ids = '';
            if(checkedProducts !== '' || typeof checkedProducts !== 'undefined') {
                ids = checkedProducts;
            }

            //var ids = _.pluck(checked, 'id').join(',');
            if(ids !== '') {
                url += 'id/' + ids + '/';

                $.ajax({
                    type: method,
                    url: url,
                    dataType: 'json',
                    beforeSend: showSpinner(),
                    data: JSON.stringify(data)
                }).done(function () {
                    appView.products.pager();
                    if (typeof options != 'undefined' && (options instanceof Object)) {
                        if (options.hasOwnProperty('success')) {
                            options.success();
                        }
                    }
                })
            } else {
                showMessage('Please select 1 or more products!', false);
            }
        }
    });

    return productsCollection;
});
