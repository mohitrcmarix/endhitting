<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class SwatchesFrontend {
	public function render_variation_swatches($args) {
		$conf = new SwatchesConfig();

		$product = $args['product'];
		$swatches_html = [];

		foreach ($product->get_variation_attributes() as $attribute_name => $options) {
			$type = $conf->get_attribute_type($attribute_name);

			$html_attr = [
				'class' => 'ct-variation-swatches',
				'data-swatches-type' => $type,
				'data-attr' => $attribute_name
			];

			if ($type === 'color') {
				$html_attr['data-swatches-shape'] = blocksy_get_theme_mod(
					'color_swatch_shape',
					'round'
				);
			}

			if ($type === 'image') {
				$html_attr['data-swatches-shape'] = blocksy_get_theme_mod(
					'image_swatch_shape',
					'round'
				);
			}

			if ($type === 'button') {
				$html_attr['data-swatches-shape'] = blocksy_get_theme_mod(
					'button_swatch_shape',
					'round'
				);
			}

			ob_start();
			wc_dropdown_variation_attribute_options([
				'options' => $options,
				'attribute' => $attribute_name,
				'product' => $product
			]);
			$content = ob_get_clean();

			if ($type !== 'select') {
				$content .= $this->get_swatch_html(
					array_merge(
						$args,
						[
							'options' => $options,
							'attribute' => $attribute_name,
							'product' => $product
						]
					)
				);
			}

			$swatches_html[] = blocksy_html_tag(
				'div',
				$html_attr,
				$content
			);
		}

		$swatches_html = implode('', $swatches_html);
		$attr = [
			'class' => 'ct-card-variation-swatches'
		];

		$swatches_html = blocksy_html_tag(
			'div',
			['class' => 'variations'],
			$swatches_html
		);

		$json = blc_get_ext('woocommerce-extra')
			->utils
			->get_available_variations($product->get_id());

		$simple = new \WC_Product_Simple($product->get_id());

		$attr['data-product_variations'] = "false";
		$attr['data-product_id'] = $product->get_id();

		$get_variations = count($json) <= apply_filters(
			'woocommerce_ajax_variation_threshold',
			30,
			$product
		);

		if ($get_variations) {
			$attr['data-product_variations'] = wc_esc_json(wp_json_encode($json));
		}

		$attr['data-dynamic-card-data'] = wc_esc_json(wp_json_encode([
			'variable' => [
				'text' => $product->add_to_cart_text(),
				'link' => $product->add_to_cart_url(),
				'price' => '<span class="price">' . $product->get_price_html() . '</span>',
			],

			'simple' => [
				'text' => $simple->add_to_cart_text(),
				'link' => $simple->add_to_cart_url()
			]
		]));

		return blocksy_html_tag('div', $attr, $swatches_html);
	}

	public function is_selected($term_slug, $term_attribute, $product) {
		$maybe_current_variation = null;

		if (function_exists('blocksy_retrieve_product_default_variation')) {
			$maybe_current_variation = blocksy_retrieve_product_default_variation(
				$product
			);
		}

		$attributes = $product->get_attributes();

		if ($maybe_current_variation) {
			$attributes = $maybe_current_variation->get_attributes();
		}

		$is_selected = false;

		if (isset($attributes[$term_attribute])) {
			$is_selected = $attributes[$term_attribute] === $term_slug;

			if ($product) {
				$is_selected = $term_slug === $product->get_variation_default_attribute(sanitize_title($term_attribute));
			}
		}

		$selected_key = 'attribute_' . sanitize_title($term_attribute);

		if (isset($_REQUEST[$selected_key])) {
			$is_selected = wc_clean(
				wp_unslash(
					strtolower(
						$_REQUEST[$selected_key]
					)
				)
			) === strtolower($term_slug);
		}

		return $is_selected;
	}

	public function count_valid_variation($term_slug, $term_attribute, $product) {
		$default_attiributes = $product->get_default_attributes();
		$all_variations = blc_get_ext('woocommerce-extra')
			->utils
			->get_available_variations($product->get_id());

		if (
			! empty($default_attiributes)
			&&
			! isset($default_attiributes[sanitize_title($term_attribute)])
		) {
			$all_variations = array_filter(
				$all_variations,
				function ($variation) use ($default_attiributes) {
					foreach ($default_attiributes as $key => $value) {
						if ($variation['attributes']['attribute_' . sanitize_title($key)] !== $value) {
							return false;
						}
					}

					return true;
				}
			);
		}

		$all_variations = array_filter(
			$all_variations,
			function ($variation) use ($term_slug, $term_attribute) {
				return $variation['attributes']['attribute_' . sanitize_title($term_attribute)] === $term_slug;
			}
		);

		$valid_variations = array_filter(
			$all_variations,
			function ($variation) {
				return $variation['is_in_stock'];
			}
		);

		return [
			'valid' => count($valid_variations),
			'total' => count($all_variations),
		];
	}

	public function get_swatch_html($args) {
		if (
			(
				is_admin()
				&&
				! defined('DOING_AJAX')
			)
			||
			empty($args['options'])
			||
			! $args['product']
		) {
			return '';
		}

		global $product;
		$result = '';

		if (! taxonomy_exists($args['attribute'])) {
			$options = $args['options'];

			$custom_attribute_slug = null;

			foreach ($args['product']->get_attributes() as $key => $value) {
				if ($value->get_name() !== $args['attribute']) {
					continue;
				}

				$custom_attribute_slug = $key;
			}

			if (! $custom_attribute_slug) {
				return '';
			}

			foreach ($options as $term) {
				$is_selected = $this->is_selected($term, $custom_attribute_slug, $args['product']);
				$valid_variation = $this->count_valid_variation(
					$term,
					$custom_attribute_slug,
					$args['product']
				);

				if ($valid_variation['total'] === 0) {
					continue;
				}

				$is_valid = $valid_variation['valid'] > 0;

				$result .= blocksy_html_tag(
					'div',
					[
						'class' => 'ct-swatch-container' . ($is_selected ? ' active' : '') . ($is_valid ? '' : ' ct-out-of-stock'),
						'data-value' => $term
					],
					blocksy_html_tag(
						'span',
						['class' => 'ct-tooltip'],
						$term . ($is_valid ? '' : ' - ' . __('Out of Stock', 'blocksy-companion'))
					) .
					blocksy_html_tag(
						'span',
						['class' => 'ct-swatch'],
						$term
					)
				);
			}

			return $result;
		}

		$terms = wc_get_product_terms(
			$args['product']->get_id(),
			$args['attribute'],
			['fields' => 'all']
		);

		foreach ($terms as $term) {
			if (! in_array($term->slug, $args['options'])) {
				continue;
			}

			$is_selected = $this->is_selected($term->slug, $term->taxonomy, $args['product']);

			$valid_variations = $this->count_valid_variation(
				$term->slug,
				$term->taxonomy,
				$args['product']
			);

			if ($valid_variations['total'] === 0) {
				continue;
			}

			$swatch_term = new SwatchesRender($term->term_id, [
				'is_selected' => $is_selected,
				'is_valid' => $valid_variations['valid'] > 0,
			]);

			$result .= $swatch_term->get_output();
		}

		return $result;
	}
}

