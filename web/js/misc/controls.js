// Quote system. Control panel.

$(function() {
    // current quote id
    var quoteId = $('#quote-id').val();

    //same fore shipping checkbox handling
    $(document).on('click', '#same-for-shipping', function(e) {
        var shippingForm = $('#shipping-user-address');
        var billingForm  = $('#plugin-quote-quoteform');
        if(shippingForm.length) {
            $(':input[name], select[name]', billingForm).each(function() {
                var shippingFormEl = $('[name=' + $(this).attr('name') + ']', shippingForm);
                if($(e.currentTarget).prop('checked')) {
                    if($(this).hasClass('state') && $(this).is(':visible')) {
                        $('select.state', shippingForm).html($(this).html()).parent('div').show();
                    }
                    shippingFormEl.val($(this).val()).attr('readonly', true);
                } else {
                    shippingFormEl.val('').removeAttr('readonly');
                    $('select.state', shippingForm).parent('div').hide();
                }
            });
        }
    });

    $(document).on('blur', '#quote-title', function(){
        updateQuote(quoteId, false);
    });

    // handling remove link click
    $(document).on('click', '.remove-product', function() {
        var selfEl = $(this);
        showConfirm('You are about to remove an item. Are you sure?', function() {
            $.ajax({
                url        : $('#website_url').val() + 'api/quote/products/id/' + selfEl.data('pid'),
                type       : 'delete',
                data       : JSON.stringify({qid: quoteId}),
                dataType   : 'json',
                beforeSend : showSpinner()
            }).done(function(response) {
                hideSpinner();
                recalculate({summary: response});
                selfEl.closest('tr').remove();
            });
        });
    });


    // quote control click handling
    $(document).on('click', '.quote-control', function(e) {
        var control  = $(e.currentTarget);
        if(parseInt(control.data('sendmail')) == 1) {
            showMailMessageEdit(control.data('trigger'), function(message) {
                updateQuote(quoteId, true, message);
            });
        } else {
            showLoader();
            updateQuote(quoteId, false);
        }
    });

    // editable (quantity, price) fields handling
    $(document).on('blur', '.quote-recalculate', function(e) {
        var field = $(e.currentTarget);
        var scope = field.data('scope');
        var type  = field.data('type');

        var data = {
            qid   : quoteId,
            type  : type,
            value : field.val()
        };

        switch (scope) {
            case 'quote-item':
                var productId = field.data('pid');
                data.value    = accounting.unformat(data.value);
                var request   = _update('api/quote/products/id/' + productId, data);
                request.done(function(response) {
                    hideSpinner();
                    $.extend(data, {
                        calculateProduct : true,
                        productId        : productId,
                        summary          : response
                    });
                    recalculate(data);
                });
                break;
            case 'quote-partial':
                var request = _update('api/quote/quotes/', data);
                request.done(function(response) {
                    hideSpinner();
                    $.extend(data, {summary:response});
                    recalculate(data);
                });
                break;
        }
    });

    $(document).on('change', '#quote-discount-rate', function(e) {
        var data = {
            qid   : quoteId,
            type  : 'taxrate',
            value : $(e.currentTarget).val()
        };
        var request = _update('api/quote/quotes/', data);
        request.done(function(response) {
            hideSpinner();
            $.extend(data, {summary:response});
            recalculate(data);
        });
    });
});


var updateQuote = function(quoteId, sendMail, mailMessage) {
    var data = {
        qid         : quoteId,
        sendMail    : sendMail,
        title       : $('#quote-title').val(),
        disclaimer  : $('.quote-disclaimer-text').val(),
        createdAt   : $('#datepicker-created').val(),
        expiresAt   : $('#datepicker-expires').val(),
        shipping    : $('#shipping-user-address').serialize(),
        billing     : $('#plugin-quote-quoteform').serialize(),
        mailMessage : (sendMail) ? mailMessage : ''
    };

    var request = _update('api/quote/quotes/', data);
    request.done(function(response, status, xhr) {
        hideLoader();
        recalculate({summary:response});
    });
};

var _update = function(apiUrl, data) {
    return $.ajax({
        type       : 'put',
        url        : $('#website_url').val() + apiUrl,
        dataType   : 'json',
        data       : JSON.stringify(data),
        beforeSend : showSpinner()
    });
};

var recalculate = function(options) {
    if(options.hasOwnProperty('calculateProduct') && options.calculateProduct === true) {
        var unitPriceContainer = $('input.price-unit[data-pid="' + options.productId + '"]');

        var unitPrice  = parseFloat(accounting.unformat(unitPriceContainer.val()));
        var qty        = parseInt($('input.qty-unit[data-pid="' + options.productId + '"]').val());
        var totalPrice = unitPrice * qty;

        $('.price-total[data-pid="' + options.productId + '"]').text(accounting.formatMoney(totalPrice));
        unitPriceContainer.val(accounting.formatNumber(unitPrice, 2));
    }
    var summary = options.summary;

    $('.sub-total').text(accounting.formatMoney(summary.subTotal));
    $('.tax-total').text(accounting.formatMoney(summary.totalTax));
    $('#quote-shipping-price').val(accounting.formatNumber(summary.shipping, 2));
    $('#quote-discount').val(accounting.formatNumber(summary.discount, 2));
    $('#quote-tax-discount').text(accounting.formatMoney(summary.discountTax));
    $('#quote-discount-with-tax').text(accounting.formatMoney(summary.discountWithTax));
    $('.grand-total').text(accounting.formatMoney(summary.total));
    $('.totalwotax-total').text(accounting.formatMoney(summary.total- summary.totalTax));
    $('#quote-shipping-with-tax').text(accounting.formatMoney(summary.shippingWithTax));
};