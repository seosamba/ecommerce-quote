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
            firstPage: 0,
            currentPage: 0,
            perPage: 100,
            totalPages: 10
        },
        server_api: {
            os: 1,
            count: true,
            limit: function () {
                return this.perPage;
            },
            offset: function () {
                return this.currentPage * this.perPage;
            }
        },
        parse: function (response) {
            if (this.server_api.count) {
                this.totalRecords = response.totalRecords;
            } else {
                this.totalRecords = response.length;
            }
            this.totalPages = Math.floor(this.totalRecords / this.perPage);
            return this.server_api.count ? response.data : response;
        },
        batch: function (method, data, options) {
            var checked = this.where({checked: true});
            var url = $('#website_url').val() + 'api/quote/products/';
            var ids = _.pluck(checked, 'id').join(',');

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
        }
    });

    return productsCollection;
});
