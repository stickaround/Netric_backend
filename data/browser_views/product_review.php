<?php
/**
 * Return browser views for entity of object type 'product_review'
 */
namespace data\browser_views;

use Netric\EntityQuery\Where;
use Netric\EntityDefinition\ObjectTypes;

return [
    'obj_type' => ObjectTypes::PRODUCT_REVIEW,
    'views' => [
				'default'=> [
					'name' => 'Default View',
					'description' => 'All Product Reviews',
					'default' => true,
					'filter_fields' => ['creator_id', 'rating'],
					'order_by' => [
						'name' => [
								'field_name' => 'name',
								'direction' => 'desc',
							],
					],
					'table_columns' => ['name', 'creator_id', 'rating', 'ts_updated', 'ts_entered']
			],
		]
];
