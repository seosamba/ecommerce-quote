// Quote system. Control panel.

$(function() {
    // current quote id
    var quoteId = $('#quote-id').val();

    //Disable "Edit page properties" on Quote
    var editPageLink = $('.tpopup.edit-page-link');
    if(editPageLink.length) {
        $(editPageLink).addClass('hidden');
    }

    //Disable "Delete this page" on Quote
    var delThisPage = $('#del-this-page');
    if(delThisPage.length) {
        $(delThisPage).addClass('hidden');
    }

    if(editPageLink.length && delThisPage.length) {
        //$('.page-control').addClass('hidden');
        var pageId = $('#page_id').val();

        $.ajax({
            url        : $('#website_url').val() + 'plugin/quote/run/showEditQuotePageConfig',
            type       : 'POST',
            data       : {pageId: pageId},
            dataType   : 'json',
        }).done(function(response) {
            if (typeof response.responseText.access !== 'undefined' && response.responseText.access === 'no_page_config') {
                $('.page-control').closest('ul').empty();
                $('#cpanelul').find('li:nth-child(1)').find('ul').append(response.responseText.quotePageConfig);
            } else {
                $('.page-control').closest('ul li:nth-child(1)').after($("<li>").append(response.responseText.quotePageConfig));
            }
        });
    }
});
