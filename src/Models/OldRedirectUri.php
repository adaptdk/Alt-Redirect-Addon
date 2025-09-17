<?php

namespace AltDesign\AltRedirect\Models;

use Illuminate\Database\Eloquent\Model;

class OldRedirectUri extends  Model
{
	protected $fillable = [
		'entry_id',
		'uri',
	];

	public static function make(string $entryId, string $uri): OldRedirectUri
	{
		$oldRedirectUri = new OldRedirectUri();

		$oldRedirectUri->fill([
			'entry_id' => $entryId,
			'uri' => $uri,
		]);

		return $oldRedirectUri;
	}

	public static function getByEntryId(string $entryId): ?OldRedirectUri
	{
		return self::query()->where('entry_id', $entryId)->first();
	}
}
