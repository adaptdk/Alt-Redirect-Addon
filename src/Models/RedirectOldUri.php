<?php

namespace AltDesign\AltRedirect\Models;

use Illuminate\Database\Eloquent\Model;

class RedirectOldUri extends  Model
{
	protected $fillable = [
		'entry_id',
		'uri',
	];

	public static function make(string $entryId, string $uri): RedirectOldUri
	{
		$oldRedirectUri = new RedirectOldUri();

		$oldRedirectUri->fill([
			'entry_id' => $entryId,
			'uri' => $uri,
		]);

		return $oldRedirectUri;
	}

	public static function getByEntryId(string $entryId): ?RedirectOldUri
	{
		return self::query()->where('entry_id', $entryId)->first();
	}
}
