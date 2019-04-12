jQuery( document ).ready(function(){
  var checkExist = setInterval(function() { // loop until POS is loaded to add the campaign list
     if (jQuery("#page.two-column #main").length) {
        addCampaignList();
        if($_GET('user_id')){
          pre_select_user($_GET('user_id'));
        }

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
		'action': 'woocommerce_civicrm_get_datas_for_pos',
    'nonce' : POS.options.nonce
	};

	jQuery.post(POS.options.ajaxurl, data, function(response) { // Ajax to get the campaign list back
		jQuery("#page.two-column #main").prepend(response);
    changeCampaign(); // set the campaign list for this user
    $( "#order_civicrmcampaign, #order_civicrmsource").change(function(){
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
  source = $( "#order_civicrmsource").val();

  var data = {
		'action': 'woocommerce_civicrm_set_datas_from_pos',
    'campaign_id' :campaign_id,
    'source' :source,
    'nonce' : POS.options.nonce
	};

	jQuery.post(POS.options.ajaxurl, data, function(response) {

	});
}

/*
*
* Returns the URL parameters
*
*/
function pre_select_user(customer_id) {
  //saveCustomer( view.model.toJSON()

  var data = {
    'security' : POS.options.nonce,

	};
  if(POS.posApp.orders.db.parent.models[0].attributes.customer_id != customer_id){
    $.ajax({
      url: POS.options.wc_api+'customers/'+customer_id,
      type: 'GET',
      beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', POS.options.rest_nonce);
      },
      data: data,
      success: function (response) {

        var db;
        var request = indexedDB.open("wc_pos_orders");
        request.onerror = function(event) {
          alert("Pourquoi ne permettez-vous pas à ma web app d'utiliser IndexedDB?!");
        };
        request.onsuccess = function(event) {
          db = event.target.result;
          var objectStore = db.transaction(["orders"], "readwrite").objectStore("orders");
          var request = objectStore.get(POS.posApp.orders.db.parent.models[0].attributes.local_id);
          request.onerror = function(event) {
            // Gestion des erreurs!
          };
          request.onsuccess = function(event) {
            // On récupère l'ancienne valeur que nous souhaitons mettre à jour
            var data = request.result;
            // On met à jour ce(s) valeur(s) dans l'objet
            data.customer_id = response.id;
            data.customer = response;

            // Et on remet cet objet à jour dans la base
            var requestUpdate = objectStore.put(data);
             requestUpdate.onerror = function(event) {
               // Faire quelque chose avec l’erreur
             };
             requestUpdate.onsuccess = function(event) {
               // Succès - la donnée est mise à jour !
             };
          };
        };
        document.location.reload(true);
      },

      error: function () { },
    });
  }

}
/*
*
* Returns the URL parameters
*
*/
function $_GET(param) {
	var vars = {};
	window.location.href.replace( location.hash, '' ).replace(
		/[?&]+([^=&]+)=?([^&]*)?/gi, // regexp
		function( m, key, value ) { // callback
			vars[key] = value !== undefined ? value : '';
		}
	);

	if ( param ) {
		return vars[param] ? vars[param] : null;
	}
	return vars;
}
