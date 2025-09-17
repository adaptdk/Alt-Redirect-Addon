<?php

return [
	// The maximum length of a redirect chain
	'redirect_chain_max_length' => 100,

	'listeners' => [
		'create_redirect' => [
			'enabled' => true
		]
	],
];
