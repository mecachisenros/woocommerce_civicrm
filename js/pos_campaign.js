jQuery( document ).ready(function(){
  var checkExist = setInterval(function() {
     if (jQuery(".cart-customer").length) {
        addCampaignList();
        clearInterval(checkExist);
     }
  }, 100); // check every 100ms
});

function addCampaignList(){

//  POS.options.nonce
  var data = {
		'action': 'get_campaign',
	};

	jQuery.post(POS.options.ajaxurl, data, function(response) {
		$( ".cart-customer" ).after(response );
	});
}
