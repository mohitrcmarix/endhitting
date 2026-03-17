<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class SwatchesRender {
	public $term_id;
	public $term_label;
	public $term_slug;
	public $type;
	public $color;
	public $thumbnail_id;
	public $short_name;

	private $is_selected = false;
	private $is_valid = false;

	public function __construct($term_id, $args = []) {
		$args = wp_parse_args(
			$args,
			[
				'is_selected' => false,
				'is_valid' => true,
			]
		);

		$this->is_selected = $args['is_selected'];
		$this->is_valid = $args['is_valid'];

		$conf = new SwatchesConfig();

		$this->term_id = $term_id;

		$term = get_term($term_id);
		$this->term_label = $term->name;
		$this->term_slug = $term->slug;

		$taxonomy_slug = $conf->get_parent_taxonomy($term_id)->attribute_name;

		$term_atts = get_term_meta(
			$term_id,
			'blocksy_taxonomy_meta_options'
		);

		$short_name = get_term_meta(
			$term_id,
			'short_name',
			true
		);

		if (empty($term_atts)) {
			$term_atts = [[]];
		}

		$term_atts = $term_atts[0];

		$this->color = '#FFF';

		if (isset($term_atts['accent_color']['default']['color'])) {
			$this->color = $term_atts['accent_color']['default']['color'];
		}

		if ($short_name) {
			$this->short_name = $short_name;
		}

		$this->thumbnail_id = null;

		if (isset($term_atts['image']['attachment_id'])) {
			$this->thumbnail_id = $term_atts['image']['attachment_id'];
		}

		$this->type = $conf->get_attribute_type($taxonomy_slug);
	}

	public function get_image_output() {
		if (! function_exists('blocksy_media')) {
			return '';
		}

		return blocksy_media(
			[
				'attachment_id' => $this->thumbnail_id,
				'ratio' => '1/1',
				'size' => 'thumbnail',
				'tag_name' => 'span',
				'class' => 'ct-swatch'
			]
		);
	}

	public function get_color_output() {
		if ($this->color === 'CT_CSS_SKIP_RULE') {
			return '';
		}
		
		return blocksy_html_tag(
			'span',
			[
				'class' => 'ct-swatch',
				'style' => 'background-color: ' . $this->color . ';',
			],
		);
	}

	public function get_button_output() {
		$out = '';
		$out .= '<span
				class="ct-swatch">
			';
		$out .= isset($this->short_name) && !empty($this->short_name) ? $this->short_name : $this->term_label;
		$out .= '</span>';

		return $out;
	}

	public function get_output($skip_tooltip = false) {
		$picker = '';

		if (! $skip_tooltip) {
			$picker .= '<span class="ct-tooltip">' . esc_attr($this->term_label) . ($this->is_valid ? '' : ' - ' . __('Out of Stock', 'blocksy-companion')) . '</span>';
		}

		if ($this->type === 'color') {
			$picker .= $this->get_color_output();
		}

		if ($this->type === 'image') {
			$picker .= $this->get_image_output();
		}

		if ($this->type === 'button') {
			$picker .= $this->get_button_output();
		}

		$class = [
			'ct-swatch-container'
		];

		if ($this->is_selected) {
			$class[] = 'active';
		}

		if (! $this->is_valid) {
			$class[] = 'ct-out-of-stock';
		}

		$out = '';
		$content = apply_filters('woocommerce_swatches_picker_html', $picker, $this);

		if (! empty($content)) {
			$out = blocksy_html_tag(
				'div',
				[
					'class' => implode(' ', $class),
					'data-value' => esc_attr($this->term_slug),
				],
				$content
			);
		}

		return $out;
	}
}
