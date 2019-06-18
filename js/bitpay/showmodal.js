
function showModal(env){
    jQuery("#bitpaybtn").text('Generating BitPay Invoice')
    
    setTimeout(function(){ 
        $j(".main").css('opacity','0.3')

    jQuery.post( "/showmodal/index/modal", function(data ) {
    jQuery("#bitpaybtn").prop("disabled",true)
       var is_paid = false
       console.log('data',data)
       var response = JSON.parse(data)
        window.addEventListener("message", function (event) {
            payment_status = event.data.status;
            if (payment_status == "paid") {
                is_paid = true
                window.location.href =response.redirectURL;
                return;
            } 
        }, false);
            
            //hide the order info
            bitpay.onModalWillEnter(function () {
                $j(".main").css('opacity','0.3')
            });
            //show the order info
            bitpay.onModalWillLeave(function () {
                if (is_paid == false) {
                  window.location.href = response.cartFix;
                } //endif
            });
            //show the modal
            if(env == 'test'){
            bitpay.enableTestMode()
            }
            setTimeout(function(){ bitpay.showInvoice(response.invoiceID); }, 10);
            
      });
    }, 1000);
}
function getCookie(name)
  {
    var re = new RegExp(name + "=([^;]+)");
    var value = re.exec(document.cookie);
    return (value != null) ? unescape(value[1]) : null;
  }
 
  function setCookie(cname, cvalue, exMins) {
    var d = new Date();
    d.setTime(d.getTime() + (exMins*60*1000));
    var expires = "expires="+d.toUTCString();  
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}

setCookie('cookieNameToDelete','',0) 
if(window.location.pathname == '/checkout/onepage/success/' && getCookie('flow') !== -1){
    $j(".main").css('opacity','0.3')
       showModal(getCookie('env'))

}

//autofill the guest info
if(window.location.pathname.indexOf('sales/guest/form') != -1){
  //autofill form
  setTimeout(function(){ 
    $j("#oar_order_id").val(getCookie("oar_order_id"))
    $j("#oar_billing_lastname").val(getCookie("oar_billing_lastname"))
    $j("#oar_email").val(getCookie("oar_email"))
    setCookie("oar_order_id",'',0)
    setCookie("oar_billing_lastname",'',0)
    setCookie("oar_email",'',0)
  }, 
    1500);
   
    
 

}
