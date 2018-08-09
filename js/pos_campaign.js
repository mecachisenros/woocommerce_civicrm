jQuery( document ).ready(function(){
  var checkExist = setInterval(function() { // loop until POS is loaded to add the campaign list
     if (jQuery("#page.two-column #main").length) {
        addCampaignList();
        clearInterval(checkExist);
     }
  }, 100); // check every 100ms
});

/*
*
* Add campaign list to the top of the page
*
*/

function addCampaignList(){

  var data = {
		'action': 'get_campaign',
    'nonce' : POS.options.nonce
	};

	jQuery.post(POS.options.ajaxurl, data, function(response) { // Ajax to get the campaign list back
		jQuery("#page.two-column #main").prepend(response);
    changeCampaign(); // set the campaign list for this user
    $( "#order_civicrmcampaign").change(function(){
      changeCampaign();
    });

	});
}

/*
*
* Send the selected campaign to AJAX set_campaign to save it to the user meta
*
*/
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
