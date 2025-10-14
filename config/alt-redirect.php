<?php

return [
	// The maximum length of a redirect chain
	'redirect_chain_max_length' => 100,

	'events' => [
		'entry' => [
			'update_redirect_to_entry' => false,
			'create_redirect_from_old_to_new_slug' => false,
		],
	],
];
