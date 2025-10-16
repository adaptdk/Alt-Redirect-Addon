<?php

return [
	// The maximum length of a redirect chain
	'redirect_chain_max_length' => 100,

	'events' => [
		'entry' => [
			// Rather or not the redirects to the updated entry should be updated to the new uri
			'update_redirect_to_entry' => false,
			// Rather or not a redirect should be created from the old uri to the new uri
			'create_redirect_from_old_to_new_uri' => false,
		],
	],
];
