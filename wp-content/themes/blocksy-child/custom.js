jQuery(function ($) {
    let timer; 
    $('.donation-popup, .popup-overlay').hide();
    $('.ct-header-cta').on('click', function(e) {
        e.preventDefault();
        $('.popup-overlay').css('display', 'block');
        $('.donation-popup').css('display', 'block');
        let countdown = 5;
        $('#timer-text').show().text("Redirecting in " + countdown + " seconds...");
        clearInterval(timer);
        timer = setInterval(function() {
            countdown--;
            $('#timer-text').text("Redirecting in " + countdown + " seconds...");
            if (countdown <= 0) {
                $('.donation-popup').css('display', 'block');
                $('.popup-overlay').css('display', 'block');
                clearInterval(timer);
                window.location.href = "https://endhitting.org/donate";
            }
        }, 1000);

    });

    $('.popup-overlay, .donation-popup .close-btn').on('click', function() {
        $('.donation-popup, .popup-overlay').fadeOut();
        clearInterval(timer);
        $('#timer-text').hide();
    });
});
window.addEventListener('pageshow', function (e) {
  if (e.persisted) {
    document.querySelectorAll('.your-popup-class').forEach(p => {
      p.style.display = 'none';
    });
    location.reload();
  }
});


