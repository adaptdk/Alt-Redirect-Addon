<?php

namespace AltDesign\AltRedirect\Listeners;

use AltDesign\AltRedirect\Models\RedirectOldUri;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry;

class StoreOldUri
{
	public function handle(EntrySaving|CollectionTreeSaving $event): void
	{
		// if (! config('alt-redirect.listeners.create_redirect.enabled', false)) {
		// 	return;
		// }
		if ($event instanceof EntrySaving) {
			if (!$event->entry->id()) {
				return;
			}

			$this->cacheEntryUri($event->entry->id());
			return;
		}

		/** @var \Statamic\Structures\CollectionTreeDiff $diff */
		$diff = $event->tree->diff();

		foreach ($diff->affected() as $entry) {
			$this->cacheEntryUri($entry);
		}
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
		$redirectOldUri = RedirectOldUri::make($entry->id(), $uri);
		$redirectOldUri->save();
    }
}
