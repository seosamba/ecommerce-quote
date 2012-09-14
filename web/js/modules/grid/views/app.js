define([
    'underscore',
    'backbone',
    '../collections/quotes',
    './quoteGridRow',
    'text!../templates/pager.html'
], function(_, Backbone, QuotesCollection, QuoteGridRowView, PagerTmpl) {

    var QuoteGridView = Backbone.View.extend({
        el: $('#quote-grid'),
        events: {
            'click #quote-grid-add': 'addAction',
            'click a.page': 'navigateAction',
            'keypress #quote-grid-search': 'searchAction',
            'change #quote-grid-select-all': function(e) {
                this.quotes.each(function(quote) {
                    quote.set('checked', e.currentTarget.checked);
                    this.$('#quote-grid-select-all').attr('checked', e.currentTarget.checked);
                })
            },
            'change #batch-action': function(e) {
                var action = e.currentTarget.value;
                if(action == 'remove') {
                    var self = this;
                    showConfirm('Your are about to remove a bunch of quotes! Are you sure?', function() {
                        self.quotes.batch('delete');
                    });
                }
                this.$(e.currentTarget).val('');
            }
        },
        templates: {
            pager:_.template(PagerTmpl)
        },
        initialize: function() {
            this.quotes = new QuotesCollection();
            this.quotes.on('reset', this.render, this);
            this.quotes.on('add', this.render, this);
            this.quotes.on('remove', this.render, this);

            this.quotes.server_api = _.extend(this.quotes.server_api, {
                search: function() {return $('#quote-grid-search').val()}
            });

            this.quotes.pager();
        },
        addAction: function(e) {
            this.quotes.create({type: 'build'}, {
                wait: true,
                success: function(model) {
                    showMessage('New quote [' + model.get('title') + '] has been generated.');
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