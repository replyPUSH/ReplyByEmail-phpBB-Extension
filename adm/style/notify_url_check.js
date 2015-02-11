jQuery(document).ready(function(){
    $('.reply_push_uri').each(function(){
        var that = this;
        var ok  = false;
        $.get($(that).val().replace(/\/([a-z0-9]+)$/,'/ping/$1'), function(data){
            if(data=='OK'){
                $(that).after('<span class="reply_push_uri_check reply_push_uri_found"></span>');
                ok = true;
            }
                
        }).always(function() {
            if(!ok)
                $(that).after('<span class="reply_push_uri_check reply_push_uri_not_found"></span>');
        });
    });
    
});
