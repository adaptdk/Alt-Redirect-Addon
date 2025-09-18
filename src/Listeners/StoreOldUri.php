<?php

namespace AltDesign\AltRedirect\Listeners;

use AltDesign\AltRedirect\Models\RedirectOldUri;
use Illuminate\Support\Facades\Log;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;

class StoreOldUri
{
	public function handle(EntrySaving $event): void
	{
		// if (! config('alt-redirect.listeners.create_redirect.enabled', false)) {
		// 	return;
		// }

		if (!$event->entry->id()) {
			return;
		}

		$this->cacheEntryUri($event->entry->id());
	}

	protected function cacheEntryUri(string $entryId): void
	{
		$entry = Entry::find($entryId);

		if (!$entry) {
			return;
		}

		if (!$uri = $entry->uri()) {
			return;
		}

		if (!$entry->published()) {
			return;
		}

		// Delete existing temporary redirect
		RedirectOldUri::getByEntryId($entry->id())?->delete();
		Log::info($entry->id());
		Log::info($uri);
		$redirectOldUri = RedirectOldUri::make($entry->id(), $uri);
		$redirectOldUri->save();
    }
}
