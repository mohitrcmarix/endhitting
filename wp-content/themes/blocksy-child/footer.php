<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package Blocksy
 */

blocksy_after_current_template();
do_action('blocksy:content:bottom');

?>
	</main>

	<?php
		do_action('blocksy:content:after');
		do_action('blocksy:footer:before');

		blocksy_output_footer();

		do_action('blocksy:footer:after');
	?>
</div>

<div class="popup-overlay" id="open"></div>
    <div class="donation-popup">
    <span class="close-btn" id="close">&times;</span>
    <p>Thank you for supporting Arkansans Against School Paddling!</p>
    <p>We are the official Arkansas chapter of the U.S. Alliance to End the Hitting of Children - a registered 501(c)(3).</p>
    <p>To make sure your donation is tax-deductible and goes directly toward supporting our advocacy and education efforts in Arkansas, donations are processed through the U.S. Alliance to End the Hitting of Children.</p>
    <p>You will now be redirected to <a href="https://endhitting.org/donate/">endhitting.org</a> to complete your donation.</p>
    </p>
    <p id="timer-text" style="display:none; font-weight:bold;"></p>
</div>

<?php wp_footer(); ?>

</body>
</html>
