<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class Filters {
	public function __construct() {
		new ActiveFilters();

		$apply_filters = new ApplyFilters();
		$apply_filters->mount_entry_point();

		add_action('init', [$this, 'blocksy_filters_block']);
		add_action('enqueue_block_editor_assets', [$this, 'enqueue_admin']);

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
					'blocksy-ext-woocommerce-extra-filters-styles',
					BLOCKSY_URL .
						'framework/premium/extensions/woocommerce-extra/static/bundle/filters.min.css',
					['blocksy-ext-woocommerce-extra-styles'],
					$data['Version']
				);
			},
			50
		);

		add_filter(
			'blocksy:options:woocommerce:archive:ajax-filtering',
			function ($opts) {
				$opts[] = [
					'woo_filters_ajax' => [
						'label' => __('AJAX Filtering', 'blocksy-companion'),
						'type' => 'ct-panel',
						'switch' => true,
						'value' => 'no',
						'inner-options' => [
							'woo_filters_scroll_to_top' => [
								'label' => __( 'Scroll to Top', 'blocksy-companion' ),
								'desc' => __( 'Automatically scroll page to top after user interaction.', 'blocksy-companion' ),
								'type' => 'ct-switch',
								'value' => 'no',
							],
						]
					],
				];

				return $opts;
			},
			50
		);

		add_filter('blocksy:general:body-attr', function ($attr) {
			if (blocksy_get_theme_mod('woo_filters_ajax', 'no') === 'yes') {
				$attr['data-ajax-filters'] = 'yes';

				if (blocksy_get_theme_mod('woo_filters_scroll_to_top', 'no') === 'yes') {
					$attr['data-ajax-filters'] = 'yes:scroll';
				}
			}

			return $attr;
		});

		add_filter('blocksy:frontend:dynamic-js-chunks', function ($chunks) {
			if (!class_exists('WC_AJAX')) {
				return $chunks;
			}

			if (blocksy_get_theme_mod('woo_filters_ajax', 'no') === 'yes') {
				$chunks[] = [
					'id' => 'blocksy_ext_woo_extra_ajax_filters',
					'selector' => '[data-ajax-filters*="yes"]',
					'trigger' => [
						[
							'trigger' => 'click',
							'selector' => implode(', ', [
								'[data-ajax-filters*="yes"] .ct-filter-widget a',
								'[data-ajax-filters*="yes"] .ct-active-filters a',
								'[data-ajax-filters*="yes"] .ct-products-container .page-numbers',
								'[data-ajax-filters*="yes"] .ct-filter-reset a'
							])
						],

						[
							'trigger' => 'submit',
							'selector' =>
							'[data-ajax-filters*="yes"] .woocommerce-ordering',
						],

						[
							'trigger' => 'change',
							'selector' =>
							'[data-ajax-filters*="yes"] .woocommerce-ordering select',
						],

						[
							'trigger' => 'change',
							'selector' =>
							'[data-ajax-filters*="yes"] .ct-filter-item [type="checkbox"]',
						],
					],
					'url' => blocksy_cdn_url(
						BLOCKSY_URL .
							'framework/premium/extensions/woocommerce-extra/static/bundle/ajax-filter-public.js'
					),
				];
			} else {
				$chunks[] = [
					'id' => 'blocksy_ext_woo_extra_ajax_filters',
					'selector' => 'body:not([data-ajax-filters*="yes"])',
					'trigger' => [
						[
							'trigger' => 'change',
							'selector' =>
							'.ct-filter-item [type="checkbox"]',
						],

						[
							'trigger' => 'click',
							'selector' =>
							'.ct-filter-item a',
						],
					],
					'url' => blocksy_cdn_url(
						BLOCKSY_URL .
							'framework/premium/extensions/woocommerce-extra/static/bundle/ajax-filter-public.js'
					),
				];
			}

			$chunks[] = [
				'id' => 'blocksy_ext_woo_extra_filters_search',
				'selector' => '.ct-filter-widget-wrapper input[type="search"]',
				'trigger' => 'input',
				'url' => blocksy_cdn_url(
					BLOCKSY_URL .
						'framework/premium/extensions/woocommerce-extra/static/bundle/filter-search.js'
				),
			];

			return $chunks;
		});

		add_action(
			'woocommerce_before_shop_loop',
			[$this, 'products_loop_container_start'],
			100
		);

		add_action(
			'woocommerce_after_shop_loop',
			[$this, 'products_loop_container_end'],
			50
		);

		add_action(
			'woocommerce_no_products_found',
			[$this, 'products_loop_container_start'],
			9
		);

		add_action(
			'woocommerce_no_products_found',
			[$this, 'products_loop_container_end'],
			20
		);

		add_action('rest_api_init', function () {
			if (! function_exists('is_shop')) {
				return;
			}

			register_rest_field('product_brands', 'logo', array(
				'get_callback' => function ($post, $field_name, $request) {
					$term_atts = get_term_meta(
						$post['id'],
						'blocksy_taxonomy_meta_options'
					);

					if (empty($term_atts)) {
						$term_atts = [[]];
					}

					$term_atts = $term_atts[0];

					$maybe_image = blocksy_akg('image', $term_atts, '');

					return $maybe_image;
				}
			));
		});

		add_action(
			'rest_api_init',
			function () {
				// TODO: improve permission callback
				register_rest_route(
					'blocksy/v1',
					'/attributes/(?P<id>\d+)',
					array(
						'methods' => 'GET',
						'callback' => [$this, 'get_attributes_terms'],
						'permission_callback' => '__return_true'
					)
				);
			}
		);
	}

	public function get_attributes_terms($request) {
		$attribute = wc_get_attribute((int) $request['id']);

		$taxonomy_terms = get_terms(
			$attribute->slug,
			'hide_empty=0'
		);

		if (
			is_wp_error($taxonomy_terms)
			||
			sizeof($taxonomy_terms) === 0
		) {
			return [];
		}

		$first_swatch_id = $taxonomy_terms[0]->term_id;
		$first_swatch = new SwatchesRender($first_swatch_id);

		$swatch_type = $first_swatch->type;

		$responce_terms = [];

		foreach ($taxonomy_terms as $term) {
			$responce_terms[] = [
				'id' => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
				'count' => $term->count,
				'parent' => $term->parent,
				'meta' => get_term_meta($term->term_id, 'blocksy_taxonomy_meta_options'),
				'short_name' => get_term_meta($term->term_id, 'short_name', true) ?? '',
				'swatch_type' => $swatch_type,
			];
		}

		return rest_ensure_response($responce_terms);
	}

	public function blocksy_filters_block() {
		register_block_type('blocksy/woocommerce-filters', [
			'render_callback' => function ($attributes, $content, $block) {
				if (
					! is_woocommerce()
					&&
					! wp_doing_ajax()
				) {
					return '';
				}

				$filter = BaseFilter::get_filter_for($attributes);

				if (! $filter) {
					return '';
				}

				$presenter = new FilterPresenter($filter);

				return $presenter->render();
			},
		]);
	}

	public function enqueue_admin() {
		$data = get_plugin_data(BLOCKSY__FILE__);

		wp_enqueue_script(
			'blocksy/woocommerce-filters',
			BLOCKSY_URL .
				'framework/premium/extensions/woocommerce-extra/static/bundle/woocommerce-filters.js',
			['wp-blocks', 'wp-element', 'wp-block-editor'],
			$data['Version']
		);

		$attribute_taxonomies = wc_get_attribute_taxonomies();

		$conf = new SwatchesConfig();

		foreach ($attribute_taxonomies as $key => $attributes_tax) {
			$attribute_taxonomies[$key] = array_merge((array) $attributes_tax, [
				'type' => $conf->get_attribute_type(
					$attributes_tax->attribute_name
				),
			]);
		}

		$storage = new Storage();
		$settings = $storage->get_settings();

		$data = [
			'attributes_tax' => $attribute_taxonomies,
			'ct_color_swatch_shape' => blocksy_get_theme_mod(
				'color_swatch_shape',
				'round'
			),
			'ct_image_swatch_shape' => blocksy_get_theme_mod(
				'image_swatch_shape',
				'round'
			),
			'ct_button_swatch_shape' => blocksy_get_theme_mod(
				'button_swatch_shape',
				'round'
			),

			'has_swatches' => !! $settings['features']['variation-swatches'],
			'has_brands' => !! $settings['features']['product-brands'],
		];

		wp_localize_script(
			'blocksy/woocommerce-filters',
			'blc_filters_data',
			$data
		);
	}

	public function products_loop_container_start() {
		if (blocksy_get_theme_mod('woo_filters_ajax', 'no') !== 'yes') {
			return;
		}

		echo '<div class="ct-products-container">';

		echo blocksy_html_tag(
			'span',
			[
				'class' => 'ct-filters-loading',
			],
			'<svg width="23" height="23" viewBox="0 0 40 40">
			<path opacity=".2" fill="currentColor" d="M20.201 5.169c-8.254 0-14.946 6.692-14.946 14.946 0 8.255 6.692 14.946 14.946 14.946s14.946-6.691 14.946-14.946c-.001-8.254-6.692-14.946-14.946-14.946zm0 26.58c-6.425 0-11.634-5.208-11.634-11.634 0-6.425 5.209-11.634 11.634-11.634 6.425 0 11.633 5.209 11.633 11.634 0 6.426-5.208 11.634-11.633 11.634z"/>

			<path fill="currentColor" d="m26.013 10.047 1.654-2.866a14.855 14.855 0 0 0-7.466-2.012v3.312c2.119 0 4.1.576 5.812 1.566z">
			<animateTransform attributeName="transform" type="rotate" from="0 20 20" to="360 20 20" dur="0.5s" repeatCount="indefinite"/>
			</path>
			</svg>'
		);
	}

	public function products_loop_container_end() {
		if (blocksy_get_theme_mod('woo_filters_ajax', 'no') !== 'yes') {
			return;
		}

		echo '</div>';
	}
}
