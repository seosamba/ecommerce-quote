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

    $(document).on('change', '.quote-info', function(e){
        var controlEl  = $(e.originalEvent),
            additionalEmailValidate = 0,
            disableAutosaveEmail = 0;

        if($(this).closest('.quote-info').hasClass('allow-auto-save')) {
            if($(this).closest('.quote-info').hasClass('disable-autosave-email')) {
                disableAutosaveEmail = 1;
            }
            
            if(typeof $(controlEl).get(0) !== 'undefined' && ($(controlEl).get(0).target.id == 'quote-form-email' || $(controlEl).get(0).target.id == 'email')) {
                additionalEmailValidate = 1;
                var defaultValue = $(controlEl).get(0).target.defaultValue;

                if(typeof defaultValue !== 'undefined' && defaultValue !== '') {
                    $('#'+$(controlEl).get(0).target.id).attr('data-email', defaultValue);
                }
                if(disableAutosaveEmail) {
                    return false;
                }
            }
            updateQuote(quoteId, false, '', '', '', true, additionalEmailValidate, disableAutosaveEmail);
        }
    });

    $(document).on('click', '.use-lead-address', function(e){
        e.preventDefault();
        if($(this).closest('.quote-info').hasClass('allow-auto-save')) {
            var addressType = $(this).data('type');

            showConfirm('Would you like to refresh the quote '+ addressType + ' address with lead address?', function() {
                $.ajax({
                    url: $('#website_url').val() + 'plugin/quote/run/useLeadAddress',
                    data: {'quoteId': quoteId, 'addressType': addressType},
                    type: 'post',
                    dataType: 'json'
                }).done(function(response) {
                    if (response.error == '1') {
                        showMessage(response.responseText, true, 5000);
                        return false;
                    } else {
                        showMessage(response.responseText, false, 3000);
                        window.setTimeout(function(){
                            window.location.reload();
                        }, 2000);
                    }
                });
            });
        }
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
            showMessage('Please specify partial payment amount', true, 5000);
            hideLoader();
            return false;
        }

        if(parseInt(control.data('sendmail')) == 1) {
            $.ajax({
                url        : $('#website_url').val() + 'plugin/quote/run/checkquoteExpired/',
                type       : 'post',
                data       : {quoteId: quoteId},
                dataType   : 'json',
                beforeSend : showSpinner()
            }).done(function(response) {
                if (response.error == '1') {
                    showMessage(response.responseText, true, 5000);
                    return false;
                } else {
                    showMailMessageEdit(control.data('trigger'), function (message, ccEmails) {
                        updateQuote(quoteId, true, message, '', ccEmails);
                    }, 'customer');
                }
            });
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
        var field = $(e.currentTarget),
            scope = field.data('scope'),
            type  = field.data('type'),
            sid = $(field).data('sid'),
            value = field.val();

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
            sid   : sid
        };

        switch (scope) {
            case 'quote-item':
                var productId = field.data('pid');
                data.value    = accounting.unformat(data.value);
                var request   = _update('api/quote/products/id/' + productId, data, false);
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
                var request = _update('api/quote/quotes/', data, false);
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
        var request = _update('api/quote/quotes/', data, false);
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

        updateQuote(quoteId, false, '');

        var request = _update('api/quote/quotes/', data, false);

        request.done(function(response) {
            hideSpinner();
            $.extend(data, {summary:response});
            recalculate(data);
        });

    });

    $(document).on('change', '#partial-payment-type',function(e){
        e.preventDefault();

        var data = {
            qid   : quoteId,
            type  : 'taxrate',
            value : $(e.currentTarget).val()
        };

        updateQuote(quoteId, false, '');

        var request = _update('api/quote/quotes/', data, false);

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

        if (paymentType === 'partial_payment') {
            $('#mark-first-payment-paid-block').removeClass('hidden');
        } else {
            $('#mark-first-payment-paid-block').addClass('hidden');
        }


        changePaymentTypeMessage(paymentType, isSignatureRequired);
    });

    $(document).on('change', '#quote-signature-required', function (e) {
        var paymentType = $('#quote-payment-type-selector').val(),
            isSignatureRequired = 0;

        if ($('#quote-signature-required').is(':checked')) {
            isSignatureRequired = 1;
            $('#quote-signature-block').removeClass('hidden');
        } else {
            $('#quote-signature-block').addClass('hidden');
        }

        changePaymentTypeMessage(paymentType, isSignatureRequired);
    });

    var quoteDraggableProducts = $('#quote-draggable-products').val();
    if(quoteDraggableProducts) {
        $('#quote-sortable').sortable({
            deactivate: function(event, ui) {
                processDraggable(quoteId);
            }
        });
    }

    $(document).on('change', '#is-partial-payment-payed', function (e) {
        var self = $(this),
            message = $('#confirm-message-for-first-payment-checked').text(),
            isChecked = false;

            if (self.is(':checked')) {
                message = $('#confirm-message-for-first-payment-not-checked').text()+' '+$('#partial-payment-percentage-payment-amount').text()+' '+$('#confirm-message-for-first-payment-not-checked-second-part').text();
                isChecked = true;
            }

        showConfirm(message, function() {
            $.ajax({
                url        : $('#website_url').val() + 'api/quote/partialpayment/',
                type       : 'POST',
                data       : {
                    quoteId: quoteId,
                    partialPercentage: $('#partial-payment-percentage').val(),
                    partialType: $('#partial-payment-type').val()
                },
                dataType   : 'json',
                beforeSend : showSpinner()
            }).done(function(response) {
                hideSpinner();
                showMessage(response.responseText.generalSuccess, true, 3000);
                window.setTimeout(function () {
                    window.location.reload();
                }, 2000);
                return false;
            }).fail(function (response) {
                showMessage(JSON.parse(response.responseText), true, 5000);
                if (isChecked === true) {
                    self.prop('checked', false);
                } else {
                    self.prop('checked', true);
                }
            });
        }, function(){
            if (isChecked === true) {
                self.prop('checked', false);
            } else {
                self.prop('checked', true);
            }
        });
    });

    getLeadLink(quoteId);
    getDisableEmailAutosave();

    $(document).on('change', '.custom-field-element', function(e) {
        e.preventDefault();
        var cartId = $('#cart-id').val(),
            customFieldValue = $(this).val(),
            customFieldType = $(this).data('type'),
            customFieldId = $(this).data('field-id');

        $.ajax({
            type: 'POST',
            url:  $('#website_url').val() +  'plugin/quote/run/updateCustomfield',
            data: {
                cartId           : cartId,
                customFieldValue : customFieldValue,
                customFieldType  : customFieldType,
                customFieldId    : customFieldId
            }
        }).done(function(response){
            if (response.error == '1') {
                showMessage(response.responseText, true, 3000);
                return false;
            } else {
                //showMessage(response.responseText, false, 2000);
            }
        });
    });

});

var getLeadLink = function (quoteId) {
    $.ajax({
        url: $('#website_url').val() + 'plugin/quote/run/getLeadLink',
        data: {'quoteId': quoteId},
        type: 'post',
        dataType: 'json'
    }).done(function(response) {
        $('.lead-link').remove();
        if(response.responseText.link) {
            var leadProfile = '<a target="_blank" class="lead-link icon-link fl-right grid_7 alpha icon-profile" title="Go to CRM Lead" href="'+ response.responseText.link +'"></a>';
            $('#quote-form-email').closest('p').find('label').append(leadProfile);
            //$('#email').closest('p').find('label').append(leadProfile);
        }
    });
}

var getDisableEmailAutosave = function () {
    if($('.quote-info').hasClass('allow-auto-save') && $('.quote-info').hasClass('disable-autosave-email')) {
        $('.disable-email-autosave').remove();
        var tooltipEl = '<a href="javascript:;" class="disable-email-autosave ticon-info tooltip icon18 fl-right grid_8" title="Email won\'t be saved automatically, please turn off (Disable email autosave) in config or Save the quote manually"></a>';
        $('#quote-form-email').closest('p').find('label').append(tooltipEl);
        $('#email').closest('p').find('label').append(tooltipEl);
    }
}

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

var updateQuote = function(quoteId, sendMail, mailMessage, eventType, ccEmails, noSpinner, additionalEmailValidate, disableAutosaveEmail) {
    var quoteForm = $('#plugin-quote-quoteform'),
        quoteShippingUserAddressForm = $('#shipping-user-address'),
        notValidElements = [],
        errorMessage = false;

    if(typeof noSpinner === 'undefined') {
        noSpinner = false;
    }

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

    if(disableAutosaveEmail) {
        var quoteFormEmail = $('#quote-form-email').data('email');
        var email = $('#email').data('email');

        if(typeof quoteFormEmail !== 'undefined' && quoteFormEmail !== '') {
            $('#quote-form-email').val(quoteFormEmail);
        }

        if(typeof email !== 'undefined' && email !== '') {
            $('#email').val(email);
        }
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
        partialPaymentType : $('#partial-payment-type').val(),
        ccEmails    : ccEmails,
        additionalEmailValidate : (additionalEmailValidate) ? additionalEmailValidate : '',
    };

    var request = _update('api/quote/quotes/', data, noSpinner);
    request.done(function(response, status, xhr) {
        hideLoader();
        if (response.error == 1) {
            showMessage(response.responseText, true, 5000);
            return false;
        }

        if (response.error == 0 && response.responseText) {
            showMessage(response.responseText, false, 3000);
        }

        if(!$('.quote-info').hasClass('allow-auto-save') && response.allowAutosave) {
            $('.quote-info').addClass('allow-auto-save');

            getDisableEmailAutosave();

            if(!$('.quote-info').hasClass('disable-autosave-email') && response.disableAutosaveEmail) {
                $('.quote-info').addClass('disable-autosave-email');
            }

            var quoteFormEmail = $('#quote-form-email').val();
            var email = $('#email').val();

            if(typeof quoteFormEmail !== 'undefined' && quoteFormEmail !== '') {
                $('#quote-form-email').attr('data-email', quoteFormEmail);
            }

            if(typeof email !== 'undefined' && email !== '') {
                $('#email').attr('data-email', email);
            }
        }

        getLeadLink(quoteId);
        processDraggable(quoteId);
        recalculate({summary:response});
    });
};

var _update = function(apiUrl, data, noSpinner) {
    return $.ajax({
        type       : 'put',
        url        : $('#website_url').val() + apiUrl,
        dataType   : 'json',
        data       : JSON.stringify(data),
        beforeSend : (!noSpinner) ? showSpinner() : ''
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
            $('.price-total[data-sid="' + sid + '"]').text(accounting.formatMoney(totalPrice, accounting.settings.currency));
        } else {
            $('.price-total[data-pid="' + options.productId + '"]').text(accounting.formatMoney(totalPrice, accounting.settings.currency));
        }
        unitPriceContainer.val(accounting.formatNumber(unitPrice, 2));
    }
    var summary = options.summary;

    $('.sub-total').text(accounting.formatMoney(summary.subTotal, accounting.settings.currency));
    $('.tax-total').text(accounting.formatMoney(summary.totalTax, accounting.settings.currency));
    $('#quote-shipping-price').val(accounting.formatNumber(summary.shipping, 2));
    $('#quote-discount').val(accounting.formatNumber(summary.discount, 2));

    $('#quote-tax-discount').text(accounting.formatMoney(summary.discountTax, accounting.settings.currency));
    $('#quote-discount-with-tax').text(accounting.formatMoney(summary.discountWithTax, accounting.settings.currency));
    $('.grand-total').text(accounting.formatMoney(summary.total, accounting.settings.currency));
    $('.totalwotax-total').text(accounting.formatMoney(summary.total- summary.totalTax, accounting.settings.currency));
    $('#quote-shipping-with-tax').text(accounting.formatMoney(summary.shippingWithTax, accounting.settings.currency));

    if ($('#partial-payment-percentage').length > 0) {
        var currentPercentage = $('#partial-payment-percentage').val(),
            partialTotal = accounting.formatMoney((currentPercentage*summary.total)/100);

            if ($('#partial-payment-type').val() === 'amount') {
                partialTotal = accounting.formatMoney(currentPercentage);
                $('#percentage-amount-text').addClass('hidden');
            } else {
                $('#percentage-amount-text').removeClass('hidden');
            }

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
                    var request = _update('api/quote/quotes/', data, false);
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
