// Quote system. Controll panel.

$(function() {
    //apply jQueryUI buttons to quote controls
    $('.quote-control').button();

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

    // handling remove link click
    $(document).on('click', '.remove-product', function(e) {
        showConfirm('You are about to remove an item. Are you sure?', function() {
            $.ajax({
                url        : $('#website_url').val() + 'api/quote/products/id/' + $(e.currentTarget).data('pid'),
                type       : 'delete',
                data       : JSON.stringify({qid: quoteId}),
                dataType   : 'json',
                beforeSend : showSpinner
            }).done(function(response) {
                hideSpinner();
                recalculate({summary: response});
                $(e.currentTarget).closest('tr').remove();
            });
        });
    })


    // quote control click handling
    $(document).on('click', '.quote-control', function(e) {
        var control  = $(e.currentTarget);
        if(parseInt(control.data('sendmail')) == 1) {
            showMailMessageEdit(control.data('trigger'), function(message) {
                updateQuote(quoteId, true, message);
            });
        } else {
            updateQuote(quoteId, false);
        }
    });

    // editable (qantity, price) fields handling
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
                var request   = _update('api/quote/products/id/' + productId, data)
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
    })

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
        qid          : quoteId,
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
        hideSpinner();
    });
}

var _update = function(apiUrl, data) {
    return $.ajax({
        type       : 'put',
        url        : $('#website_url').val() + apiUrl,
        dataType   : 'json',
        data       : JSON.stringify(data),
        beforeSend : showSpinner
    });
}

var recalculate = function(options) {
    var symbol = $('#quote-currency').val();
    if(options.hasOwnProperty('calculateProduct') && options.calculateProduct === true) {
        var unitPriceContainer = $('input.price-unit[data-pid="' + options.productId + '"]');

        var unitPrice  = parseFloat(unitPriceContainer.val());
        var qty        = parseInt($('input.qty-unit[data-pid="' + options.productId + '"]').val())
        var totalPrice = unitPrice * qty;

        $('.price-total[data-pid="' + options.productId + '"]').text(symbol + totalPrice.toFixed(2));
        unitPriceContainer.val(parseFloat(unitPrice).toFixed(2));
    }
    var summary = options.summary;

    $('.sub-total').text(symbol + summary.subTotal.toFixed(2));
    $('.tax-total').text(symbol + summary.totalTax.toFixed(2));
    $('#quote-shipping-price').val(parseFloat(summary.shipping).toFixed(2));
    $('#quote-discount').val(parseFloat(summary.discount).toFixed(2));
    $('#quote-tax-discount').text(symbol + parseFloat(summary.discountTax).toFixed(2))
    $('.grand-total').text(symbol + summary.total.toFixed(2));
}