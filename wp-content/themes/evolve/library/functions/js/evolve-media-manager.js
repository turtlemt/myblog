(function($) {
 
// Object for creating WordPress 3.5 media upload menu
// for selecting theme images.
wp.media.evolveMediaManager = {
     
    init: function() {
        // Create the media frame.
        this.frame = wp.media.frames.evolveMediaManager = wp.media({
            title: 'Choose Image',
            library: {
                type: 'image'
            },
            button: {
                text: 'Insert into field',
            }
        });
 
         // When an image is selected, run a callback.
		this.frame.on( 'select', function() {
		    // Grab the selected attachment.
		    var attachment = wp.media.evolveMediaManager.frame.state().get('selection').first(),
		    controllerName = wp.media.evolveMediaManager.$el.data('controller');
		     
		    controller = wp.customize.control.instance(controllerName);//evolve-theme_evl_header_logo
		    controller.thumbnailSrc(attachment.attributes.url);
		    controller.setting.set(attachment.attributes.url);
		});

        $('.choose-from-library-link').click( function( event ) {
            wp.media.evolveMediaManager.$el = $(this);
            var controllerName = $(this).data('controller');
            event.preventDefault();
 
            wp.media.evolveMediaManager.frame.open();
        });
         
    } // end init
}; // end evolveMediaManager
 
wp.media.evolveMediaManager.init();
 
}(jQuery));