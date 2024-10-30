jQuery( document ).ready(function() {
jQuery('#checkp').click(function (e) {
	e.preventDefault();
	jQuery('#msg').html('Loading...');
	var pincode = jQuery('#pincode').val();
	console.log(pincode);
	var data = { 
			'action': 'cpincode',
			'pincode': pincode
		};
	
		jQuery.ajax({

          url: ajaxurl, // this is the object instantiated in wp_localize_script function

          type: 'POST',

          data:data,

          success: function( data ){
		
			jQuery('#msg').html(data);
			//alert(response);
		  }
		});
	
})    


jQuery("input[name='update_cart']").click(function() {
     setTimeout(function() {
         window.location.reload();
      }, 5000);
   });  
});      