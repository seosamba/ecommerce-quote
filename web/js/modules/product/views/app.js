define([
    'underscore',
    'backbone',
    '../collections/products',
    '../views/product'
], function (_, Backbone, ProductsCollection, ProductView) {

    var quoteListView = Backbone.View.extend({
        el: $('#products-container'),
        events: {
            'keypress #search': 'searchAction',
            'click .ui-menu-item': 'searchAction',
            'click .add-products': 'addAction'
        },
        initialize: function () {
            this.products = new ProductsCollection();
            this.products.server_api.order = 'p.price DESC';
            this.products.on('reset', this.render, this);
            this.products.fetch();

            //init autocomplete
            $.getJSON($('#website_url').val() + 'plugin/shopping/run/searchindex', function (response) {
                $('#search')
                    .data({source: response})
                    .autocomplete({
                        minLength: 2,
                        source: function (req, resp) {
                            var data = $(this.element).data('source'),
                                list;
                            list = _.filter(data, function (str) {
                                var t,
                                    term = req.term.split(/\s+/);
                                for (t in term) {
                                    if (term.hasOwnProperty(t)) {
                                        if (str.toLowerCase().search(term[t].toLowerCase()) === -1) {
                                            return false;
                                        }
                                    }
                                }
                                return true;
                            });
                            resp(list);
                        },
                        select: function (event, ui) {
                            $('#search').val(ui.item.value).trigger('keypress', true);
                        },
                        messages: {
                            noResults: '',
                            results: function() {}
                        }
                    });
            });
        },
        addAction: function (e) {
            var splitedUrl = window.location.href.split('/');
            var quoteId = splitedUrl[splitedUrl.length - 1];
            this.products.batch('post', {qid: quoteId}, {success: function (response) {
                hideSpinner();
                showMessage('Products added to the quote. Refreshing the quote page...');
                window.parent.location.reload();
            }});
        },
        render: function () {
            this.$('#products').empty();
            this.products.each(function (product) {
                var view = new ProductView({model: product});
                $(view.render().el).appendTo('#products');
            });
            if (this.products.length === 1) {
                this.renderRelatedFor(this.products.first());
            }

            this.$('img.lazy').lazyload({
                container: this.$('#products'),
                effect: 'fadeIn'
            });
        },
        renderRelatedFor: function (product) {
            if (!_.isEmpty(product.get('related'))) {
                var relateds = new ProductsCollection(),
                    placeholder = $('<img class="mt50px" src="' + $('#website_url').val() + 'system/images/spinner2.gif">');

                this.$('#products').append(placeholder);
                relateds.server_api.count = false;
                relateds.on('reset', function (collection) {
                    placeholder.remove();
                    collection.each(function (product) {
                        var view = new ProductView({model: product});
                        $(view.render().el).appendTo('#products');
                    });
                }, this);
                relateds.fetch({reset: true, data: {id: product.get('related').join(',')}});

            }

        },
        searchAction: function (e, force) {
            if (e.keyCode == 13 || force) {
                this.products.server_api.key = function () {
                    return e.currentTarget.value;
                }
                this.products.pager();
                $(e.target).autocomplete('close');
            }
        }
    });

    return quoteListView;

});
