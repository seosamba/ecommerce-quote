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
            'click #search-quote-button'   : 'searchAction',
            'keyup #quote-grid-search'   : 'searchEnterAction',
            'change #quote-grid-select-all' : 'checkAllAction',
            'change #batch-action'          : 'batchAction',
            'click .sortable'               : 'sortGridAction',
            'click .quote-create-option-button': 'changeCreationType'
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
                search: function() {
                    var searchParam = $('#quote-grid-search').val();
                    searchParam = searchParam.replace("&", $('#quote-amp-hook').val());
                    return searchParam;
                },
                quoteOwnerId: function() {return $('#quote-owner-name').val()},
                quoteStatusName: function() {return $('#quote-status-name').val()}
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
        changeCreationType: function(e)
        {

            var el = $(e.currentTarget),
                currentStatus = el.data('checked'),
                switchType = el.data('type');

            $(el).closest('#quote-grid-top').find('.quote-create-option-button').prop('checked', false);
            $(el).closest('#quote-grid-top').find('.quote-create-option-button').removeClass('checked-btn');
            $(el).addClass('checked-btn');
            if (currentStatus !== true) {
                el.prop('checked', true);
                el.data('checked', true);
            }

            if (switchType === 'create_quote_duplicate') {
                $(el).closest('#quote-grid-top').find('#search-quote-duplicate').removeClass('hidden');
            } else {
                $(el).closest('#quote-grid-top').find('#search-quote-duplicate').addClass('hidden');
                $(el).closest('#quote-grid-top').find('#search-quote-duplicate').val('');
                $(el).closest('#quote-grid-top').find('#duplicate-quote-id').val('');
            }

            $(el).closest('#quote-grid-top').find('#quote-chosen-type').val(switchType);

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
            });
            if (typeof _checkboxRadio === 'function') {
                _checkboxRadio();
            }
        },
        addAction: function(e) {
            showSpinner();
            var self = this,
                duplicateQuoteId = $('#duplicate-quote-id').val(),
                quoteTitle = $('#quote-title-original').val(),
                quoteType = $('#quote-chosen-type').val();

                if (quoteType === 'create_quote_duplicate' && duplicateQuoteId == '') {
                    showMessage(_.isUndefined(i18n['Please search by quote title'])?'Please search by quote title':i18n['Please search by quote title'], true, 5000);
                    return false;
                }

            this.quotes.create({type: 'build', duplicateQuoteId: duplicateQuoteId, quoteTitle: quoteTitle}, {
                wait: true,
                success: function(model) {
                    hideSpinner();
                    self.quotes.pager();
                    showMessage((_.isUndefined(i18n['New quote']) ? 'New quote':i18n['New quote']) +' '+ '[' + model.get('title') + ']' +' '+ (_.isUndefined(i18n['has been generated.']) ? 'has been generated.':i18n['has been generated.']));
                    $('#duplicate-quote-id').val('');
                    $('.quote-create-option-button-default-load').trigger('click');
                    $('#search-quote-duplicate').val('');
                    $('#quote-title-original').val('');
                },
                error: function(mode, xhr) {
                    hideSpinner();
                    showMessage(xhr.responseText, true);
                }
            });
        },
        searchAction: function() {
            this.quotes.goTo(this.quotes.firstPage);

        },
        searchEnterAction: function(event)
        {
            if (event.keyCode === 13) {
                event.preventDefault();
                this.quotes.goTo(this.quotes.firstPage);
            }
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

            $("#search-quote-duplicate").on("keydown", function(event) {
                if ( event.keyCode === $.ui.keyCode.TAB &&
                    $(this).autocomplete( "instance" ).menu.active) {
                    event.preventDefault();
                }
            }).autocomplete({
                source: function(request, response) {
                    $.ajax({
                        'url': $('#website_url').val()+'plugin/quote/run/getQuoteNames/',
                        'type':'GET',
                        'dataType':'json',
                        'data': {searchTerm: request.term}
                    }).done(function(responseData){
                        $('#duplicate-quote-id').val('');
                        if (!_.isEmpty(responseData)) {
                            response($.map(responseData, function (responseData) {
                                return {
                                    label: responseData.title,
                                    value: responseData.title,
                                    custom: responseData.id,
                                };
                            }));
                        }
                    });
                },
                select: function(event, ui ) {
                    $('#duplicate-quote-id').val(ui.item.custom);
                }
            });

            return this;
        }
    })
    return QuoteGridView;
});
