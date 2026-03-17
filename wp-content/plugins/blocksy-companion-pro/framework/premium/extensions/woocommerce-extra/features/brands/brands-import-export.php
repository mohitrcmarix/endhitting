<?php

namespace Blocksy\Extensions\WoocommerceExtra;

class BrandsImportExport {
	public function __construct() {
		add_filter('woocommerce_product_export_column_names', [$this, 'add_columns']);
		add_filter('woocommerce_product_export_product_default_columns', [$this, 'add_columns']);
		add_filter('woocommerce_product_export_product_column_blocksy_product_brands', [$this, 'export_taxonomy'], 10, 2);

		add_filter('woocommerce_csv_product_import_mapping_options', [$this, 'map_columns']);
		add_filter('woocommerce_csv_product_import_mapping_default_columns', [$this, 'add_columns_to_mapping_screen']);
		add_filter('woocommerce_product_import_inserted_product_object', [$this, 'set_taxonomy'], 10, 2);
	}

	public function add_columns($columns) {
		$columns['blocksy_product_brands'] = __('Blocksy Brands', 'blocksy-companion');

		return $columns;
	}

	public function export_taxonomy($value, $product) {
		$terms = get_terms([
			'object_ids' => $product->get_id(),
			'taxonomy' => 'product_brands'
		]);

		if (! is_wp_error($terms)) {
			$data = [];

			foreach ($terms as $term) {
				$data[] = $term->name;
			}

			$value = implode(',', $data);
		}

		return $value;
	}

	public function map_columns($columns) {
		$columns['blocksy_product_brands'] = __('Blocksy Brands', 'blocksy-companion');

		return $columns;
	}

	public function add_columns_to_mapping_screen($columns) {
		$columns[__('Blocksy Brands', 'blocksy-companion')] = 'blocksy_product_brands';

		return $columns;
	}

	public function set_taxonomy($product, $data) {
		if (! $product instanceof \WC_Product) {
			return $product;
		}

		if (empty($data['blocksy_product_brands'])) {
			return $product;
		}

		wp_delete_object_term_relationships($product->get_id(), 'product_brands');

		$product_brands = explode(',', $data['blocksy_product_brands']);
		$terms = [];

		foreach ($product_brands as $brand) {
			if (! current_user_can('manage_product_terms')) {
				break;
			}

			$term = wp_insert_term($brand, 'product_brands');

			if ( is_wp_error( $term ) ) {
				if ( $term->get_error_code() === 'term_exists' ) {
					$term_id = $term->get_error_data();
				} else {
					break;
				}
			} else {
				$term_id = $term['term_id'];
			}

			$terms[] = $term_id;
		}

		wp_set_object_terms($product->get_id(), $terms, 'product_brands');
	}
}
