<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class Swatches {
	public function __construct() {
		add_action(
			'wp_enqueue_scripts',
			function () {
				if (!function_exists('get_plugin_data')) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$data = get_plugin_data(BLOCKSY__FILE__);

				if (is_admin()) {
					return;
				}

				wp_enqueue_style(
					'blocksy-ext-woocommerce-extra-variation-swatches-styles',
					BLOCKSY_URL .
						'framework/premium/extensions/woocommerce-extra/static/bundle/variation-swatches.min.css',
					['blocksy-ext-woocommerce-extra-styles'],
					$data['Version']
				);
			},
			50
		);

		new SwatchesLoopVariableProduct();

		if (is_admin()) {
			new SwatchesPersistAttributes();
		}

		add_filter('blocksy:frontend:dynamic-js-chunks', function ($chunks) {
			if (! class_exists('WC_AJAX')) {
				return $chunks;
			}

			ob_start();
			wc_get_template('single-product/add-to-cart/variation.php');
			$raw_html = ob_get_clean();

			$chunks[] = [
				'id' => 'blocksy_ext_woo_extra_swatches',
				'selector' => '.product .ct-swatch-container,.product .ct-variation-swatches select:last-child',
				'trigger' => [
					[
						'trigger' => 'click',
						'selector' => '.product .ct-swatch-container',
					],

					[
						'trigger' => 'change',
						'selector' => '.product .ct-variation-swatches select:last-child',
					],

					[
						'trigger' => 'click',
						'selector' => '.product .reset_variations',
					]
				],
				'url' => blocksy_cdn_url(
					BLOCKSY_URL . 'framework/premium/extensions/woocommerce-extra/static/bundle/swatches.js'
				),
				'deps' => [
					'underscore',
					'wc-add-to-cart-variation',
					'wp-util'
				],
				'global_data' => [
					[
						'var' => 'wc_add_to_cart_variation_params',
						'data' => [
							'wc_ajax_url'                      => \WC_AJAX::get_endpoint('%%endpoint%%'),
							'i18n_no_matching_variations_text' => esc_attr__('Sorry, no products matched your selection. Please choose a different combination.', 'blocksy-companion'),
							'i18n_make_a_selection_text'       => esc_attr__('Please select some product options before adding this product to your cart.', 'blocksy-companion'),
							'i18n_unavailable_text'            => esc_attr__('Sorry, this product is unavailable. Please choose a different combination.', 'blocksy-companion'),
							'i18n_out_of_stock' 			   => esc_attr__('Out of Stock', 'blocksy-companion'),
						]
					]
				],
				'raw_html' => [
					'html' => $raw_html,
					'selector' => '#tmpl-variation-template'
				]
			];

			return $chunks;
		});

		add_filter(
			'blocksy_customizer_options:woocommerce:general:end',
			function ($opts) {
				$opts['has_variation_swatches_panel'] = blocksy_get_options(
					dirname(__FILE__) . '/options.php',
					[],
					false
				);

				return $opts;
			},
			55
		);

		add_filter(
			'blocksy_woo_card_options_layers:defaults',
			function ($defaults) {
				$defaults[] = [
					'id' => 'product_swatches',
					'enabled' => false
				];

				return $defaults;
			}
		);

		add_filter(
			'blocksy_woo_card_options_layers:extra',
			[$this, 'add_layer_options']
		);

		add_action('blocksy:woocommerce:product-card:custom:layer', [
			$this,
			'render_layer',
		]);

		add_filter(
			'woocommerce_dropdown_variation_attribute_options_html',
			function ($html, $args) {
				$has_single_product_swatches = apply_filters(
					'blocksy:pro:woocommerce-extra:swatches:has-single-product-swatches',
					true
				);

				if (! $has_single_product_swatches) {
					return $html;
				}

				global $blocksy_rendering_woo_card;

				if ($blocksy_rendering_woo_card) {
					return $html;
				}

				$conf = new SwatchesConfig();
				$type = $conf->get_attribute_type($args['attribute']);

				$attr = [
					'class' => 'ct-variation-swatches',
					'data-swatches-type' => $type
				];

				if ($type === 'color') {
					$attr['data-swatches-shape'] = blocksy_get_theme_mod('color_swatch_shape', 'round');
				}

				if ($type === 'image') {
					$attr['data-swatches-shape'] = blocksy_get_theme_mod('image_swatch_shape', 'round');
				}

				if ($type === 'button') {
					$attr['data-swatches-shape'] = blocksy_get_theme_mod('button_swatch_shape', 'round');
				}

				$custom_swatch_html = '';
				$renderer = new SwatchesFrontend();

				if ($type !== 'select') {
					$custom_swatch_html = $renderer->get_swatch_html($args);
				}

				return blocksy_html_tag('div', $attr, $html . $custom_swatch_html);
			},
			999, 2
		);

		add_filter(
			'woocommerce_attribute_label',
			function ($label, $name) {
				global $product;

				if (
					! $product
					||
					! $product instanceof \WC_Product
				) {
					return $label;
				}

				if ($product->get_type() !== 'variable') {
					return $label;
				}

				$default_attributes = $product->get_default_attributes();
				$maybe_value = '';

				$conf = new SwatchesConfig();
				$type = $conf->get_attribute_type($name);

				if ($type === 'select') {
					return $label;
				}

				if (isset($default_attributes[$name])) {
					if (taxonomy_exists($name)) {
						$term = get_term_by(
							'slug',
							$default_attributes[$name],
							$name
						);

						if (! $term) {
							return $label;
						}

						$maybe_value = $term->name;
					} else {
						$maybe_value = $default_attributes[$name];
					}
				} else {
					$attributes = $product->get_attributes();
					$attributes = array_map(function ($attr) {
						return $attr->get_name();
					}, $attributes);

					$maybe_custom_attribute = array_search($name, $attributes);

					if (
						$maybe_custom_attribute
						&&
						isset($default_attributes[$maybe_custom_attribute])
					) {
						$maybe_value = $default_attributes[$maybe_custom_attribute];
					}
				}

				if ($maybe_value) {
					return $label . ': ' . $maybe_value;
				}

				return $label;
			},
			10,
			3
		);

		add_filter(
			'woocommerce_post_class',
			function ($classes, $product) {

				global $blocksy_rendering_woo_card;

				if ($blocksy_rendering_woo_card) {
					return $classes;
				}

				$product_view_type = blocksy_get_theme_mod('product_view_type', 'default-gallery');
				if (
					$product_view_type === 'default-gallery'
					||
					$product_view_type === 'stacked-gallery'
				) {
					$default_product_layout = [];

					if ( function_exists('blocksy_get_woo_single_layout_defaults') ) {
						$default_product_layout = blocksy_get_woo_single_layout_defaults();
					}

					$woo_single_layout = blocksy_get_theme_mod(
						'woo_single_layout',
						$default_product_layout
					);
				} else {
					$woo_single_split_layout_defults = [
						'left' => [],
						'right' => []
					];

					if ( function_exists('blocksy_get_woo_single_layout_defaults') ) {
						$woo_single_split_layout_defults = [
							'left' => blocksy_get_woo_single_layout_defaults('left'),
							'right' => blocksy_get_woo_single_layout_defaults('right')
						];
					}
					$woo_single_split_layout = blocksy_get_theme_mod(
						'woo_single_split_layout',
						$woo_single_split_layout_defults
					);

					$woo_single_layout_left = $woo_single_split_layout['left'];
					$woo_single_layout_right = $woo_single_split_layout['right'];

					$woo_single_layout = array_merge(
						$woo_single_layout_left,
						$woo_single_layout_right
					);
				}

				$product_layer = array_search('product_add_to_cart', array_column($woo_single_layout, 'id'));
				$variations_swatches_display_type = blocksy_get_theme_mod('variations_swatches_display_type', 'no');

				if (
					! $product_layer
					||
					! isset($woo_single_layout[$product_layer])
					||
					! isset($woo_single_layout[$product_layer]['enabled'])
					||
					! $woo_single_layout[$product_layer]['enabled']
					||
					$variations_swatches_display_type === 'no'
				) {
					return $classes;
				}

				$classes[] = 'ct-inline-variations';

				return $classes;
			},
			99999, 2
		);

		add_filter('blocksy_woo_single_options:after_layers', function ($opts) {
			return [
				$opts,

				'variations_swatches_display_type' => [
					'label' => __('Display Variations Inline', 'blocksy-companion'),
					'type' => 'ct-switch',
					'value' => 'no',
					'sync' => [
						'id' => 'woo_single_layout_skip'
					],
				],
			];
		});
	}

	public function add_layer_options($opt) {
		return array_merge(
			$opt,
			[
				'product_swatches' => [
					'label' => __('Swatches', 'blocksy-companion'),
					'options' => [
						'spacing' => [
							'label' => __('Bottom Spacing', 'blocksy-companion'),
							'type' => 'ct-slider',
							'min' => 0,
							'max' => 100,
							'value' => 10,
							'responsive' => true,
							'sync' => [
								'id' => 'woo_card_layout_skip'
							]
						],
					]
				],
			]
		);
	}

	public function render_layer($layer) {
		if ($layer['id'] !== 'product_swatches') {
			return;
		}

		global $product;
		$renderer = new SwatchesFrontend();

		if ($product->get_type() !== 'variable') {
			return '';
		}

		echo $renderer->render_variation_swatches([
			'product' => $product
		]);
	}
}
