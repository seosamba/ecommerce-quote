define([
    'underscore',
    'backbone',
    'text!../templates/productItem.html'
], function(_, Backbone, ProductItemTmpl) {

	var productView = Backbone.View.extend({
		className : 'productlisting pointer',
		template  : _.template(ProductItemTmpl),
		events: {
            'click': 'toggleAction'
        },
        initialize: function() {
            this.model.on('change', this.render, this);
        },
        toggleAction: function(e) {
            $(this.el).toggleClass('quote-checked');
            var isCheckedProduct = $(this.el).hasClass('quote-checked');
            var checkedProducts = $('#checkedProducts').val();
            var currentProductId = $(this.el).find('input').data('pid');
            var checkedProductsResult = [];

            if(checkedProducts !== '') {
                checkedProducts = checkedProducts.split(',');

                if(isCheckedProduct) {
                    $.each(checkedProducts, function(key, prodId) {
                        checkedProductsResult = _.union(checkedProducts, parseInt(currentProductId, 10));
                        window.appView.checkedProducts = _.union(window.appView.checkedProducts, parseInt(currentProductId, 10));
                    });
                    $('#checkedProducts').val(checkedProductsResult.join(','));
                } else {
                    $.each(checkedProducts, function(key, prodId) {
                        if(_.contains(checkedProducts,prodId) && currentProductId == prodId) {
                            var arrayIndex = checkedProducts.indexOf(prodId);
                            checkedProducts.splice(arrayIndex, 1);
                            window.appView.checkedProducts.splice(arrayIndex, 1);
                        }
                        checkedProductsResult = checkedProducts;
                    });
                    $('#checkedProducts').val(checkedProductsResult.join(','));
                }
            } else if(isCheckedProduct) {
                $('#checkedProducts').val(currentProductId);
                window.appView.checkedProducts.push(currentProductId);
            }

            this.model.set({checked: isCheckedProduct});
            checkboxRadioStyle();
        },
        addAction: function(e) {
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