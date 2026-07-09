<?php

namespace AltDesign\AltRedirect\Http\Controllers;

use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Statamic\Fields\BlueprintRepository;
use Statamic\Fields\Fields;
use Throwable;

class AltRedirectController
{
	const BLUEPRINT = 'redirects';

	public function index()
	{
		// Get a blueprint.
		$blueprint = with(new BlueprintRepository())->setDirectory(__DIR__ . '/../../../resources/blueprints')->find(self::BLUEPRINT);
		// Get a Fields object
		$fields = $blueprint->fields();
		// Add empty values for initial load
		$fields = $fields->addValues([]);
		// Pre-process the values.
		$fields = $fields->preProcess();

		return Inertia::render('alt-redirect::Index', [
			'blueprint' => $blueprint->toPublishArray(),
			'initialValues' => $fields->values()->all(),
			'initialMeta' => $fields->meta()->all(),
			'action' => cp_route('alt-redirect.create'),
			'title' => 'Redirect',
		]);
	}

	public function paginated(Request $request)
	{
		$perPage = $request->get('per_page', 10);
		$search = $request->get('search', '');

		$query = Redirect::query();

		// Apply search filter if provided
		if (!empty($search)) {
			$query->where(function ($q) use ($search) {
				$q->where('from', 'LIKE', "%{$search}%")
				  ->orWhere('to', 'LIKE', "%{$search}%")
				  ->orWhere('redirect_type', 'LIKE', "%{$search}%");
			});
		}

		$redirects = $query->paginate($perPage);

		return response()->json([
			'data' => $redirects->items(),
			'current_page' => $redirects->currentPage(),
			'last_page' => $redirects->lastPage(),
			'per_page' => $redirects->perPage(),
			'total' => $redirects->total(),
			'from' => $redirects->firstItem(),
			'to' => $redirects->lastItem(),
		]);
	}

	public function create(Request $request)
	{
		// Get a blueprint.
		$blueprint = with(new BlueprintRepository())->setDirectory(__DIR__ . '/../../../resources/blueprints')->find(self::BLUEPRINT);

		// Get a Fields object
		/** @var Fields $fields */
		$fields = $blueprint->fields();

		$fields = $fields->addValues($request->all());
		$fields->validate();

		// Post-process values through fieldtypes for storage
		$processedValues = $fields->process()->values()->all();

		$fromMd5 = md5($processedValues['from']);
		$redirect = Redirect::make(
			from: $processedValues['from'],
			to: $processedValues['to'],
			redirectType: $processedValues['redirect_type'],
			sites: $processedValues['sites'],
			isRegex: $processedValues['is_regex'] ?? false,
		);

		if ($message = $redirect->validateRedirect()) {
			throw ValidationException::withMessages($message['errors']);
		}

		Redirect::query()->updateOrCreate(['from_md5' => $fromMd5], $redirect->toArray());
	}

	/**
	 * @throws Throwable
	 */
	public function delete(Request $request)
	{
		$id = $request->get('id');
		Redirect::query()->find($id)?->delete();

		return redirect()->back();
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
						$redirect->is_regex ? 1 : 0,
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
			'*.is_regex' => ['required', 'bool'],
		])->validate();

		DB::transaction(function () use ($redirects) {
			foreach ($redirects as $redirect) {
				$fromMd5 = md5($redirect['from']);
				$redirectModel = Redirect::make(
					from: $redirect['from'],
					to: $redirect['to'],
					redirectType: $redirect['redirect_type'],
					sites: $redirect['sites'],
					isRegex: $redirect['is_regex'],
				);

				if ($message = $redirectModel->validateRedirect()) {
					throw ValidationException::withMessages($message['errors']);
				}

				Redirect::query()->updateOrCreate(['from_md5' => $fromMd5], $redirectModel->toArray());
			}
		});

		return redirect()->back();
	}

	private function redirectCsvToArray($file): array
	{
		$handle = fopen($file->path(), 'r');
		$redirects = [];

		if ($handle !== false) {
			$headers = fgetcsv($handle, escape: "");
			while (($row = fgetcsv($handle, escape: "")) !== false) {
				$redirect = [
					'from' => $row[0],
					'to' => $row[1],
					'redirect_type' => $row[2],
					'sites' => !empty($row[3] ?? false) ? explode(',', $row[3]) : ['default'],
					'is_regex' => $row[4],
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
