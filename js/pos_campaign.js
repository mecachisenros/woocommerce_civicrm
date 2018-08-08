jQuery( document ).ready(function(){
  var checkExist = setInterval(function() {
     if (jQuery(".cart-customer").length) {
        addCampaignList();
        clearInterval(checkExist);
     }
  }, 100); // check every 100ms
});

function addCampaignList(){

  var data = {
		'action': 'get_campaign',
    'nonce' : POS.options.nonce
	};

	jQuery.post(POS.options.ajaxurl, data, function(response) {
		$( ".cart-customer" ).after(response );
    changeCampaign();
    $( "#order_civicrmcampaign").change(function(){
      console.log( $( "#order_civicrmcampaign").val());
      changeCampaign();
    });

	});
}
var campaign_id;
function changeCampaign(){
  campaign_id = $( "#order_civicrmcampaign").val();

  var data = {
		'action': 'set_campaign',
    'campaign_id' :campaign_id,
    'nonce' : POS.options.nonce
	};

	jQuery.post(POS.options.ajaxurl, data, function(response) {

	});
}
