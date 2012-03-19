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
		}
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
	});
});