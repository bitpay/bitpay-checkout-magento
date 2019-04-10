
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
 
if(window.location.pathname == '/checkout/onepage/success/' && getCookie('flow') !== -1){
    $j(".main").css('opacity','0.3')
       showModal(getCookie('env'))

}
