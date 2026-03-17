<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class Brands {
	public function __construct() {
		new \Blocksy\Extensions\WoocommerceExtra\BrandsImportExport();

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
					'blocksy-ext-woocommerce-extra-product-brands-styles',
					BLOCKSY_URL .
						'framework/premium/extensions/woocommerce-extra/static/bundle/product-brands.min.css',
					['blocksy-ext-woocommerce-extra-styles'],
					$data['Version']
				);
			},
			50
		);

		add_action('current_screen', function () {
			if (function_exists('add_settings_field')) {
				add_settings_field(
					'blocksy_woocommerce_extra_product_brands_slug',
					__('Product brands base', 'blocksy-companion'),
					function () {
						$storage = new Storage();
						$settings = $storage->get_settings();

						echo blocksy_html_tag(
							'input',
							[
								'name' => 'blocksy_woocommerce_extra_product_brands_slug',
								'type' => 'text',
								'class' => 'regular-text code',
								'value' => $settings['product-brands-slug'],
								'placeholder' => __('brand', 'blocksy-companion')
							]
						);
					},
					'permalink',
					'optional'
				);
			}

			if (
				is_admin()
				&&
				isset($_POST['blocksy_woocommerce_extra_product_brands_slug'])
				&&
				wp_verify_nonce(
					wp_unslash($_POST['wc-permalinks-nonce']),
					'wc-permalinks'
				)
			) {
				$storage = new Storage();
				$settings = $storage->get_settings();

				$settings['product-brands-slug'] = wc_sanitize_permalink(
					$_POST['blocksy_woocommerce_extra_product_brands_slug']
				);

				update_option(
					'blocksy_ext_woocommerce_extra_settings',
					$settings
				);
			}
		}, 100);

		add_action('init', [$this, 'register_brand_meta']);

		add_filter(
			'blocksy_woo_single_options_layers:defaults',
			[$this, 'add_layer_to_default_layout']
		);
		add_filter(
			'blocksy_woo_compare_layers:defaults',
			[$this, 'add_layer_to_default_layout']
		);
		add_filter(
			'blocksy_woo_card_options_layers:defaults',
			[$this, 'add_layer_to_default_layout']
		);
		add_filter(
			'blocksy_woo_single_right_options_layers:defaults',
			[$this, 'add_layer_to_default_layout']
		);

		add_filter(
			'blocksy_woo_single_options_layers:extra',
			[$this, 'add_single_layer_options']
		);
		add_filter(
			'blocksy_woo_compare_layers:extra',
			[$this, 'add_compare_layer_options']
		);
		add_filter(
			'blocksy_woo_card_options_layers:extra',
			[$this, 'add_archive_layer_options']
		);
		add_filter(
			'blocksy_woo_single_right_options_layers:extra',
			[$this, 'add_single_layer_options']
		);

		add_action(
			'blocksy:woocommerce:product:custom:layer',
			[$this, 'product_single_render']
		);

		add_action(
			'blocksy:woocommerce:product-card:custom:layer',
			[$this, 'product_card_render']
		);

		add_action(
			'blocksy:woocommerce:compare:custom:layer',
			[$this, 'product_card_render']
		);

		add_filter(
			'blocksy:options:woo:tabs:general:brands',
			function ($opts) {
				$opts[] = blocksy_get_options(
					dirname(__FILE__) . '/options.php',
					[],
					false
				);

				return $opts;
			},
			50
		);

		add_action(
			'wp',
			function() {
				if (blocksy_get_theme_mod('has_woo_brands_tab', 'no') === 'yes') {
					add_filter(
						'woocommerce_product_tabs',
						[$this, 'brands_custom_product_tab']
					);
				}
			}
		);

		add_action(
			'woocommerce_product_duplicate',
			function ($duplicate, $product) {
				$terms = get_the_terms($product->get_id(), 'product_brands');

				if (! is_wp_error($terms)) {
					wp_set_object_terms($duplicate->get_id(), wp_list_pluck($terms, 'term_id'), 'product_brands');
				}
			},
			999,
			2
		);
	}

	public function brands_custom_product_tab( $tabs ) {
		global $product;

		$brands = get_the_terms($product->get_id(), 'product_brands');

		if (!$brands || !is_array($brands)) {
			return $tabs;
		}

		if (!count($brands)) {
			return $tabs;
		}

		$tabs['specific_product_tab'] = array(
			'title' => blocksy_get_theme_mod('use_brand_name_for_tab_title', 'no') === 'no' ? __( 'About Brands', 'blocksy-companion' ) : blc_safe_sprintf(
					__('About %s', 'blocksy-companion'),
					$brands[0]->name
				),
			'priority' => 50,
			'callback' => [$this, 'brands_custom_product_tab_render']
		);

		return $tabs;
	}

	//Add content to a custom product tab
	public function brands_custom_product_tab_render() {
		$brands = get_the_terms(get_the_ID(), 'product_brands');

		if (!$brands || !is_array($brands)) {
			return;
		}

		if (!count($brands)) {
			return;
		}

		$output = '';

		$tabs_type = blocksy_get_theme_mod('woo_tabs_type', 'type-1');

		if ( $tabs_type === 'type-4' ) {
			$output .= blocksy_html_tag(
				'h2',
				[],
				blocksy_get_theme_mod('use_brand_name_for_tab_title', 'no') === 'no'
					? __('About Brands', 'blocksy-companion')
					: blc_safe_sprintf(
						__('About %s', 'blocksy-companion'),
						$brands[0]->name
					)
			);
		}

		foreach ($brands as $key => $brand) {
			$output .= blocksy_html_tag(
				'div',
				[
					'class' => 'ct-product-brands-tab'
				],
				do_shortcode(wpautop($brand->description))
			);
		}

		echo $output;
	}

	public function add_compare_layer_options($opt) {
		$opt = array_merge(
			$opt,
			[
				'product_brands' => [
					'label' => __('Brands', 'blocksy-companion'),
					'options' => [
						'compare_row_sticky' => [
							'type'  => 'ct-switch',
							'label' => __( 'Sticky Row', 'blocksy-companion' ),
							'value' => 'no',
						],
					]
				]
			]
		);

		return $opt;
	}

	public function add_single_layer_options($opt) {
		$opt = array_merge(
			$opt,
			[
				'product_brands' => [
					'label' => __('Brands', 'blocksy-companion'),
					'options' => [

						'brand_layer_title' => [
							'label' => __('Title', 'blocksy'),
							'type' => 'text',
							'design' => 'block',
							'value' => '',
							'disableRevertButton' => true,
							'sync' => [
								'id' => 'woo_card_layout_skip'
							],
						],

						'brand_logo_size' => [
							'label' => __('Logo Size', 'blocksy-companion'),
							'type' => 'ct-slider',
							'value' => 100,
							'min' => 30,
							'max' => 200,
							'responsive' => true,
							'sync' => [
								'id' => 'woo_card_layout_skip'
							]
						],

						'brand_logo_gap' => [
							'label' => __('Logos Gap', 'blocksy-companion'),
							'type' => 'ct-slider',
							'value' => 10,
							'min' => 0,
							'max' => 100,
							'responsive' => true,
							'sync' => [
								'id' => 'woo_card_layout_skip'
							]
						],

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

		return $opt;
	}

	public function add_archive_layer_options($opt) {
		$opt = array_merge(
			$opt,
			[
				'product_brands' => [
					'label' => __('Brands', 'blocksy-companion'),
					'options' => [

						'brand_logo_size' => [
							'label' => __('Logo Size', 'blocksy-companion'),
							'type' => 'ct-slider',
							'value' => 100,
							'min' => 30,
							'max' => 200,
							'responsive' => true,
							'sync' => [
								'id' => 'woo_card_layout_skip'
							]
						],

						'brand_logo_gap' => [
							'label' => __('Logos Gap', 'blocksy-companion'),
							'type' => 'ct-slider',
							'value' => 10,
							'min' => 0,
							'max' => 100,
							'responsive' => true,
							'sync' => [
								'id' => 'woo_card_layout_skip'
							]
						],

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

		return $opt;
	}

	public function add_layer_to_default_layout($opt) {
		$opt = array_merge(
			$opt,
			[
				[
					'id' => 'product_brands',
					'enabled' => false,
				]
			]
		);

		return $opt;
	}

	public function register_brand_meta() {
		$storage = new Storage();
		$settings = $storage->get_settings();

		register_taxonomy('product_brands', ['product'], [
			'label'                 => '',
			'labels'                => [
				'name'              => __('Brands', 'blocksy-companion'),
				'singular_name'     => __('Brand', 'blocksy-companion'),
				'search_items'      => __('Search Brands', 'blocksy-companion'),
				'all_items'         => __('All Brands', 'blocksy-companion'),
				'parent_item'       => __('Parent Brand', 'blocksy-companion'),
				'parent_item_colon' => __('Parent Brand:', 'blocksy-companion'),
				'view_item '        => __('View Brand', 'blocksy-companion'),
				'edit_item'         => __('Edit Brand', 'blocksy-companion'),
				'update_item'       => __('Update Brand', 'blocksy-companion'),
				'add_new_item'      => __('Add New Brand', 'blocksy-companion'),
				'new_item_name'     => __('New Brand Name', 'blocksy-companion'),
				'menu_name'         => __('Brands', 'blocksy-companion'),
			],
			'hierarchical'          => true,
			'show_ui' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'show_in_rest' => true,
			'rewrite' => [
				'slug' => $settings['product-brands-slug']
			],
		]);

		add_action(
			'product_brands_edit_form',
			[$this, 'term_options']
		);

		add_action(
			'product_brands_add_form',
			[$this, 'term_options']
		);

		add_action('edited_term', function ($term_id, $tt_id, $taxonomy) {
			if (
				!(
					isset($_POST['action'])
					&&
					'editedtag' === $_POST['action']
					&&
					isset($_POST['taxonomy'])
					&&
					($taxonomy = get_taxonomy(sanitize_text_field(wp_unslash($_POST['taxonomy']))))
					&&
					current_user_can($taxonomy->cap->edit_terms)
				)
			) {
				return;
			}

			$values = [];

			if (isset($_POST['blocksy_taxonomy_meta_options'][blocksy_post_name()])) {
				$values = json_decode(
					sanitize_text_field(
						wp_unslash(
							$_POST['blocksy_taxonomy_meta_options'][
								blocksy_post_name()
							]
						)
					),
					true
				);
			}

			update_term_meta(
				$term_id,
				'blocksy_taxonomy_meta_options',
				$values
			);

			do_action('blocksy:dynamic-css:refresh-caches');
		}, 10, 3);
	}

	public function term_options($term) {
		$values = isset($term->term_id) ? get_term_meta(
			$term->term_id,
			'blocksy_taxonomy_meta_options'
		) : [[]];

		if (empty($values)) {
			$values = [[]];
		}

		if (! $values[0]) {
			$values[0] = [];
		}

		$options = [
			'image' => [
				'type' => 'ct-image-uploader',
				'value' => '',
				'attr' => [
					'data-type' => 'large'
				],
				'emptyLabel' => __('Select Image', 'blocksy-companion'),
			]
		];

		echo blocksy_html_tag(
			'div',
			[],
			blocksy_html_tag(
				'input',
				[
					'type' => 'hidden',
					'value' => htmlspecialchars(wp_json_encode($values[0])),
					'data-options' => htmlspecialchars(
						wp_json_encode($options)
					),
					'name' => 'blocksy_taxonomy_meta_options[' . blocksy_post_name() . ']',
				]
			)
		);
	}

	public function render_brands_grid($brands) {
		$output = '';

		foreach ($brands as $key => $brand) {

			$label = blocksy_html_tag(
				'a',
				[
					'href' => esc_url(get_term_link($brand)),
				],
				$brand->name
			);

			$term_atts = get_term_meta(
				$brand->term_id,
				'blocksy_taxonomy_meta_options'
			);

			if (empty($term_atts)) {
				$term_atts = [[]];
			}

			$term_atts = $term_atts[0];

			$maybe_image = blocksy_akg('image', $term_atts, '');

			if (
				$maybe_image
				&&
				is_array($maybe_image)
				&&
				isset($maybe_image['attachment_id'])
			) {
				$attachment_id = $maybe_image['attachment_id'];

				$label = blocksy_media([
					'attachment_id' => $maybe_image['attachment_id'],
					'size' => 'full',
					'ratio' => 'initial',
					'class' => 'ct-product-brand',
					'tag_name' => 'a',
					'html_atts' => [
						'href' => get_term_link($brand),
						'aria-label' => $brand->name
					]
				]);
			}

			$output .= $label;
		}

		return $output;
	}

	public function product_single_render($layer) {
		if ($layer['id'] !== 'product_brands') {
			return;
		}

		$brands = get_the_terms(get_the_ID(), 'product_brands');

		if (!$brands || !is_array($brands)) {
			return;
		}

		if (!count($brands)) {
			return;
		}

		$section_title = blocksy_akg('brand_layer_title', $layer, '');

		echo blocksy_html_tag(
			'div',
			[
				'class' => 'ct-product-brands-single',
			],
			(
				! empty($section_title) || is_customize_preview() ?
				blocksy_html_tag(
					'span',
					[
						'class' => 'ct-module-title',
					],
					$section_title
				) : ''
			) .
			blocksy_html_tag(
				'div',
				[
					'class' => 'ct-product-brands',
				],
				$this->render_brands_grid($brands)
			)
		);
	}

	public function product_card_render($layer) {
		if ($layer['id'] !== 'product_brands') {
			return;
		}

		$brands = get_the_terms(get_the_ID(), 'product_brands');

		if (!$brands || !is_array($brands)) {
			return;
		}

		if (!count($brands)) {
			return;
		}

		echo blocksy_html_tag(
			'div',
			[
				'class' => 'ct-product-brands',
			],
			$this->render_brands_grid($brands)
		);
	}
}
