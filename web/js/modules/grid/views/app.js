/**
 * Quote grid application view
 *
 */
define([
    'underscore',
    'backbone',
    '../collections/quotes',
    './quoteGridRow',
    'i18n!../../../nls/'+$('input[name=system-language]').val()+'_ln'
], function(_, Backbone, QuotesCollection, QuoteGridRowView, i18n) {

    var QuoteGridView = Backbone.View.extend({
        el: $('#quote-grid'),
        events: {
            'click a.quote-grid-add'        : 'addAction',
            'click a.page'                  : 'navigateAction',
            'keypress #quote-grid-search'   : 'searchAction',
            'change #quote-grid-select-all' : 'checkAllAction',
            'change #batch-action'          : 'batchAction',
            'click .sortable'               : 'sortGridAction'
        },
        templates: {
            pager: _.template($('#quote-grid-pager').text())
        },
        initialize: function() {
            this.quotes = new QuotesCollection();
            this.quotes.on('reset', this.render, this);
            this.quotes.on('add', this.render, this);
            this.quotes.on('remove', this.render, this);

            this.quotes.server_api = _.extend(this.quotes.server_api, {
                search: function() {return $('#quote-grid-search').val()}
            });
        },
        sortGridAction: function(e) {
            var self = this;
            self.quotes.order = $(e.currentTarget).data('sort');
            self.quotes.pager().done(function() {
                if(self.quotes.orderType == 'desc') {
                    self.quotes.orderType = 'asc';
                } else {
                    self.quotes.orderType = 'desc'
                }
            });
        },
        batchAction: function(e) {
            var action = e.currentTarget.value;
            if(action == 'remove') {
                var self = this;
                var selected = self.quotes.where({checked: true});
                if(_.isEmpty(selected)) {
                    showMessage(_.isUndefined(i18n['You should pick at least one item!']) ? 'You should pick at least one item!':i18n['You should pick at least one item!'], true);
                    $('#batch-action').val($('option:first', $('#batch-action')).val());
                    return false;
                }
                showConfirm(_.isUndefined(i18n['Your are about to remove a bunch of quotes! Are you sure?']) ? 'Your are about to remove a bunch of quotes! Are you sure?':i18n['Your are about to remove a bunch of quotes! Are you sure?'], function() {
                    self.quotes.batch('delete');
                });
            }
            this.$(e.currentTarget).val('');
        },
        checkAllAction: function(e) {
            this.quotes.each(function(quote) {
                quote.set('checked', e.currentTarget.checked);
                this.$('#quote-grid-select-all').attr('checked', e.currentTarget.checked);
            })
        },
        addAction: function(e) {
            showSpinner();
            var self = this;
            this.quotes.create({type: 'build'}, {
                wait: true,
                success: function(model) {
                    hideSpinner();
                    self.quotes.pager();
                    showMessage((_.isUndefined(i18n['New quote']) ? 'New quote':i18n['New quote']) +' '+ '[' + model.get('title') + ']' +' '+ (_.isUndefined(i18n['has been generated.']) ? 'has been generated.':i18n['has been generated.']));
                },
                error: function(mode, xhr) {
                    hideSpinner();
                    showMessage(xhr.responseText, true);
                }
            });
        },
        searchAction: function() {
            this.quotes.pager();
        },
        navigateAction: function(e) {
            e.preventDefault();
            var page = $(e.currentTarget).data('page');
            if ($.isNumeric(page)) {
                this.quotes.goTo(page);
            } else {
                switch(page){
                    case 'first':
                        this.quotes.goTo(this.quotes.firstPage);
                    break;
                    case 'last':
                        this.quotes.goTo(this.quotes.totalPages);
                    break;
                    case 'prev':
                        this.quotes.requestPreviousPage();
                    break;
                    case 'next':
                        this.quotes.requestNextPage();
                    break;
                }
            }
        },
        renderGrid: function() {
            this.$('#quote-grid-quotes tbody').empty();
            this.quotes.each(function(quote) {
                var view = new QuoteGridRowView({model: quote});
                if(quote.has('type') && quote.get('type') == 'build') {
                    this.$('#quote-grid-quotes tbody').prepend($(view.render().el).addClass('quote-grid-new-quote'));
                } else {
                    this.$('#quote-grid-quotes tbody').append(view.render().el);
                }
            }, this);
            this.$('td.pager').html(this.templates.pager(this.quotes.info()));
        },
        render: function() {
            this.renderGrid();
            return this;
        }
    })
    return QuoteGridView;
});