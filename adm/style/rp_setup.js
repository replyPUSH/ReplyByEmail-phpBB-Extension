jQuery(document).ready(function(){
	$('.rp_dismiss').click(function(e){
		e.preventDefault();
		$.get($('.rp_dismiss').attr('href'), function(data) {
			if(data=='OK'){
				$('.rp_setup').remove();
			}
		});
	});
	
});
