<?php

namespace AltDesign\AltRedirect\Listeners;

use AltDesign\AltRedirect\Models\Redirect;
use AltDesign\AltRedirect\RedirectType;
use Statamic\Events\EntrySaving;

class EntrySavedUpdateRedirect
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

		foreach (Redirect::$oldSlugs as $originalSlug => $newSlug) {
			// Update all that redirects to original slug
			Redirect::query()
				->where('to', $originalSlug)
				->update(['to' => $newSlug]);

			// Update all the original slugs redirects
			Redirect::query()
				->where('from', $originalSlug)
				->update(['from' => $newSlug]);

			if (config('alt-redirect.events.entry.create_redirect_from_old_to_new_slug', false)) {
				// Remove all loops created potentially created by the "from change above
				Redirect::query()
					->whereColumn('from', 'to')
					->delete();

				// if we redirect for the new slug already exist, we delete it
				Redirect::query()
					->where('from', $newSlug)
					->delete();

				// Create redirect from the original slug to new slug
				Redirect::query()
					->insert([
						'from' => $originalSlug,
						'from_md5' => md5($originalSlug),
						'to' => $newSlug,
						'redirect_type' => RedirectType::MOVED_PERMANENTLY->value,
						'sites' => $event->entry->sites(),
						'is_regex' => false,
					]);
			}

			unset(Redirect::$oldSlugs[$originalSlug]);
		}
	}
}
