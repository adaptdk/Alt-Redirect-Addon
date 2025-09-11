<?php

namespace AltDesign\AltRedirect\Models;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model implements Arrayable
{
	use HasUuids;

	protected $primaryKey = 'id';

	protected $fillable = [
		'from_mb5',
		'from',
		'to',
		'redirect_type',
		'sites',
	];

	protected $casts = [
		'sites' => 'array',
	];

	public static function make(string $from, string $to, string $redirectType, array $sites): Redirect
	{
		$redirect = new Redirect();
		$redirect->fill([
			'from_mb5' => md5($from),
			'from' => $from,
			'to' => $to,
			'redirect_type' => $redirectType,
			'sites' => $sites,
		]);

		return $redirect;
	}

	public static function getByFrom(string $from): Redirect
	{
		return Redirect::query()->where('from', $from)->first();
	}

	public function toArray(): array
	{
		return [
			'from_mb5' => $this->from_mb5,
			'from' => $this->from,
			'to' => $this->to,
			'redirect_type' => $this->redirect_type,
			'sites' => $this->sites,
		];
	}
}
