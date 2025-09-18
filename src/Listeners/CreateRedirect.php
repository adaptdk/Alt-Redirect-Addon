<?php

namespace AltDesign\AltRedirect\Listeners;

use AltDesign\AltRedirect\Models\RedirectOldUri;
use AltDesign\AltRedirect\Models\Redirect;
use AltDesign\AltRedirect\RedirectType;
use Illuminate\Support\Facades\Log;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Entry as EntryFacade;
use Symfony\Component\ErrorHandler\Debug;

class CreateRedirect
{
	public function handle(CollectionTreeSaved|EntrySaved $event): void
	{
		// if (! config('alt-redirect.listeners.create_redirect.enabled', false)) {
		//     return;
		// }

		if ($event instanceof EntrySaved) {
			Log::debug('EntrySaved');
		} else {
			Log::debug('CollectionTreeSaved');
		}

		$oldRedirectUris = RedirectOldUri::all();

		foreach ($oldRedirectUris as $oldRedirectUri) {
			$entry = EntryFacade::query()->find($oldRedirectUri->entry_id);
			$oldUri = $oldRedirectUri->uri;

			if (!$entry->uri()) {
				continue;
			}

			if ($oldUri === $entry->uri()) {
				continue;
			}

			Log::info($oldUri);
			Log::info($entry->uri());

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

		RedirectOldUri::query()->truncate();
	}
}
