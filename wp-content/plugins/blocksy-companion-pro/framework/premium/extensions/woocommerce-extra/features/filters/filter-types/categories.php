<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class CategoriesFilter extends BaseFilter {
	use QueryManager;

	public function get_filter_name() {
		return 'filter_product_category';
	}

	public function wp_query_arg($query_string, $query_args) {
		if (! isset($query_string['filter_product_category'])) {
			return $query_args;
		}

		$terms = explode(',', $query_string['filter_product_category']);

		if (! isset($query_args['tax_query'])) {
			$query_args['tax_query'] = [];
		}

		$query_args['tax_query'][] = [
			'taxonomy' => 'product_cat',
			'field' => 'id',
			'terms' => $terms,
			'operator' => 'IN'
		];

		return $query_args;
	}

	public function render() {
		return [
			'items' => $this->filter_get_items_for_categories()
		];
	}

	private function get_terms_for($parent = null) {
		$params = [
			'hide_empty' => true,
			'exclude' => $this->attributes['taxonomy_not_in']
		];

		if (! $this->attributes['excludeTaxonomy']) {
			unset($params['exclude']);
		}

		if ($parent === null) {
			$params['parent'] = 0;

			$is_taxonomy_page = $this->is_taxonomy_page();

			if ($is_taxonomy_page) {
				$queried_object = get_queried_object();

				if ($queried_object->taxonomy === 'product_cat') {
					$params['parent'] = $queried_object->term_id;
				}
			}
		} else {
			$params['parent'] = $parent;
		}

		$terms = get_terms('product_cat', $params);

		foreach ($terms as $term) {
			$term->children = $this->get_terms_for($term->term_id);
		}

		return $terms;
	}

	private function flatten_terms($terms) {
		$flattened = [];

		foreach ($terms as $term) {
			$flattened[] = $term;

			if (isset($term->children)) {
				$flattened = array_merge(
					$flattened,
					$this->flatten_terms($term->children)
				);
			}
		}

		return $flattened;
	}

	private function filter_get_items_for_categories() {
		$is_hierarchical =
			$this->attributes['viewType'] === 'list' ? $this->attributes['hierarchical'] : false;

		$terms = $this->get_terms_with_counts($this->get_terms_for());

		if (! $is_hierarchical) {
			$terms = $this->flatten_terms($terms);
		}

		$list_items_html = [];

		foreach ($terms as $key => $value) {
			$list_items_html[] = self::get_category_item($value);
		}

		return $list_items_html;
	}

	private function get_category_item($category) {
		$is_hierarchical =
			$this->attributes['viewType'] === 'list' ? $this->attributes['hierarchical'] : false;
		$is_expandable = $is_hierarchical ? $this->attributes['expandable'] : false;

		$api_url = $this->get_link_url(
			$category->term_id,
			[
				'is_multiple' => $this->attributes['multipleFilters']
			]
		);

		$label_html = blocksy_html_tag(
			'span',
			['class' => 'ct-filter-label'],
			$category->name
		);

		$checbox_html = $this->attributes['showCheckbox']
			? blocksy_html_tag(
				'input',
				array_merge(
					[
						'type' => 'checkbox',
						'class' => 'ct-checkbox',
						'tabindex' => '-1',
						'name' => 'product_category_' . $category->term_id,
						'aria-label' => $category->name,
					],
					$this->is_filter_active($category->term_id)
						? ['checked' => 'checked']
						: []
				)
			)
			: '';

		$products_count = $this->format_products_count([
			'count' => $category->count,
			'with_wrap' => $is_expandable && $this->attributes['showCounters']
		]);


		if (! $products_count) {
			return '';
		}

		if (! $this->attributes['showCounters']) {
			$products_count = '';
		}

		$childrens_html = '';
		$expandable_triger = '';

		if ($is_expandable && $this->attributes['showCounters']) {
			$label_html = blocksy_html_tag(
				'span',
				['class' => 'ct-filter-label'],
				$category->name . $products_count
			);
		}

		if ($is_hierarchical) {
			$childrens_items_html = [];

			if (sizeof($category->children)) {
				foreach ($category->children as $key => $value) {
					$childrens_items_html[] = self::get_category_item(
						$value,
						$this->attributes
					);
				}

				$childrens_html = blocksy_html_tag(
					'ul',
					array_merge([
						'class' => 'ct-filter-children',
						'aria-hidden' => $this->attributes['defaultExpanded']
							? 'false'
							: 'true',
						'data-behaviour' => $is_expandable
							? 'drop-down'
							: 'list',
					]),
					implode('', $childrens_items_html)
				);

				if ($is_expandable) {
					$expandable_triger = blocksy_html_tag(
						'button',
						[
							'class' => 'ct-expandable-trigger',
							'aria-expanded' => $this->attributes['defaultExpanded']
								? 'true'
								: 'false',
							'aria-label' => $this->attributes['defaultExpanded']
								? __('Collapse', 'blocksy-companion')
								: __('Expand', 'blocksy-companion'),
						],
						"<svg class='ct-icon' width='10' height='10' viewBox='0 0 25 25'><path d='M.207 17.829 12.511 5.525l1.768 1.768L1.975 19.596z'/><path d='m10.721 7.243 1.768-1.768L24.793 17.78l-1.768 1.767z'/></svg>"
					);
				}
			}
		}

		if ($this->attributes['showCounters'] && empty($products_count)) {
			return '';
		}

		if ($is_expandable && $this->attributes['showCounters']) {
			$products_count = '';
		}

		$item_classes = ['ct-filter-item'];

		if ($this->is_filter_active($category->term_id)) {
			$item_classes[] = 'active';
		}

		return blocksy_html_tag(
			'li',
			[
				'class' => implode(' ', $item_classes),
			],
			blocksy_html_tag(
				'div',
				[
					'class' => 'ct-filter-item-inner'
				],
				blocksy_html_tag(
					'a',
					[
						'href' => esc_url($api_url),
						'rel' => 'nofollow',
						'aria-label' => $category->name,
						'data-key' => 'product_category',
						'data-value' => $category->term_id,
					],
						$checbox_html .
						$label_html .
						$products_count
				) .
				$expandable_triger
			) .
			$childrens_html
		);
	}

	private function get_terms_with_counts($terms, $counts = null) {
		$terms_with_counts = [];

		if ($counts === null) {
			$flattened_terms = $this->flatten_terms($terms);

			$terms_with_children = [];

			foreach ($flattened_terms as $term) {
				if (count($term->children) > 0) {
					$terms_with_children[] = $term;
				}
			}

			$counts = $this->get_terms_counts($flattened_terms);

			if (! empty($terms_with_children)) {
				global $wpdb;

				$select_components = [];
				$subqueries = [];

				foreach ($terms_with_children as $term) {
					$all_children = $this->flatten_terms([$term]);

					$all_children_ids = array_map(function ($term) {
						return $term->term_taxonomy_id;
					}, $all_children);

					$all_product_ids = $this->get_product_ids();

					$select_components[] = "term_{$term->term_taxonomy_id}.count as term_{$term->term_taxonomy_id}";

					$subqueries[] = trim("
						(SELECT COUNT(DISTINCT object_id) as count
						FROM {$wpdb->term_relationships}
						WHERE (
							object_id IN (" . implode(',', $all_product_ids) . ")
							AND
							term_taxonomy_id IN (" . implode(',', $all_children_ids) . ")
						)) term_{$term->term_taxonomy_id}
					");
				}

				$final_query = "
					SELECT " . implode(', ', $select_components) . "
					FROM " . implode(', ', $subqueries) . ";";

				$children_results = $wpdb->get_results($final_query, ARRAY_A);

				if ($children_results && isset($children_results[0])) {
					foreach ($children_results[0] as $key => $value) {
						$counts[intval(str_replace('term_', '', $key))] = (object) [
							'term_id' => str_replace('term_', '', $key),
							'term_count' => $value
						];
					}
				}
			}
		}

		foreach ($terms as $term) {
			$term_count = 0;

			if (isset($counts[$term->term_id])) {
				$term_count = intval($counts[$term->term_id]->term_count);
			}

			if (isset($counts[$term->term_taxonomy_id])) {
				$term_count = intval($counts[$term->term_taxonomy_id]->term_count);
			}

			if (count($term->children) > 0) {
				$term->children = $this->get_terms_with_counts(
					$term->children,
					$counts
				);
			}

			$term->count = $term_count;

			$terms_with_counts[] = $term;
		}

		return $terms_with_counts;
	}
}
