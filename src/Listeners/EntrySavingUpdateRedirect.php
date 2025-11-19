<?php

namespace AltDesign\AltRedirect\Listeners;

use AltDesign\AltRedirect\Models\Redirect;
use Statamic\Events\EntrySaving;

class EntrySavingUpdateRedirect
{
	/**
	 * Create the event listener.
	 */
	public function __construct()
	{
		//
	}

	/**
	 * Handle the event.
	 */
	public function handle(EntrySaving $event): void
	{
		if (!config('alt-redirect.events.entry.update_redirect_to_entry', false)) {
			return;
		}

		$entry = $event->entry;
		$originalEntry =  $entry->getOriginal();

		// If there is no original entry, then it's because a entry is being created
		if (empty($originalEntry)) {
			return;
		}

		$newSlug = $entry->slug();
		$originalSlug = $originalEntry['slug'];

		$newUri = ltrim($entry->uri(), '/');

		// When STATAMIC_REVISIONS_ENABLED is enabled will $entry->uri() return the old uri. To fix this, we do a string replacement.
		if (str_ends_with($newUri, $originalSlug)) {
			$newUri = str_replace($originalSlug, $newSlug, $newUri);
		}

		$originalUri = str_replace($newSlug, $originalSlug, $newUri);

		if ($originalSlug !== $newSlug) {
			Redirect::$oldUris[$originalUri] = $newUri;
		}
	}
}
