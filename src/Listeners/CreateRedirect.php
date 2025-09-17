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

		if ($event instanceof EntrySaved) {
			$this->createRedirect($event->entry);
		}

		$entries = $this->treeToEntries($event->tree->tree());
		$this->createRedirect($entries);
    }

    protected function treeToEntries(array $tree): array
    {
        $ids = [];

        foreach ($tree as $item) {
            $ids = array_merge($ids, $this->gatherEntryIds($item));
        }

        return EntryFacade::query()->whereIn('id', $ids)->get()->all();
    }

    protected function gatherEntryIds(array $item): array
    {
        $ids = [];

        if (isset($item['entry'])) {
            $ids[] = $item['entry'];
        }

        if (! isset($item['children'])) {
            return $ids;
        }

        foreach ($item['children'] as $child) {
            $ids = array_merge($ids, $this->gatherEntryIds($child));
        }

        return $ids;
    }

    protected function createRedirect(Entry|array $entries): void
    {
        $entries = Arr::wrap($entries);

		/** @var Entry $entry */
		foreach ($entries as $entry) {
            if (!$entry->uri()) {
                continue;
            }

            if (!$oldRedirectUri = OldRedirectUri::getByEntryId($entry->id())) {
                continue;
            }

			$oldUri = $oldRedirectUri->from;
			$oldRedirectUri->delete();

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
				return;
			}

			$redirect->save();
        }
    }
}
