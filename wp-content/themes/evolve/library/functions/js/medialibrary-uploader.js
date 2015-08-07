jQuery(document).ready(function($){
var evolve_upload;
var evolve_selector;
function evolve_add_file(event, selector) {
var upload = $(".uploaded-file"), frame;
var $el = $(this);
evolve_selector = selector;
event.preventDefault();
// Create the media frame.
evolve_upload = wp.media.frames.evolve_upload = wp.media({
title: $el.data('choose'),
button: {
text: $el.data('update'),
close: false
}
});
// When an image is selected, run a callback.
evolve_upload.on( 'select', function() {
    
// Grab the selected attachment.
var attachment = evolve_upload.state().get('selection').first();
evolve_upload.close();
evolve_selector.find('.upload').val(attachment.attributes.url);
if ( attachment.attributes.type == 'image' ) {
evolve_selector.find('.screenshot').empty().hide().append('<img src="' + attachment.attributes.url + '">').slideDown('fast');
}
selector.find('.button').val(evolveframework_l10n.remove);
evolve_selector.find('.of-background-properties').slideDown();

});

// Finally, open the modal.
evolve_upload.open();
}

function evolve_remove_file(selector) {
    selector.find('.upload').val('');
    selector.find('.of-background-properties').hide();
    selector.find('.screenshot').slideUp();
    selector.find('.button').val(evolveframework_l10n.upload);
// We don't display the upload button if .upload-notice is present
// This means the user doesn't have the WordPress 3.5 Media Library Support
if ( $('.section-upload .upload-notice').length > 0 ) {
$('.upload-button').remove();
}
//selector.find('.upload-button').show();
}
$('.remove-image, .remove-file, .upload-button').on('click', function(event) {
    var parent_section_id=$(this).attr('id');
    parent_section_id=parent_section_id.replace("remove","section").replace("upload","section"); ;
    if($(this).val()==evolveframework_l10n.remove)
     evolve_remove_file( $("#"+parent_section_id) );
    else
     evolve_add_file(event, $("#"+parent_section_id));
});

});