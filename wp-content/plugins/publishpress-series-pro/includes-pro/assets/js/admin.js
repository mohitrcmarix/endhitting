(function($) {
  'use strict'

  /**
   * All of the code for your admin-facing JavaScript source
   * should reside in this file.
   *
   * Note: It has been assumed you will write jQuery code here, so the
   * $ function reference has been prepared for usage within the scope
   * of this function.
   *
   * This enables you to define handlers, for when the DOM is ready:
   *
   * $(function() {
   *
   * });
   *
   * When the window is loaded:
   *
   * $( window ).load(function() {
   *
   * });
   *
   * ...and/or other possibilities.
   *
   * Ideally, it is not considered best practise to attach more than a
   * single DOM-ready or window-load handler for a particular page.
   * Although scripts in the WordPress core, Plugins and Themes may be
   * practising this, we should strive to set a better example in our own work.
   */

  $(document).ready(function() {

    // -------------------------------------------------------------
    //   Activate licence
    // -------------------------------------------------------------
    $(document).on('click', '.ppseries-activate-license', function (e) {
        e.preventDefault();
        $(".ppseries-spinner").addClass("is-active");
        $(this).attr('disabled', true);
        var data = {
            'action': 'ppseries_pro_activate_license_by_ajax',
            'licence_key': $('#ppseries_licence_key_input').val(),
            'licence_action': $(this).attr('data-action'),
            'security': ppseries_pro.check_nonce,
        };
        $.post(ppseries_pro.ajaxurl, data, function (response) {
          location.reload();
        });

    });

  })

})(jQuery)
