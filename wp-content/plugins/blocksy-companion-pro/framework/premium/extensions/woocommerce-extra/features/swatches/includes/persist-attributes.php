<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class SwatchesPersistAttributes {
	private $conf = null;

	public function __construct() {
		$this->conf = new SwatchesConfig();

		add_action('admin_init', function () {
			$this->attributes_meta_init();
			$this->attributes_value_meta_init();
		});
	}

	private function attributes_meta_init() {
		add_action(
			'woocommerce_after_add_attribute_fields',
			[$this, 'add_attr_type_field']
		);

		add_action(
			'woocommerce_after_edit_attribute_fields',
			[$this, 'add_attr_type_field']
		);

		add_action(
			'woocommerce_attribute_added',
			[$this, 'save_attr_type_product_attribute']
		);

		add_action(
			'woocommerce_attribute_updated',
			[$this, 'save_attr_type_product_attribute']
		);
	}

	private function attributes_value_meta_init() {
		add_action(
			'edited_term',
			[$this, 'persist_attributes_values_option'],
			10, 3
		);

		add_action(
			'create_term',
			[$this, 'persist_attributes_values_option'],
			10, 3
		);

		if (! function_exists('wc_get_attribute_taxonomies')) {
			return;
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if (! $attribute_taxonomies) {
			return;
		}

		foreach ($attribute_taxonomies as $tax) {
			add_action(
				'pa_' . $tax->attribute_name . '_edit_form_fields',
				[$this, 'output_attributes_values_options'],
				10, 2
			);

			add_action(
				'pa_' . $tax->attribute_name . '_add_form_fields',
				[$this, 'output_add_attributes_values_options'],
				10, 1
			);

			$type = $this->conf->get_attribute_type_term_by_id($tax->attribute_id);

			if ($type === 'color' || $type ===  'image') {
				add_filter(
					'manage_edit-pa_' . $tax->attribute_name . '_columns',
					function ($columns) {
						$new_columns = array();
						$new_columns['cb'] = $columns['cb'];
						$new_columns['blc_swatch_preview'] = '';
						unset($columns['cb']);
						$columns = array_merge($new_columns, $columns);

						return $columns;
					}
				);

				add_filter(
					'manage_pa_' . $tax->attribute_name . '_custom_column',
					function ($columns, $column, $id) {
						if ($column !== 'blc_swatch_preview') {
							return $columns;
						}

						$swatch_term = new SwatchesRender($id);
						return $columns . $swatch_term->get_output(true);
					},
					10, 3
				);
			}
		}
	}

    public function save_attr_type_product_attribute($id) {
        if (! isset($_POST['blocksy_taxonomy_meta_options'])) {
			return;
		}

		$metas = $_POST['blocksy_taxonomy_meta_options'];

		if (! isset($metas['swatch_type'])) {
			return;
		}

		$values = [];

		$values['swatch_type'] = $metas['swatch_type'];

		update_term_meta($id, 'blocksy_taxonomy_meta_options', $values);
	}

	public function persist_attributes_values_option($term_id, $tt_id, $taxonomy) {
		if (
			!(
				isset($_POST['action'])
				&&
				('editedtag' === $_POST['action'] || 'add-tag' === $_POST['action'])
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

		if (isset($_POST['short_name'])) {
			$short_name = sanitize_text_field($_POST['short_name']);

			update_term_meta($term_id, 'short_name', $short_name);
		}

		update_term_meta(
			$term_id,
			'blocksy_taxonomy_meta_options',
			$values
		);

		do_action('blocksy:dynamic-css:refresh-caches');
	}

	public function add_attr_type_field() {
		$id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;

		$selected_type = $this->conf->get_attribute_type_term_by_id($id);

		$variation_types = [
			'button' => ['name' => __('Button', 'blocksy-companion')],
			'select' => ['name' => __('Select', 'blocksy-companion')],
			'color' => ['name' => __('Color', 'blocksy-companion')],
			'image' => ['name' => __('Image', 'blocksy-companion')],
		];

		?>

		<div class="form-field">
			<th scope="row" valign="top">
				<label for="product_attribute_swatchtype_swatch_type">
					<?php _e('Swatch Type', 'blocksy-companion'); ?>
				</label>
			</th>

			<td>
				<select name="blocksy_taxonomy_meta_options[swatch_type]" id="product_attribute_swatchtype_swatch_type" class="postform">
					<?php
						$variation_types = $variation_types;

						foreach ($variation_types as $key => $value) { ?>
							<option value="<?php echo $key ?>" <?php echo $key == $selected_type ? 'selected' : ''; ?>>
								<?php echo $value['name']; ?>
							</option>
						<?php }
					?>
				</select>

				<p class="description">
					<?php _e('Determines the swatch type of this attribute.', 'blocksy-companion'); ?>
				</p>
			</td>
		</div>
	<?php }

	public function output_add_attributes_values_options($taxonomy) {
		$selected_type = $this->conf->get_attribute_type($taxonomy);

		$values = [[]];
		$options = [];

		if ($selected_type === 'image') {
			$options = [
				'image' => [
					'label' => __('Image', 'blocksy-companion'),
					'type' => 'ct-image-uploader',
					'value' => '',
					'attr' => [
						'data-type' => 'large'
					],
					'emptyLabel' => __('Upload Image', 'blocksy-companion'),
					'desc' => __('Upload an image that will be used for this term.', 'blocksy-companion'),
				]
			];
		}

		if ($selected_type === 'color') {
			$options = [
				'accent_color' => [
					'label' => __('Color', 'blocksy-companion'),
					'type' => 'ct-color-picker',
					'design' => 'inline',
					'disableRevertButton' => true,
					'value' => [
						'default' => [
							'color' => 'CT_CSS_SKIP_RULE',
						]
					],
					'pickers' => [
						[
							'title' => __('Initial Color', 'blocksy-companion'),
							'id' => 'default'
						]
					],
					'desc' => __('Choose a color that will be used for this term.', 'blocksy-companion'),
				],
			];
		}

		if ($selected_type === 'button') {
			echo blocksy_html_tag(
				'div',
				['class' => 'form-field form-required term-short-name-wrap'],
				blocksy_html_tag(
					'label',
					['for' => 'short_name'],
					__('Short Name', 'blocksy-companion')
				) .
				blocksy_html_tag(
					'input',
					[
						'id' => 'short_name',
						'type' => 'text',
						'value' => '',
						'name' => 'short_name'
					]
				) .
				blocksy_html_tag(
					'p',
					['id' => 'short_name-description'],
					__('Set a short name that will be used for this term.', 'blocksy-companion')
				)
			);

			return;
		}

		if (empty($options)) {
			return;
		}

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
					'name' => 'blocksy_taxonomy_meta_options[' . blocksy_post_name() . ']'
				]
			)
		);
	}

	public function output_attributes_values_options($term, $taxonomy) {
		$selected_type = $this->conf->get_attribute_type($term->taxonomy);

		$values = get_term_meta(
			$term->term_id,
			'blocksy_taxonomy_meta_options'
		);

		if (empty($values)) {
			$values = [[]];
		}

		if (! $values[0]) {
			$values[0] = [];
		}

		$options = [];

		if ($selected_type === 'image') {
			$options = [
				'image' => [
					'label' => __('Image', 'blocksy-companion'),
					'help' => __('Select Image', 'blocksy-companion'),
					'type' => 'ct-image-uploader',
					'value' => '',
					'attr' => [
						'data-type' => 'large'
					],
					'emptyLabel' => __('Upload Image', 'blocksy-companion'),
					'desc' => __('Upload an image that will be used for this term.', 'blocksy-companion'),
				]
			];
		}

		if ($selected_type === 'color') {
			$options = [
				'accent_color' => [
					'label' => __('Color', 'blocksy-companion'),
					'type' => 'ct-color-picker',
					'design' => 'inline',
					'value' => [
						'default' => [
							'color' => 'CT_CSS_SKIP_RULE',
						]
					],
					'pickers' => [
						[
							'title' => __('Text Initial', 'blocksy-companion'),
							'id' => 'default'
						]
					],
					'desc' => __('Choose a color that will be used for this term.', 'blocksy-companion'),
				]
			];
		}

		if ($selected_type === 'button') {
			$short_name = get_term_meta(
				$term->term_id,
				'short_name',
				true
			);

			echo blocksy_html_tag(
				'tr',
				['class' => 'form-field term-short-name-wrap'],
				blocksy_html_tag(
					'th',
					['scope' => 'row'],
					blocksy_html_tag(
						'label',
						['for' => 'short_name'],
						__('Short Name', 'blocksy-companion')
					)
				) .
				blocksy_html_tag(
					'td',
					[],
					blocksy_html_tag(
						'input',
						[
							'id' => 'short_name',
							'type' => 'text',
							'value' => $short_name,
							'name' => 'short_name'
						]
					) .
					blocksy_html_tag(
						'p',
						[
							'id' => 'short_name-description',
							'class' => 'description'
						],
						__('Set a short name that will be used for this term.', 'blocksy-companion')

					)
				)
			);

			return;
		}

		if (empty($options)) {
			return;
		}

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
					'name' => 'blocksy_taxonomy_meta_options[' . blocksy_post_name() . ']'
				]
			)
		);
	}
}

