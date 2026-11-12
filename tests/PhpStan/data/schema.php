<?php

return [
	'tables' => [
		'keyed' => [
			'columns' => [
				'id' => ['pk' => true],
				'name',
			],
		],
		'pivot' => [
			'columns' => [
				'keyed_id' => ['type' => 'int'],
				'label',
			],
		],
	],
];
