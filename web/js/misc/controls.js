$(function() {
    $('.quote-control').button();

    $(document).on('click', '.save-action', function(e) { //quote controls handling
        var control     = $(e.currentTarget);
        var sendMail    = control.data('sendmail');
        if(parseInt(sendMail)) {
            showMailMessageEdit(control.data('trigger'), function(message) {
                updateQuote(control.data('qid'),sendMail, message);
            });
        } else {
            updateQuote(control.data('qid'), sendMail);
        }
    }).on('click', '#same-for-shipping', function(e) {  // billing form "same for shipping" checkbox handling
        var shippingForm = $('#shipping-user-address');
        var billingForm  = $('#plugin-quote-quoteform');
        var check        = $(e.currentTarget);
        if(shippingForm.length) {
            $(':input[name], select[name]', billingForm).each(function() {
                var shippingFormEl = $('[name=' + $(this).attr('name') + ']', shippingForm);
                if(check.prop('checked')) {
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
    }).on('blur', '.quote-qty', function(e) { // product quantity control handling
        var qtyControl = $(e.currentTarget);
        var data       = {
            qty : qtyControl.val(),
            qid : $('.save-action:first').data('qid')
        };
        $.ajax({
            url        : $('#website_url').val() + 'api/quote/products/id/' + qtyControl.attr('id') + '/type/qty/',
            type       : 'put',
            dataType   : 'json',
            data       : JSON.stringify(data),
            beforeSend : showSpinner
        }).done(function(response) {
                hideSpinner();
                var itemTotalPrice   = qtyControl.closest('tr').find('span.price-total').text();
                var totalPriceValue  = itemTotalPrice.replace(/[^\d]*/, '');
                var singlePriceValue = qtyControl.closest('tr').find('span.price-unit').text().replace(/[^\d]*/, '');
                var totalPriceRecounted = parseFloat(singlePriceValue) * data.qty;
                qtyControl.closest('tr').find('span.price-total').text(itemTotalPrice.replace(totalPriceValue, totalPriceRecounted.toFixed(2)));

                updateTotal(response);
        });
    }).on('click', '.remove-product', function(event) {
        smoke.confirm('You are about to remove an item. Are you sure?', function(e) {
            if(e) {
                $.ajax({
                    url      : $('#website_url').val() + 'api/quote/products/id/' + $(event.currentTarget).data('pid'),
                    type     : 'delete',
                    data     : JSON.stringify({qid: $('.save-action:first').data('qid')}),
                    dataType : 'json',
                    beforeSend: showSpinner
                }).done(function(response) {
                    $(event.currentTarget).closest('tr').remove();
                    hideSpinner();
                    updateTotal(response);
                });
            } else {
                $('.smoke-base').remove();
            }
        }, {classname:"errors", 'ok':'Yes', 'cancel':'No'});
    }).on('blur', '#quote-shipping-price', function() {
        var quoteId = $('.save-action:first').data('qid');
        var shippingPrice = parseFloat($(this).val());
        if(!shippingPrice) {
            $(this).val(0);
            shippingPrice = 0;
        }
        $.ajax({
            url      : $('#website_url').val() + 'api/quote/quotes/',
            type     : 'put',
            data     : JSON.stringify({
                id: quoteId,
                partial: 'shipping',
                shippingPrice: shippingPrice
            }),
            dataType : 'json'
        }).done(function(response) {
           $('.grand-total').text(response.grandTotalCurrency);
        });
    });
});


function updateTotal(options) {
    $('.sub-total').text(options.subTotal);
    $('.tax-total').text(options.totalTax);
    $('.grand-total').text(options.total);
}

function updateQuote(quoteId, sendMail, mailMessage) {
    if(typeof sendMail == 'undefined') {
        sendMail = false;
    }
    if(typeof mailMesage == 'undefined') {
        mailMessage = '';
    }
    var data = {
        id          : quoteId,
        sendMail    : sendMail,
        title       : $('#quote-title').val(),
        disclaimer  : $('.quote-disclaimer-text').val(),
        createdAt   : $('#datepicker-created').val(),
        expiresAt   : $('#datepicker-expires').val(),
        shipping    : $('#shipping-user-address').serialize(),
        billing     : $('#plugin-quote-quoteform').serialize(),
        mailMessage : mailMessage
    };
    $.ajax({
        url        : $('#website_url').val() + 'api/quote/quotes/',
        type       : 'put',
        dataType   : 'json',
        data       : JSON.stringify(data),
        beforeSend : showSpinner
    }).done(function(response) {
            hideSpinner();
            showMessage('Quote information updated.');
    });
}
