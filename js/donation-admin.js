jQuery( document ).ready(function(){
  var el = jQuery("#_nyp");

  el.change(function() {
    hide_show_nyp(this);
  });
  if(el.is(':checked')) {
    jQuery(".options_group.donation_fields").show();
  }else {
    jQuery(".options_group.donation_fields").hide();
  }
});

function hide_show_nyp(el){

  if(el.checked) {
    jQuery(".options_group.donation_fields").show();
  }else {
    jQuery(".options_group.donation_fields").hide();
  }
}
