<?php

namespace AltDesign\AltRedirect\Listeners;

use AltDesign\AltRedirect\Models\OldRedirectUri;
use AltDesign\AltRedirect\Models\Redirect;
use AltDesign\AltRedirect\RedirectType;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Support\Arr;

class CreateRedirect
{
	public function handle(CollectionTreeSaved|EntrySaved $event): void
	{
		// if (! config('alt-redirect.listeners.create_redirect.enabled', false)) {
		//     return;
		// }

		$oldRedirectUris = OldRedirectUri::all();

		foreach ($oldRedirectUris as $oldRedirectUri) {
			$entry = EntryFacade::query()->find($oldRedirectUri->entry_id);
			$oldUri = $oldRedirectUri->uri;

			if (!$entry->uri()) {
				continue;
			}

			if ($oldUri === $entry->uri()) {
				continue;
			}

			Redirect::query()->where('from', $entry->uri())->delete();
			Redirect::query()->where('from', $oldUri)->delete();

			$redirect = Redirect::make(
				from: $oldUri,
				to: $entry->uri(),
				redirectType: RedirectType::MOVED_PERMANENTLY->value,
				sites: $entry->sites()
			);

			if ($redirect->validateRedirect()) {
				continue;
			}

			$redirect->save();
		}

		OldRedirectUri::query()->truncate();
	}
}
