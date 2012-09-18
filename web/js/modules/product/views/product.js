define([
    'underscore',
    'backbone',
    'text!../templates/productItem.html'
], function(_, Backbone, ProductItemTmpl) {

	var productView = Backbone.View.extend({
		className : 'product-item',
		template  : _.template(ProductItemTmpl),
		events: {
            //'click .product-item' : 'toggleAction',
            'click .in-quote': 'toggleAction'
        },
//        initialize: function() {
//            this.model.on('change', this.toggleAction, this);
//        },
        toggleAction: function(e) {
            this.model.set({checked: e.currentTarget.checked});
            $(this.el).toggleClass('quote-checked');
        },
        addAction: function(e) {
            console.log('pfff'); return;
            var productId        = $(e.currentTarget).data('pid');
            var splitedParentUrl = window.parent.location.href.split('/');
            $.ajax({
                url        : $('#website_url').val() + 'api/quote/products/',
                type       : 'post',
                dataType   : 'json',
                data : {
                    pid  : productId,
                    opts : $('div[data-productid=' + productId + '] *').serialize(),
                    qty  : $(e.target).prev('.qty').val(),
                    qid  : splitedParentUrl[splitedParentUrl.length-1]
                },
                beforeSend : function() {showSpinner();},
                success : function(response) {
                    hideSpinner();
                    showMessage(response.responseText);
                }
            })
        },
        render: function() {
            $(this.el).html(this.template(this.model.toJSON()));
			return this;
		}
	});

	return productView;

});