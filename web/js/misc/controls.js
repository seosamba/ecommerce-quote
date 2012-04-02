$(function() {

	$(document).on('click', '.save-quote', function(e) {
		var quoteData = {
			quoteId     : $(e.target).parent().data('qid'),
			quoteTitle  : $('#quote-title').val(),
			createdDate : $('#datepicker-created').val(),
			expiresDate : $('#datepicker-expires').val(),
			shipping    : $('#shipping-user-address').serialize(),
			billing     : $('#quote-user-address').serialize(),
			sendMail    : $(e.target).parent().data('sendmail')
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
	});
});