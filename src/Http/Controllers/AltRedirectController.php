<?php

namespace AltDesign\AltRedirect\Http\Controllers;

use AltDesign\AltRedirect\Helpers\Data;
use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Statamic\Fields\BlueprintRepository;
use Statamic\Fields\Fields;
use Throwable;

class AltRedirectController
{
	const BLUEPRINT = 'redirects';

	public function index()
	{
		$redirects = Redirect::all()->toArray();

		// Get a blueprint.So
		$blueprint = with(new BlueprintRepository())->setDirectory(__DIR__ . '/../../../resources/blueprints')->find(self::BLUEPRINT);
		// Get a Fields object
		$fields = $blueprint->fields();
		// Add the values to the object
		$fields = $fields->addValues($redirects);
		// Pre-process the values.
		$fields = $fields->preProcess();

		return view('alt-redirect::index', [
			'blueprint' => $blueprint->toPublishArray(),
			'values' => $fields->values(),
			'meta' => $fields->meta(),
			'data' => $redirects,
			'action' => 'alt-redirect.create',
			'title' => 'Redirect',
			'instructions' => 'Manage your redirects here.',
		]);
	}

	public function create(Request $request)
	{
		// Get a blueprint.
		$blueprint = with(new BlueprintRepository())->setDirectory(__DIR__ . '/../../../resources/blueprints')->find(self::BLUEPRINT);

		// Get a Fields object
		/** @var Fields $fields */
		$fields = $blueprint->fields();
		$values = $request->all();

		$fields = $fields->addValues($values);
		$fields->validate();

		$fromMd5 = md5($request->get('from'));
		$redirect = Redirect::make(
			from: $request->get('from'),
			to: $request->get('to'),
			redirectType: $request->get('redirect_type'),
			sites: $request->get('sites'),
			isRegex: $request->get('is_regex'),
		);

		if ($message = $redirect->validateRedirect()) {
			return response()->json($message, 422);
		}

		Redirect::query()->updateOrCreate(['from_md5' => $fromMd5], $redirect->toArray());

		return [
			'data' => Redirect::all()->toArray(),
			'values' => $values,
		];
	}

	/**
	 * @throws Throwable
	 */
	public function delete(Request $request)
	{
		$id = $request->get('id');
		Redirect::query()->find($id)?->delete();

		return [
			'data' => Redirect::all()->toArray(),
		];
	}

	// Import and Export can stay hardcoded to redirects since I/O for Query Strings aren't supported atm
	public function export(Request $request)
	{
		$callback = function () {
			$stream = fopen('php://output', 'w');

			fputcsv($stream, ['from', 'to', 'redirect_type', 'sites', 'is_regex']);

			Redirect::query()->chunk(100, function ($redirects) use ($stream) {
				foreach ($redirects as $redirect) {
					fputcsv($stream, [
						$redirect->from,
						$redirect->to,
						$redirect->redirect_type,
						implode(',', $redirect->sites),
						$redirect->is_regex,
					]);
				}
			});

			fclose($stream);
		};

		return response()->stream($callback, 200, [
			'Content-Type' => 'text/csv',
			'Content-Disposition' => 'attachment; filename="redirects_' . date('Y-m-d\_H:i:s') . '.csv"',
		]);
	}

	/**
	 * @throws ValidationException
	 */
	public function import(Request $request)
	{
		$file = $request->file('file');
		$redirects = $this->redirectCsvToArray($file);

		$redirects = Validator::make($redirects, [
			'*.from' => ['required', 'string'],
			'*.to' => ['required', 'string'],
			'*.redirect_type' => ['required', 'string', Rule::in(['301', '302', '307', '308'])],
			'*.sites' => ['required', 'array'],
		])->validate();

		return DB::transaction(function () use ($redirects) {
			foreach ($redirects as $key => $redirect) {
				$fromMd5 = md5($redirect['from']);
				$redirect = Redirect::make(
					from: $redirect['from'],
					to: $redirect['to'],
					redirectType: $redirect['redirect_type'],
					sites: $redirect['sites'],
					isRegex: $redirect['is_regex'],
				);

				if ($message = $redirect->validateRedirect()) {
					DB::rollBack();
					return response()->json([$key => $message], 422);
				}

				Redirect::query()->updateOrCreate(['from_md5' => $fromMd5], $redirect->toArray());
			}

			DB::commit();
			return [
				'data' => Redirect::all()->toArray(),
			];
		});
	}

	private function redirectCsvToArray($file): array
	{
		$handle = fopen($file->path(), 'r');
		$redirects = [];

		if ($handle !== false) {
			$headers = fgetcsv($handle);
			while (($row = fgetcsv($handle)) !== false) {
				$redirect = [
					'from' => $row[1],
					'to' => $row[2],
					'redirect_type' => $row[3],
					'sites' => !empty($row[4] ?? false) ? explode(',', $row[4]) : ['default'],
				];
				// Skip the redirect if it'll create an infinite loop (handles empty redirects too)
				if ($redirect['to'] === $redirect['from']) {
					continue;
				}

				$redirects[] = $redirect;
			}

			// Close the file handle
			fclose($handle);
		}

		return $redirects;
	}
}
