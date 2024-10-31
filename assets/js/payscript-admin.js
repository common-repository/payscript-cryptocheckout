jQuery(document).ready(function(){
    jQuery('.get_payscript_balance').on('click',function(e){
        e.preventDefault();
        jQuery.ajax({
            type: 'POST',
            url: PayscriptAjax.ajaxurl,
            data: {"action":"payscript_get_balance","authNonce":PayscriptAjax.authNonce},
            dataType: 'json',
            success: function(data) {
                jQuery('.balance_area').html('');
                jQuery('.balance_area').html(data.message);
            }
        });
    });
});