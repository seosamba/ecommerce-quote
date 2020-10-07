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

    $(document).on('change', '#overwrite-quote-user-shipping', function(){
        if ($(this).is(':checked')) {
            $('#overwrite-quote-user-billing').prop('checked', false);
        } else {
            $('#overwrite-quote-user-billing').prop('checked', true);
        }
    });

    // handling remove link click
    $(document).on('click', '.remove-product', function() {
        var selfEl = $(this);
        var sid = $(selfEl).data('sid');
        showConfirm('You are about to remove an item. Are you sure?', function() {
            $.ajax({
                url        : $('#website_url').val() + 'api/quote/products/id/' + selfEl.data('pid'),
                type       : 'delete',
                data       : JSON.stringify({qid: quoteId, sid: sid}),
                dataType   : 'json',
                beforeSend : showSpinner()
            }).done(function(response) {
                hideSpinner();
                recalculate({summary: response});
                selfEl.closest('tr').remove();
            });
        });
    });

    $(document).on('click', '#add-product-to-quote', function(e) {
        var eventType = $(this).data('type');
        updateQuote(quoteId, false, '', eventType);
    });

    // quote control click handling
    $(document).on('click', '.quote-control', function(e) {
        var control  = $(e.currentTarget);

        if ($('#quote-payment-type-selector').attr('disabled') !== 'disabled' &&  $('#quote-payment-type-selector').val() === 'partial_payment' && (parseInt($('#partial-payment-percentage').val()) < 1 || isNaN(parseInt($('#partial-payment-percentage').val())))) {
            showMessage('Please specify partial payment percentage', true, 5000);
            hideLoader();
            return false;
        }

        if(parseInt(control.data('sendmail')) == 1) {
            showMailMessageEdit(control.data('trigger'), function(message, ccEmails) {
                updateQuote(quoteId, true, message, '', ccEmails);
            }, 'customer');
        } else {
            var eventType = '';
            if(typeof $(this).data('type') !== 'undefined') {
                eventType = $(this).data('type')
            }
            showLoader();
            updateQuote(quoteId, false, '', eventType);
        }
    });

    //clone quote
    $(document).on('click', '.clone-quote', function(e) {
        var pageId = $('#page_id').val();

        $.ajax({
            url        : $('#website_url').val() + 'api/quote/quotes/',
            type       : 'post',
            data       : {type: 'clone', quoteId: quoteId, pageId: pageId},
            dataType   : 'json',
            beforeSend : showSpinner()
        }).done(function(response) {
            var quoteUrl = '<a href="'+ $('#website_url').val() + response.id +'.html" target="_blank" title="'+ response.id +'">'+ $('#website_url').val() +response.id + '.html</a>';
            hideSpinner();
            showMessage('The quote has been duplicated. ' + '<br/>' + quoteUrl, false, 15000);
            recalculate({summary: response});
        });
    });

    // editable (quantity, price) fields handling
    $(document).on('blur', '.quote-recalculate', function(e) {
        var field = $(e.currentTarget);
        var scope = field.data('scope');
        var type  = field.data('type');
        var sid = $(field).data('sid');
        var value = field.val();

        if(type == 'qty') {
            value = Math.abs(value);
            if(value == 0) {
                showMessage('You can\'t set zero qty for product!', true, 3000);
                return false;
            }
            field.val(value);
        } else if(type == 'price') {
            value = Math.abs(value);
            if(value == 0) {
                showMessage('You can\'t set zero price for product!', true, 3000);
                return false;
            }
            field.val(value);
        }

        var data = {
            qid   : quoteId,
            type  : type,
            value : value,
            sid : sid
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
                    recalculate(data, sid);
                });
                break;
            case 'quote-partial':
                var request = _update('api/quote/quotes/', data);
                request.done(function(response) {
                    hideSpinner();
                    $.extend(data, {summary:response});
                    recalculate(data, sid);
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

    $(document).on('change', '.quote-disclaimer-text', function(e) {
        e.preventDefault();
        $('.quote-control-save').trigger('click');
        showMessage('Quote notes has been saved', false, 3000);
    });

    $(document).on('keyup', '#partial-payment-percentage',function(e){
        e.preventDefault();

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

    $(document).on('change', '#quote-payment-type-selector', function (e) {
        var paymentType = $(e.currentTarget).val(),
            isSignatureRequired = 0;

        if ($('#quote-signature-required').is(':checked')) {
            isSignatureRequired = 1;
        }

        if (paymentType === 'only_signature') {
            $('#quote-signature-required').prop('disabled', true).prop('checked', true);
        } else {
            $('#quote-signature-required').prop('disabled', false);
        }

        changePaymentTypeMessage(paymentType, isSignatureRequired);
    });

    $(document).on('change', '#quote-signature-required', function (e) {
        var paymentType = $('#quote-payment-type-selector').val(),
            isSignatureRequired = 0;

        if ($('#quote-signature-required').is(':checked')) {
            isSignatureRequired = 1;
        }

        changePaymentTypeMessage(paymentType, isSignatureRequired);
    });

});


var processDraggable = function(quoteId) {
    var quoteDraggableProducts = $('#quote-draggable-products').val();
    if(quoteDraggableProducts) {
        var sortProductsSids = [];

        $('.quote-sortable-product-row').each(function (index) {
            sortProductsSids.push($(this).data('sort-product-sid'));
        });

        if(sortProductsSids.length) {
            $.ajax({
                url: $('#website_url').val() + 'plugin/quote/run/saveDragListOrder',
                data: {'quoteId': quoteId, 'data': sortProductsSids},
                type: 'post',
                dataType: 'json'
            }).done(function(response) {});
        }
    }
    return true;
}

var updateQuote = function(quoteId, sendMail, mailMessage, eventType, ccEmails) {
    var quoteForm = $('#plugin-quote-quoteform'),
        quoteShippingUserAddressForm = $('#shipping-user-address'),
        notValidElements = [],
        errorMessage = false;
    
    if(typeof quoteForm !== 'undefined') {
        $(':input[name], select[name]', quoteForm).each(function(key, field) {
            if($(field).hasClass('required')){
                if($(field).attr('id') != 'quote-form-email' && $(this).val() === '') {
                    notValidElements.push(field);
                }
            }
        });
    }

    if(typeof quoteShippingUserAddressForm !== 'undefined') {
        $(':input[name], select[name]', quoteShippingUserAddressForm).each(function(key, field) {
            if($(field).hasClass('required')){
                if($(field).attr('id') != 'email' && $(this).val() === '') {
                    notValidElements.push(field);
                }
            }
        });
    }

    if(notValidElements.length) {
        errorMessage = true;
    }

    var isQuoteSignatureRequired = 0;
    if ($('#quote-signature-required').is(':checked')) {
        isQuoteSignatureRequired = 1;
    }

    var data = {
        qid         : quoteId,
        sendMail    : sendMail,
        title       : $('#quote-title').val(),
        disclaimer  : $('.quote-disclaimer-text').val(),
        createdAt   : $('#datepicker-created').val(),
        expiresAt   : $('#datepicker-expires').val(),
        shipping    : $('#shipping-user-address').serialize(),
        billing     : $('#plugin-quote-quoteform').serialize(),
        mailMessage : (sendMail) ? mailMessage : '',
        errorMessage: errorMessage,
        eventType   : (eventType) ? eventType : '',
        paymentType : $('#quote-payment-type-selector').val(),
        // pdfTemplate : $('#quote-pdf-template-selector').val(),
        isSignatureRequired : isQuoteSignatureRequired,
        partialPaymentPercentage : $('#partial-payment-percentage').val(),
        ccEmails    : ccEmails
    };

    var request = _update('api/quote/quotes/', data);
    request.done(function(response, status, xhr) {
        hideLoader();
        if (response.error == 1) {
            showMessage(response.responseText, true, 5000);
            return false;
        }
        processDraggable(quoteId);
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

var recalculate = function(options, sid) {
    if(options.hasOwnProperty('calculateProduct') && options.calculateProduct === true) {
        if(sid.length){
            var unitPriceContainer = $('input.price-unit[data-sid="' + sid + '"]');
            var qty        = parseInt($('input.qty-unit[data-sid="' + sid + '"]').val());
        } else {
            var unitPriceContainer = $('input.price-unit[data-pid="' + options.productId + '"]');
            var qty        = parseInt($('input.qty-unit[data-pid="' + options.productId + '"]').val());
        }

        var unitPrice  = parseFloat(accounting.unformat(unitPriceContainer.val()));
        var totalPrice = unitPrice * qty;

        if(sid.length){
            $('.price-total[data-sid="' + sid + '"]').text(accounting.formatMoney(totalPrice));
        } else {
            $('.price-total[data-pid="' + options.productId + '"]').text(accounting.formatMoney(totalPrice));
        }
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

    if ($('#partial-payment-percentage').length > 0) {
        var currentPercentage = $('#partial-payment-percentage').val(),
            partialTotal = accounting.formatMoney((currentPercentage*summary.total)/100);

        $('#partial-payment-percentage-payment-amount').html(partialTotal);
    }

};

function changePaymentTypeMessage(paymentType, isSignatureRequired) {
    if (paymentType === 'only_signature') {
        $('#quote-type-info-message').addClass('hidden');
    } else {
        $.ajax({
            url: $('#website_url').val() + 'plugin/quote/run/getPaymenttypeinfo',
            type: 'POST',
            data: {
                'quoteId': $('#quote-id-payment-type').val(),
                'paymentType': paymentType,
                'isSignatureRequired': isSignatureRequired
            },
            dataType: 'json',
            beforeSend: showSpinner()
        }).done(function (response) {
            hideSpinner();
            if (response.error == '0') {
                $('#quote-type-info-message').html(response.responseText);
                $('#quote-type-info-message').removeClass('hidden');
                if (paymentType === 'partial_payment') {
                    var data = {
                        qid   : $('#quote-id-payment-type').val(),
                        type  : 'taxrate',
                        value : $('#partial-payment-percentage').val()
                    };
                    var request = _update('api/quote/quotes/', data);
                    request.done(function(response) {
                        hideSpinner();
                        $.extend(data, {summary:response});
                        recalculate(data);
                    });

                }
            }
        }).fail(function (response) {
            showMessage(JSON.parse(response.responseText), true, 5000);
        });
    }
}