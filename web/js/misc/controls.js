$(function() {

	$(document).on('click', '.save-quote', function(e) {
		saveQuote($(e.target).parent().data('qid'), $(e.target).parent().data('sendmail'));
	}).on('click', '#save-and-send-quote', function() {
        showMailMessageEdit('new quote', function(message) {
            saveQuote($('#save-and-send-quote').data('qid'), true, message);
        })
    }).on('click', '#same-for-shipping', function() {
		var shippingForm = $('#shipping-user-address');
		var billingForm  = $('#quote-billing-info');
		if(shippingForm.length) {
			$(':input[name]', billingForm).each(function() {
				$('[name=' + $(this).attr('name') + ']', shippingForm).val($(this).val());
			});
			var self = $(this);
			shippingForm.find('input, select').each(function() {
				if(self.prop('checked')) {
					$(this).attr('disabled', true);
				} else {
					$(this).removeAttr('disabled');
				}

			});
		}
	}).on('blur', '#quote-title', function() {
		saveQuote($('.save-quote:first').data('qid'), false);
	});
});

function saveQuote(qid, sendEmail, mailMessage) {
    var quoteData = {};
    if(typeof sendEmail == 'undefined') {
		sendEmail = false;
	}
	quoteData = {
		quoteId     : qid,
		quoteTitle  : $('#quote-title').val(),
		createdDate : $('#datepicker-created').val(),
		expiresDate : $('#datepicker-expires').val(),
		shipping    : $('#shipping-user-address').serialize(),
		billing     : $('#quote-user-address').serialize(),
		sendMail    : sendEmail,
        mailMessage : mailMessage
	};
	$.ajax({
		url        : $('#website_url').val() + 'plugin/quote/run/build/',
		type       : 'post',
		dataType   : 'json',
		data : quoteData,
		beforeSend : function() {showSpinner();},
		success : function(response) {
			hideSpinner();
			showMessage(response.responseText);
		}
	})
}