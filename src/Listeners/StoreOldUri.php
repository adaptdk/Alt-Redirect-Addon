<?php

namespace AltDesign\AltRedirect\Listeners;

use AltDesign\AltRedirect\Models\OldRedirectUri;
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

		// ray($event)->orange();

		if ($event instanceof EntrySaving) {
			// ray('we in')->orange();
			if (!$event->entry->id()) {
				// ray('No Id')->orange();
				return;
			}

			$this->cacheEntryUri($event->entry->id());

			return;
		}

		/** @var \Statamic\Structures\CollectionTreeDiff $diff */
		$diff = $event->tree->diff();
		// ray('Diff', $diff)->orange();

		foreach ($diff->affected() as $entry) {
			$this->cacheEntryUri($entry);
		}
	}

	protected function cacheEntryUri(string $entryId): void
	{
		$entry = Entry::find($entryId);


		if (!$entry) {
			// ray('No entry')->orange();
			return;
		}

		if (!$uri = $entry->uri()) {
			// ray('No uri')->orange();
			return;
		}

		if (!$entry->published()) {
			// ray('No published')->orange();
			return;
		}

		// Delete existing temporary redirect
		// ray('Delete existing temp')->orange();
		OldRedirectUri::getByEntryId($entryId)?->delete();
		OldRedirectUri::make($entryId, $uri);
    }
}
