define([
    'underscore',
    'backbone',
    '../collections/products',
    '../views/product',
    'text!../templates/paginator.html'
], function (_, Backbone, ProductsCollection, ProductView, PaginatorTmpl) {

    var quoteListView = Backbone.View.extend({
        el: $('#products-container'),
        events: {
            'keypress #search': 'searchAction',
            'click .ui-menu-item': 'searchAction',
            'click .add-products': 'addAction',
            'click .paginator a.page': 'paginatorAction'
        },
        templates: {
            paginator: _.template(PaginatorTmpl)
        },
        checkedProducts: [],
        initialize: function () {
            $('#products').html('<div class="spinner"></div>');
            this.products = new ProductsCollection();
            this.products.server_api.order = 'p.price DESC';
            this.products.server_api.count = true;
            this.products.on('reset', this.render, this);
            this.products.fetch();

            //init autocomplete
            $('#search').on("keydown", function(event) {
                if ( event.keyCode === $.ui.keyCode.TAB &&
                    $(this).autocomplete( "instance" ).menu.active) {
                    event.preventDefault();
                }
            }).autocomplete({
                source: function(request, response) {
                    $.ajax({
                        'url': $('#website_url').val() + 'plugin/shopping/run/searchindex',
                        'type':'GET',
                        'dataType':'json',
                        'data': {searchTerm: request.term}
                    }).done(function(responseData){
                        if (!_.isEmpty(responseData)) {
                            response($.map(responseData, function (responseData) {
                                return {
                                    label: responseData,
                                    value: responseData
                                };
                            }));
                        } else {
                            $('#search').prop('disabled', true).prop('disabled', false).focus();
                        }
                    });
                },
                search: function() {

                },
                focus: function() {
                    return true;
                },
                select: function(event, ui) {
                    $('#search').val(ui.item.value).trigger('keypress', true);
                },
                minLength: 1,
                messages: {
                    noResults: '',
                    results: function() {}
                }
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
            var self = this;

            this.$('#products').empty();
            this.$('.products-paginator').empty();
            this.products.each(function (product) {
                var checked = false;

                if(_.contains(self.checkedProducts, product.get('id')/*.toString()*/)) {
                    checked = true;
                    product.set({checked: true});
                }
                var view = new ProductView({model: product});

                if(checked) {
                    $(view.el).addClass('quote-checked');
                }
                $(view.render().el).appendTo('#products');
            });

            if (this.products.length === 1) {
                this.renderRelatedFor(this.products.first());
            } else if(this.products.length === 0) {
                $('#products').html('<p class="nothing">'+$('#products').data('emptymsg')+'</p>');
            }

            this.$('img.lazy').lazyload({
                container: this.$('#products'),
                effect: 'fadeIn'
            });
            var paginatorData = {
                collection : 'products'
            };
            paginatorData = _.extend(paginatorData, this.products.info());
            this.$('.products-paginator').html(this.templates.paginator(paginatorData));
        },
        paginatorAction:  function(e){
            var self = this;
            var products = this.products;
            products.each(function (product) {
                var productId = product.get('id');
               if(product.get('checked')) {
                   if (!_.isUndefined(self.checkedProducts)) {
                       self.checkedProducts = _.union(self.checkedProducts, productId);
                   } else {
                       self.checkedProducts = [productId];
                   }
               } else if(_.contains(self.checkedProducts,productId)) {
                   var arrayIndex = self.checkedProducts.indexOf(productId);
                   self.checkedProducts.splice(arrayIndex, 1);
               }
            });

            var page = $(e.currentTarget).data('page');
            var collection = $(e.currentTarget).parent('.paginator').data('collection');
            if (!collection) return false;
            if (_.has(this, collection)){
                collection = this[collection];
            }

            switch (page) {
                case 'first':
                    collection.goTo(collection.firstPage);
                    break;
                case 'prev':
                    if (collection instanceof Backbone.Paginator.requestPager){
                        collection.requestPreviousPage();
                    } else {
                        collection.previousPage();
                    }
                    break;
                case 'next':
                    if (collection instanceof Backbone.Paginator.requestPager){
                        collection.requestNextPage();
                    } else {
                        collection.nextPage();
                    }
                    break;
                case 'last':
                    collection.goTo(collection.totalPages);
                    break;
                default:
                    var pageId = parseInt(page);
                    !_.isNaN(pageId) && collection.goTo(pageId);
                    break;
            }
            return false;
        },
        renderRelatedFor: function (product) {
            var self = this;
            if (!_.isEmpty(product.get('related'))) {
                var relateds = new ProductsCollection(),
                    placeholder = $('<img class="mt50px" src="' + $('#website_url').val() + 'system/images/spinner2.gif">');

                this.$('#products').append(placeholder);
                relateds.server_api.count = false;
                relateds.on('reset', function (collection) {
                    placeholder.remove();
                    collection.each(function (product) {
                        if(_.contains(self.checkedProducts,product.get('id'))) {
                            product.set({checked: true});
                        }
                        var view = new ProductView({model: product});
                        $(view.render().el).appendTo('#products');
                    });
                }, this);
                relateds.fetch({reset: true, data: {id: product.get('related').join(',')}});

            }

        },
        searchAction: function (e, force) {
            if (e.keyCode == 13 || force) {
                this.products.server_api.count = true;
                this.products.server_api.key = function () {
                    return e.currentTarget.value;
                };
                $('#products').html('<div class="spinner"></div>');
                //this.products.pager();
                this.products.goTo(this.products.firstPage);
                $(e.target).autocomplete('close');
            }
        }
    });

    return quoteListView;

});
