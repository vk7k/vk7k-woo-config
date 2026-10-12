/**
 * WooConfig Wholesale Lightbox Interactivity
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Open modal
        $(document).on('click', '[data-action="open-wholesale-modal"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $('#wooconfig-wholesale-modal').fadeIn(200);
            $('body').addClass('wooconfig-modal-open');
        });

        // Close modal via close button or OK button
        $(document).on('click', '#wooconfig-wholesale-close-btn, #wooconfig-wholesale-btn-ok', function(e) {
            e.preventDefault();
            $('#wooconfig-wholesale-modal').fadeOut(150);
            $('body').removeClass('wooconfig-modal-open');
        });

        // Close modal when clicking outside box
        $(document).on('click', '#wooconfig-wholesale-modal', function(e) {
            if ($(e.target).is('#wooconfig-wholesale-modal')) {
                $('#wooconfig-wholesale-modal').fadeOut(150);
                $('body').removeClass('wooconfig-modal-open');
            }
        });

        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#wooconfig-wholesale-modal').is(':visible')) {
                $('#wooconfig-wholesale-modal').fadeOut(150);
                $('body').removeClass('wooconfig-modal-open');
            }
        });
    });
})(jQuery);
