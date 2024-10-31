jQuery(document).ready(function(){
    jQuery('.clip-copy').on('click',function(){
        value = jQuery(this).parent().parent().find('input').val();
        var $temp = jQuery("<input>");
        jQuery("body").append($temp);
        $temp.val(value).select();
        document.execCommand("copy");
        $temp.remove();
        jQuery(this).parent().parent().append('<span class="copy_msg">copied!</span>');
        setTimeout(() => {
            jQuery('.copy_msg').fadeOut(300, function(){ jQuery(this).remove();});
        }, 2000);
    });
    if(jQuery("#fnp-timer").is(":visible")){
        document.getElementById('fnp-timer').innerHTML = jQuery('#fnp-timer').html();
        startTimer();
        startTimerForTicker();
    }
    
    function startTimer() {
      var presentTime = document.getElementById('fnp-timer').innerHTML;
      var timeArray = presentTime.split(/[:]+/);
      var m = timeArray[0];
      var s = checkSecond((timeArray[1] - 1));
      if(s==59){m=m-1}
      if(m == 0 && s == 59){
          jQuery('#fnp-timer').css('color','#FF0D00');
      }
      if(m<1 && s<1){
          jQuery.ajax({
            type: 'POST',
            url: PayscriptAjax.ajaxurl,
            data: {"action":"payscript_order_failed","authNonce":PayscriptAjax.authNonce,"transaction_id":jQuery('.ps-fnp-data').data('transaction_id'),"order_id":jQuery('.ps-fnp-data').data('order_id')},
            dataType: 'json',
            success: function(data) { 
                // location.href = data.redirect;
                jQuery('.payment-window').addClass("strict-hide");
                jQuery('.payment-window-timeout').removeClass("strict-hide");
            }
        });
          return false;
      }
      document.getElementById('fnp-timer').innerHTML =
        m + ":" + s;
      setTimeout(startTimer, 1000);
    }

    function checkSecond(sec) {
      if (sec < 10 && sec >= 0) {sec = "0" + sec}; // add zero in front of numbers < 10
      if (sec < 0) {sec = "59"};
      return sec;
    }
    
    function startTimerForTicker(){
        //call ajax for ticker
        jQuery.ajax({
            type: 'POST',
            url: PayscriptAjax.ajaxurl,
            data: {"action":"payscript_get_transaction_status","authNonce":PayscriptAjax.authNonce,"transaction_id":jQuery('.ps-fnp-data').data('transaction_id'),"order_id":jQuery('.ps-fnp-data').data('order_id')},
            dataType: 'json',
            success: function(data) { 
                if(data.success == 2){
                    location.href = data.redirect;
                }else if(data.success == 3){
                    jQuery('.payment-window').addClass("strict-hide");
                    jQuery('.payment-window-timeout').removeClass("strict-hide");
                }else if(data.success == 4){
                    jQuery('.payment-window').addClass("strict-hide");
                    jQuery('.payment-window-timeout').addClass("strict-hide");
                    jQuery('.payment-window-received').removeClass("strict-hide");
                    setTimeout(startTimerForTicker, 5000);
                }else{
                    setTimeout(startTimerForTicker, 5000);
                }
            }
        });
//        setTimeout(startTimerForTicker, 5000);
    }
    
    if(jQuery("#crypto-timer").is(":visible")){
        document.getElementById('crypto-timer').innerHTML =
        00 + ":" + 10;
        startTimer2();
    }
    
    function startTimer2() {
      var presentTime = document.getElementById('crypto-timer').innerHTML;
      var timeArray = presentTime.split(/[:]+/);
      var m = timeArray[0];
      var s = checkSecond((timeArray[1] - 1));
      if(s==59){m=m-1}
      if(s==0 && m==0){
           var cryptostring = "",cryptoamount = "";
            jQuery('.cryptocurrency_loop').each(function(k) {
                  var crypto = jQuery(this).data('crypto');
                  var amount = jQuery(this).data('amount');
                  cryptostring += crypto+",";
                  cryptoamount += amount+",";
            });
          jQuery.ajax({
            type: 'POST',
            url: PayscriptAjax.ajaxurl,
            data: {"action":"payscript_refresh_amount","authNonce":PayscriptAjax.authNonce,"cryptostring":cryptostring,"cryptoamount":cryptoamount,"order_id":jQuery('.cryptocurrency_loop').data('order_id')},
            dataType: 'json',
            success: function(data) { 
                    jQuery.each(data, function( index, value ) {
                        jQuery('#cryptotag_'+value.symbol).html(value.amount);
                        jQuery('#cryptotag_'+value.symbol).attr('data-amount',value.amount);
                    });
            }
        });
document.getElementById('crypto-timer').innerHTML =
        00 + ":" + 10;
         setTimeout(startTimer2, 1000);
          return false;
      }
      document.getElementById('crypto-timer').innerHTML =
        m + ":" + s;
      setTimeout(startTimer2, 1000);
    }
});