jQuery( document ).ready(function(){

  jQuery("#actual_price").val(jQuery(".group_choices input[name=price]:checked").val());
  changeParagraph(jQuery(".group_choices input[name=price]:checked").val());

  jQuery(".group_choices input[name=price] , .group_choices input.other_price").change(function(){
    somethingChanged();
  });

  jQuery(".group_choices input.other_price").keyup(function(){
    if(isNaN(jQuery(this).val())){
      jQuery(this).val(jQuery(this).val().replace(/\D/g,''));
    }
    somethingChanged();
  });

});


function somethingChanged(){

  $checked = jQuery(".group_choices input[name=price]:checked")
  $val = $checked.val();

  if(isNaN($val)){
    jQuery("#"+$val).removeAttr("disabled");
    jQuery("#actual_price").val(jQuery("#"+$val).val());

    changeParagraph(jQuery("#"+$val).val());

  }else {
    jQuery(".group_choices .other_price").attr("disabled","disabled");
    jQuery("#actual_price").val($val);


    changeParagraph($val);
  }

  if($checked.hasClass("recurring_choices")){
    jQuery("#is_recurring").val('true');
  }else{
    jQuery("#is_recurring").val('false');
  }
}

function changeParagraph($val){
  jQuery("#actual_price_calculation").html($val);

  jQuery("#after_tax_price_calculation").html($val*jQuery("#tax_return").val()/100);
}
