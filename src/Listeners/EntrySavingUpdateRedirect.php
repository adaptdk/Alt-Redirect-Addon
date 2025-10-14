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
		$newSlug = $entry->slug();
		$originalSlug = $entry->getOriginal()['slug'];

		if ($originalSlug !== $newSlug) {
			Redirect::$oldSlugs[$originalSlug] = $newSlug;
		}
	}
}
