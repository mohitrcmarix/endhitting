<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class SwatchesConfig {
	public function get_attribute_type_term_by_id($id) {
		$term_atts = get_term_meta($id, 'blocksy_taxonomy_meta_options');

		if (empty($term_atts)) {
			$term_atts = [[]];
		}

		$term_atts = $term_atts[0];

		return blocksy_akg('swatch_type', $term_atts, 'button');
	}

	public function get_parents($taxonomy) {
		$attribute_taxonomies = wc_get_attribute_taxonomies();

		$parents = array_filter($attribute_taxonomies, function ($item) use ($taxonomy) {
			return $item->attribute_name == rtrim(preg_replace('/pa_/', '', $taxonomy));
		});

		return $parents;
	}

	public function get_parent_taxonomy($term_id) {
		$term = get_term($term_id);
		$parents = $this->get_parents($term->taxonomy);

		return reset($parents);
	}

	public function get_attribute_type($taxonomy) {
		$parents = $this->get_parents($taxonomy);

		if (! empty($parents)) {
			$parent_taxonomy = array_values($this->get_parents($taxonomy))[0];

			if (isset($parent_taxonomy->attribute_id)) {
				$selected_type = $this->get_attribute_type_term_by_id(
					$parent_taxonomy->attribute_id
				);

				if ($selected_type) {
					return $selected_type;
				}
			}
		}

		return 'button';
	}

}
