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
            $(':input[name]', billingForm).each(function() {
                $('[name=' + $(this).attr('name') + ']', shippingForm).val((check.prop('checked') ? $(this).val() : ''))
                    .attr('readonly', true);
                shippingForm.find('input, select').each(function() {
                    if(!check.prop('checked')) {
                        $(this).removeAttr('readonly');
                    }
                });
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
                var totalPriceValue  = qtyControl.closest('tr').find('span.price-total').text().replace(/[^\d]*/, '');
                var singlePriceValue = qtyControl.closest('tr').find('span.price-unit').text().replace(/[^\d]*/, '');
                var totalPriceRecounted = parseFloat(singlePriceValue) * data.qty;
                qtyControl.closest('tr').find('span.price-total').text(itemTotalPrice.replace(totalPriceValue, totalPriceRecounted.toFixed(2)));
        });
    }).on('click', '.remove-product', function(event) {
        smoke.confirm('You are about to remove an item. Are you sure?', function(e) {
            if(e) {
                $.ajax({
                    url      : $('#website_url').val() + 'api/quote/products/id/' + $(event.currentTarget).data('pid'),
                    type     : 'delete',
                    data     : JSON.stringify({qid: $('.save-action:first').data('qid')}),
                    dataType : 'json'
                }).done(function(response) {
                    $(event.currentTarget).closest('tr').remove();
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
            return false;
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
        }).done(function(response) {});
    });
});


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
        createdAt   : $('#datepicker-created').val(),
        expiresAt   : $('#datepicker-expires').val(),
        shipping    : $('#shipping-user-address').serialize(),
        billing     : $('#plugin-quote-quoteform').serialize(),
        mailMessage : mailMessage
    };
    console.log(data);
    $.ajax({
        url        : $('#website_url').val() + 'api/quote/quotes/',
        type       : 'put',
        dataType   : 'json',
        data       : JSON.stringify(data),
        beforeSend : showSpinner
    }).done(function(response) {
            hideSpinner();
    });
}
