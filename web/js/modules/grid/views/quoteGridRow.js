define([
    'underscore',
    'backbone',
    'i18n!../../../nls/'+$('input[name=system-language]').val()+'_ln'
], function(_, Backbone, i18n) {

    var quoteRowView = Backbone.View.extend({
        tagName   : 'tr',
        className : 'quote-grid-row',
        template  : _.template($('#quote-grid-row').text()),
        events    : {
            'click .quote-grid-delete': 'deleteAction',
            'change .quote-status': 'statusAction',
            'change .quote-grid-row-checkbox': 'toggleAction'
        },
        initialize: function() {
            this.model.on('change', this.render, this);
        }                                              ,
        deleteAction: function(e) {
            showConfirm(_.isUndefined(i18n['You are about to remove a quote! Are you sure?']) ? 'You are about to remove a quote! Are you sure?':i18n['You are about to remove a quote! Are you sure?'], function() {
                var quote = appView.quotes.get($(e.currentTarget).data('sid'));
                showSpinner();
                quote.destroy({
                    wait: true,
                    success: function(model, response) {
                        hideSpinner();
                        showMessage((_.isUndefined(i18n['Quote']) ? 'Quote':i18n['Quote']) + ' ' + '[' + model.get('title') + ']' + ' ' + (_.isUndefined(i18n['has been removed.']) ? 'has been removed.':i18n['has been removed.']));
                    },
                    error: function(model, response) {
                        hideSpinner();
                        showMessage((_.isUndefined(i18n['Can not remove quote.']) ? 'Can not remove quote.':i18n['Can not remove quote.'])  + ' ' + '[' + model.get('title') + ']'  + ' ' + (_.isUndefined(i18n['Try again later.']) ? 'Try again later.':i18n['Try again later.']), true);
                    }
                });
            });
        },
        statusAction: function(e) {
            var quote = appView.quotes.get(e.currentTarget.id),
                status = e.currentTarget.value,
                oldStatus = quote.get('status');
            quote.set({status: status, skipStatusVerification:'1'});
            showSpinner();

            if (status === 'partial') {
                $.ajax({
                    'url': $('#website_url').val() + 'api/quote/partialpayment/',
                    'type': 'POST',
                    'dataType': 'json',
                    'data': {
                        status: status,
                        fromGrid: '1',
                        quoteId:quote.get('id')
                    }
                }).done(function (responseData) {
                    hideSpinner();
                    showMessage((_.isUndefined(i18n['Quote']) ? 'Quote' : i18n['Quote']) + ' ' + '[' + quote.get('title') + ']' + ' ' + (_.isUndefined(i18n['status changed to']) ? 'status changed to' : i18n['status changed to']) + ' ' + quote.get('status'));
                }).fail(function(responseData){
                    hideSpinner();
                    showMessage(responseData.responseJSON, true, 3000);
                    quote.set('status', oldStatus);
                });
            } else {
                quote.save(null, {
                    success: function (model, response) {
                        hideSpinner();
                        showMessage((_.isUndefined(i18n['Quote']) ? 'Quote' : i18n['Quote']) + ' ' + '[' + model.get('title') + ']' + ' ' + (_.isUndefined(i18n['status changed to']) ? 'status changed to' : i18n['status changed to']) + ' ' + model.get('status'));
                    },
                    error: function (model, response) {
                        hideSpinner();
                        showMessage((_.isUndefined(i18n['Can not update quote']) ? 'Can not update quote' : i18n['Can not update quote']) + ' ' + '[' + model.get('title') + ']' + ' ' + (_.isUndefined(i18n['status. Try again later.']) ? 'status. Try again later.' : i18n['status. Try again later.']), true);
                    }
                });
            }
        },
        toggleAction: function(e) {
            var quote = appView.quotes.get(e.currentTarget.id);
            if(typeof quote !== 'undefined'){
                this.model.set('checked', e.currentTarget.checked);
                if (typeof _checkboxRadio === 'function') {
                    _checkboxRadio();
                }
            }

        },
        render: function() {
            this.$el.html(this.template(this.model.toJSON()));
            return this;
        }
    });

    return quoteRowView;

});