<?php

$image_size = blocksy_get_theme_mod('wishlist_image_size', 'woocommerce_thumbnail');
$image_ratio = blocksy_get_theme_mod('wishlist_image_ratio', '1/1');

if (! function_exists('blc_render_wishlist_table_actions')) {
	function blc_render_wishlist_table_actions($product, $maybeVariations, $is_mobile = false) {
		global $has_wish_list;
		$has_wish_list = true;

		$maybeVariationsAttrs = $product->get_attributes();

		if (
			isset($maybeVariations['attributes'])
			&&
			! empty($maybeVariations['attributes'])
		) {
			$maybeVariationsAttrs = array_merge(
				$maybeVariationsAttrs,
				$maybeVariations['attributes']
			);
		}

		$product->set_attributes($maybeVariationsAttrs);

		$is_simple_product = blc_get_ext('woocommerce-extra')
						->utils
						->is_simple_product($product);

		if (
			$is_simple_product['value']
			&&
			! $is_mobile
		) {
			do_action('woocommerce_simple_add_to_cart');
		} else {
			woocommerce_template_loop_add_to_cart();
		}

		$has_wish_list = false;
	}
}

$wish_list = blc_get_ext('woocommerce-extra')->get_wish_list()->get_current_wish_list();

$has_custom_user = isset($_GET['wish_list_id']);

add_filter('wsa_sample_should_add_button', '__return_false');

if (class_exists('EPOFW_Front')) {
	$instance = EPOFW_Front::instance();

	remove_action(
		'woocommerce_before_add_to_cart_button',
		array(
			$instance,
			'epofw_before_add_to_cart_button',
		),
		10
	);

	remove_action(
		'woocommerce_after_add_to_cart_button',
		array($instance, 'epofw_after_add_to_cart_button'),
		10
	);
}

if (count($wish_list) > 0) {

?>
	<div class="ct-woocommerce-wishlist-table">
		<table class="shop_table">
			<thead>
				<tr>
					<th colspan="2"><?php esc_html_e( 'Product', 'blocksy-companion' ); ?></th>
					<th class="wishlist-product-actions"><?php esc_html_e( 'Add to cart', 'blocksy-companion' ); ?></th>
					<?php if (! $has_custom_user) { ?>
					<th class="wishlist-product-remove">&nbsp;</th>
					<?php } ?>
				</tr>
			</thead>

			<tbody>
				<?php

				foreach ($wish_list as $single_product) {

					$single_product_id = null;

					if (
						isset($single_product['id'])
						&&
						is_numeric($single_product['id'])
					) {
						$single_product_id = $single_product['id'];
					} elseif (is_numeric($single_product)) {
						$single_product_id = $single_product;
					}

					if (! $single_product_id) {
						continue;
					}

					$product = wc_get_product($single_product_id);

					$status = $product->get_status();

					if ( $status === 'trash' ) {
						continue;
					}

					if (
						$status === 'private'
						&&
						! current_user_can( 'read_private_products' )
					) {
						continue;
					}

					$maybeVariations = null;

					$is_simple_product = blc_get_ext('woocommerce-extra')
						->utils
						->is_simple_product($product);

					if (isset($is_simple_product['fake_type'])) {
						$product_classname = WC()
							->product_factory
							->get_product_classname(
								$single_product_id, 'variable'
							);

						try {
							$product = new $product_classname($single_product_id);
						} catch (Exception $e) {
						}
					}

					$GLOBALS['product'] = $product;

					if ($product && $product->exists()) {
						$product_permalink = $product->is_visible() ? $product->get_permalink() : '';

						if (
							$product->is_type( 'variation' )
							||
							(
								$product->is_type( 'variable' )
								&&
								blocksy_retrieve_product_default_variation(
									$product
								)
							)
						) {

							$maybeVariations = $single_product;

							if (isset($maybeVariations['attributes'])) {
								$product_permalink = esc_url(
									add_query_arg(
										$maybeVariations['attributes'],
										$product_permalink
									)
								);
							}
						}

						$class = '';

						if (
							! $product->is_type('grouped')
							&&
							! $product->is_type('external')
						) {
							$class .= 'class="ct-ajax-add-to-cart"';
						}

						$remove_button = '';

						if (function_exists('blocksy_action_button')) {
							$remove_button = blocksy_action_button(
								[
									'button_html_attributes' => [
										'href' => '#wishlist-remove-' . $single_product_id,
										'class' => 'remove',
										'data-id' => $single_product_id,
										'title' => __('Remove Product', 'blocksy-companion')
									],
									'icon' => '<svg viewBox="0 0 24 24"><path d="M9.6,0l0,1.2H1.2v2.4h21.6V1.2h-8.4l0-1.2H9.6z M2.8,6l1.8,15.9C4.8,23.1,5.9,24,7.1,24h9.9c1.2,0,2.2-0.9,2.4-2.1L21.2,6H2.8z"></path></svg>'
								]
							);
						}
						?>
							<tr <?php echo $class ?>>
								<td class="wishlist-product-thumbnail">
									<?php
										echo blocksy_media([
											'no_image_type' => 'woo',
											'attachment_id' => $product->get_image_id(),
											'post_id' => $product->get_id(),
											'size' => $image_size,
											'ratio' => $image_ratio,
											'tag_name' => 'a',
											'class' => 'product-thumb',
											'html_atts' => [
												'href' => esc_url($product->get_permalink()),
											],
										])
									?>
								</td>

								<td class="wishlist-product-name">
									<?php
										$product_name = blc_get_ext(
											'woocommerce-extra'
										)->utils->get_formatted_title($product->get_id());

										if ($product->is_type('variation')) {
											$parent_product = wc_get_product($product->get_parent_id());

											if ($parent_product) {
												$product_name = blc_get_ext(
													'woocommerce-extra'
												)->utils->get_formatted_title($product->get_id());
											}
										}

										if (! $product_permalink) {
											echo wp_kses_post($product_name);
										} else {
											echo wp_kses_post(blc_safe_sprintf(
												'<a href="%s" class="product-name">%s</a>',
												esc_url($product_permalink),
												$product_name
											));
										}
									?>

										<?php
											if (
												$product->is_type( 'variation' )
											) {
												$withDefaultVariation = $product->is_type( 'variable' ) && blocksy_retrieve_product_default_variation($product) ? 'yes' : 'no';

												$maybeVariationsAttrs = $product->get_attributes();

												if (
													$product->is_type( 'variable' )
													&&
													blocksy_retrieve_product_default_variation(
														$product
													)
												) {
													$defaultVar = blocksy_retrieve_product_default_variation(
														$product
													);

													$maybeVariationsAttrs = $defaultVar->get_attributes();
												}

												if (isset($maybeVariations['attributes']) && !empty($maybeVariations['attributes'])) {
													$maybeVariationsAttrs = array_merge(
														$maybeVariationsAttrs,
														$maybeVariations['attributes']
													);
												}

												$attributes_html = [];

												foreach ($maybeVariationsAttrs as $key => $value) {
													$attribute_slug = str_replace('attribute_', '', sanitize_title($key));
													$attribute_label = wc_attribute_label( $attribute_slug );
													$term = get_term_by( 'slug', $value, $attribute_slug);

													$attribute_name = $value;
													$attribute_value = $value;

													if ( $term && ! is_wp_error( $term )  ) {
														$attribute_name = $term->name;
														$attribute_value = $term->slug;
													}

													if (
														$value
													) {
														$attributes_html[] = blocksy_html_tag(
															'dt',
															[
																'data-attribute-slug' => $attribute_slug,
																'data-attribute-val' => $attribute_value
															],
															$attribute_label . ':'
														);

														$attributes_html[] = blocksy_html_tag(
															'dd',
															[],
															$attribute_name
														);
													}
												}


												echo blocksy_html_tag(
													'dl',
													[
														'class' => 'variation',
														'data-default' => $withDefaultVariation
													],
													implode('', $attributes_html)
												);
											} ?>

									<?php
										$GLOBALS['product'] = wc_get_product($single_product_id);
										woocommerce_template_single_price();
										$GLOBALS['product'] = $product;
									?>

									<div class="product-mobile-actions ct-hidden-lg">
										<?php
											blc_render_wishlist_table_actions($product, $maybeVariations, $is_mobile = true);
										?>

										<?php if (! $has_custom_user) {
											echo $remove_button;
										} ?>
									</div>
								</td>

								<td class="wishlist-product-actions">
									<?php
										blc_render_wishlist_table_actions($product, $maybeVariations);
									?>
								</td>

								<?php if (! $has_custom_user) { ?>
									<td class="wishlist-product-remove">
										<?php
											echo $remove_button;
										?>
									</td>
								<?php } ?>
							</tr>
						<?php
					}
				}
				?>
			</tbody>
		</table>
	</div>

<?php
	if (
		blocksy_get_theme_mod('product_wishlist_display_for', 'logged_users') === 'all_users'
		&&
		blocksy_get_theme_mod('woocommerce_wish_list_page')
		&&
		is_user_logged_in()
		&&
		blocksy_get_theme_mod('wish_list_has_share_box', 'no') === 'yes'
	) {
		echo blocksy_get_social_share_box([
			'html_atts' => [
				'data-type' => 'type-3'
			],
			'links_wrapper_attr' => [
				'data-icons-type' => 'simple'
			],
			'custom_share_url' => add_query_arg(
				'wish_list_id',
				get_current_user_id(),
				get_permalink(blocksy_get_theme_mod('woocommerce_wish_list_page'))
			),
			'strategy' => [
				'strategy' => 'customizer',
				'prefix' => 'wish_list'
			]
		]);
	}

} else { ?>

	<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
		<a class="woocommerce-Button button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
			<?php echo __('Browse products', 'blocksy-companion') ?>
		</a>

		<?php echo __("You don't have any products in your wish list yet.", 'blocksy-companion') ?>
	</div>

<?php } ?>
