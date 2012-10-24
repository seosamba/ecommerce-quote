define([
    'underscore',
    'backbone'
], function(_, Backbone) {

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
            showConfirm('You are about to remove a quote! Are you sure?', function() {
                var quote = appView.quotes.get($(e.currentTarget).data('sid'));
                showSpinner();
                quote.destroy({
                    wait: true,
                    success: function(model, response) {
                        hideSpinner();
                        showMessage('Quote [' + model.get('title') + '] has been removed.');
                    },
                    error: function(model, response) {
                        hideSpinner();
                        showMessage('Can not remove quote [' + model.get('title') + ']. Try again later.', true);
                    }
                });
            });
        },
        statusAction: function(e) {
            var quote = appView.quotes.get(e.currentTarget.id);
            quote.set({status: e.currentTarget.value});
            showSpinner();
            quote.save(null, {
                success: function(model, response) {
                    hideSpinner();
                    showMessage('Quote [' + model.get('title') + '] status changed to ' + model.get('status'));
                },
                error: function(model, response) {
                    hideSpinner();
                    showMessage('Can not update quote [' + model.get('title') + '] status. Try again later.', true);
                }
            })
        },
        toggleAction: function(e) {
            this.model.set('checked', e.currentTarget.checked);
        },
        render: function() {
            this.$el.html(this.template(this.model.toJSON()));
            return this;
        }
    });

    return quoteRowView;

});