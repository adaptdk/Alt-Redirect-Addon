<?php

namespace AltDesign\AltRedirect\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Redirect extends Model
{
	protected $fillable = [
		'from_md5',
		'from',
		'to',
		'redirect_type',
		'sites',
		'is_regex',
		'temp_entry_id',
	];

	protected $casts = [
		'sites' => 'array',
	];

	public static function make(
		string $from,
		string $to,
		string $redirectType,
		array|Collection $sites,
		?bool $isRegex = false,
		?string $tempEntryId = null
	): Redirect {
		$redirect = new Redirect();
		$redirect->fill([
			'from_md5' => md5($from),
			'is_regex' => $isRegex,
			'from' => $from,
			'to' => $to,
			'redirect_type' => $redirectType,
			'sites' => $sites,
			'temp_entry_id' => $tempEntryId,
		]);

		return $redirect;
	}

	public static function getByFromMd5(string $from): ?Redirect
	{
		return Redirect::query()->where('from_md5', $from)->first();
	}

	public static function getTemporaryRedirect(string $id): ?Redirect
	{
		return Redirect::query()
			->where('temp_entry_id', $id)
			->first();
	}

	public static function getRegexRedirect(string $from): ?Redirect
	{
		return Redirect::query()
			->whereRaw('? ~ "from"', [$from])
			->where('is_regex', true)
			->first();
	}

	public static function getOverlapRegexRedirects(string $from): ?Redirect
	{
		if ($overlap = self::getRegexRedirect($from)) {
			return $overlap;
		}

		return Redirect::query()
			->whereRaw('"from" ~ ?', [$from])
			->where('is_regex', true)
			->first();
	}

	public function validateRedirect(): ?array
	{
		if ($this->to === $this->from) {
			return [
				'message' => "'To' and 'From' addresses cannot be identical",
				'errors' => [
					'from' => ['This field must be unique.'],
					'to' => ['This field must be unique.'],
				],
			];
		}

		if ($this->is_regex && $redirect = Redirect::getOverlapRegexRedirects($this->from)) {
			return [
				'message' => "Regex overlap with existing redirect: $redirect->from",
				'errors' => [
					'is_regex' => ["Regex overlap with existing redirect: $redirect->from"],
				],
			];
		}

		if (Redirect::query()->where('from', $this->to)->where('to', $this->from)->exists()) {
			return [
				'message' => "Existing redirect with opposite from and to exist creating a loop",
				'errors' => [
					'from' => ["Existing redirect with opposite from and to exist creating a loop"],
					'to' => ["Existing redirect with opposite from and to exist creating a loop"],
				],
			];
		}

		try {
			$maxDepth = config('alt_redirect.redirect_chain_max_length', 100);
			$this->getRecursiveRedirect($this, $maxDepth);
		} catch (Exception $e) {
			return [
				'message' => "Loop detected",
				'errors' => [
					'from' => ["Loop detected"],
					'to' => ["Loop detected"],
				],
			];
		}

		return null;
	}

	public static function getRecursiveRedirect(Redirect $redirect, int $maxDepth = 100, $visited = [], int $depth = 0): Redirect
	{
		$fromMd5 = md5($redirect->from);

		if (in_array($fromMd5, $visited) || $depth === $maxDepth) {
			abort(500, "Redirect loop detected");
		}

		$visited[] = $fromMd5;

		// Check if any direct redirects
		if ($nextRedirect = self::getByFromMd5(md5($redirect->to))) {
			$depth += 1;
			return self::getRecursiveRedirect($nextRedirect, $visited, $depth, $maxDepth);
		}

		// Check if any regex redirects
		if ($nextRedirect = self::getRegexRedirect($redirect->to)) {
			$redirectTo = preg_replace('#' . $nextRedirect->from . '#', $nextRedirect->to, $redirect->to);

			// check if the regex redirect, redirects back to from
			if ($redirectTo === $redirect->from) {
				abort(500, "Redirect loop detected");
			}

			// Check if any direct redirects
			if ($nextRedirect = self::getByFromMd5(md5($redirectTo))) {
				$depth += 1;
				return self::getRecursiveRedirect($nextRedirect, $visited, $depth, $maxDepth);
			}
		}

		// Check if regex loops back to self
		if ($redirect->is_regex && preg_match('#' . $redirect->from . '#', $redirect->to)) {
			abort(500, "Redirect loop detected");
		}

		return $redirect;
	}
}
