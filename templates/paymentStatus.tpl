<section class="woocommerce-order-payment-status ps-fnp-status-section">
   <div class="ps-fnp-text-right ps-fnp-smallest ps-fnp-sponsor">Powered by Payscript</div>
   <div class="payment-window">
      <div class="row ps-processsec__row ps-fnp-data" data-transaction_id="{$transactionID}" data-order_id="{$orderID}">
         <div class="row ps-processsec__row">
            <div class="col-md-12 col-sm-12 col-lg-12 ps-fnp-text-center ps-fnp-timer-div">
               <div class="ps-fnp-timer" id="fnp-timer">{$timeStart}</div>
               <span class="ps-remaining_time_txt"> Remaining Time</span>
            </div>
         </div>
         <div class="row ps-processsec__row ps-process__sec_address">
            <div class="col-md-12 col-sm-12 col-lg-12">
               <div class="ps-currency__details">
                  <span class="ps-fnp-inline-block ps-fnp-currency-icon">
                     <div><img src="{$pluginURL}assets/images/{$smallSymbol}.svg"></div>
                  </span>
                  <div class="ps-currency__details__div">
                     <div class="ps-symbolamount"> <span class="ps-btcsymboltxt"> {$symbol} {$btcAmount} </span> <span class="ps-amount_AUD">(A${$orderAmount} {$wooCurrency})</span> </div>
                     <div class="ps-ratesecdiv"> Rate Locked 1 {$symbol} : A${$btcRate} {$wooCurrency} </div>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <div class="row ps-processsec__row">
         <div class="col-md-12 col-sm-12 col-lg-12 ps-fnp-text-center ps-fnp-input-div">
            <label class="ps-fp-txt-address">Address :</label>
            <div class="pos__input">
               <input id="payscript-address" class="ps-fnp-input ps-fp-address" type="text" value="{$btcAddress}" readonly>
               <div class="ps-copy-img"><a href="javascript://" class="clip-copy"><img src="{$pluginURL}assets/images/copy1.png"></a></div>
            </div>
         </div>
      </div>

      <div class="row ps-processsec__row">
         <div class="col-md-12 col-sm-12 col-lg-12 ps-fnp-text-center ps-fnp-input-div">
            <label class="ps-fp-txt-address">Amount : <br/> (Please send exact amount with decimals) </label>
            <div class="pos__input">
               <input class="ps-fnp-input ps-fp-address" type="text" value="{$btcAmount}" readonly>
               <div class="ps-copy-img"><a href="javascript://" class="clip-copy"><img src="{$pluginURL}assets/images/copy1.png"></a></div>
            </div>
         </div>
      </div>
      {$note}

      <div class="row ps-processsec__row">
         <div class="col-md-12 col-sm-12 col-lg-12 ps-fnp-text-center ps-qrcode">
            <div class="ps-fnp-inline-block ps-fini-final-amout">
               You are sending <br/>
               <span class="bluetxt"> {$symbol} {$btcAmount} </span> 
               <span class="ps-amount_AUD"> (A${$orderAmount}) </span>
            </div>
            <div>
               <div id="payscript-qrcode"></div>
            </div>
         </div>
      </div>
   </div>
   <div class="payment-window-timeout strict-hide">
      <div class="pos__payment-timout">
         <img src="{$pluginURL}assets/images/timeout.svg"/>
         <div class="pos__text-section">
            <span class="text-1">
               Time Out for
               <span class="text-1--block">Amount Transaction</span>
            </span>
            <p class="text-2 mb-0">
               Please reinitiate the amount paid!
               <span class="text-2--block"></span>
            </p>
         </div>
      </div>
   </div>
   <div class="payment-window-received strict-hide">
      <div class="pos__payment-detected">
        <img src="{$pluginURL}assets/images/paymentdetected.svg"/>
         <div class="pos__text-section">
            <span class="text-1">
                  Payment Dectected.
                  <span class="text-1--block">Waiting for Confirmation</span>
            </span>
            <p class="text-2 mb-0">
                  You've successfully sent a payment,
                  <span class="text-2--block">
                     Your order will be processed soon.
                  </span>
            </p>
         </div>
      </div>
   </div>
</section>