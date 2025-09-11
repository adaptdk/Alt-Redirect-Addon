<?php

namespace AltDesign\AltRedirect\Http\Controllers;

use AltDesign\AltRedirect\Helpers\Data;
use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Http\Request;
use Statamic\Fields\BlueprintRepository;
use Throwable;

class AltRedirectController
{
    private string $type = 'redirects';
    private array $actions = [
        'redirects' => 'alt-redirect.create',
        'query-strings' => 'alt-redirect.query-strings.create'
    ];
    private array $titles = [
        'redirects' => 'Alt Redirect',
        'query-strings' => 'Alt Redirect - Query Strings'
    ];

    private array $instructions = [
        'redirects' => 'Manage your redirects here. For detailed instructions, please consult the Alt Redirect Readme',
        'query-strings' => 'Alt Redirect can strip query strings from your URIs before they are processed. These are listed below, add the key for query strings you want strip',
    ];

    // Work out what page we're handling
    public function __construct()
    {
        $path = request()->path();
        if (str_contains($path, 'query-strings')) {
            $this->type = 'query-strings';
        }
    }

    public function index()
    {
        $redirects =  Redirect::all()->toArray();

        // Get a blueprint.So
        $blueprint = with(new BlueprintRepository)->setDirectory(__DIR__.'/../../../resources/blueprints')->find($this->type);
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
            'type' => $this->type,
            'action' => $this->actions[$this->type],
            'title' => $this->titles[$this->type],
            'instructions' => $this->instructions[$this->type],
        ]);
    }

    public function create(Request $request)
    {
        // Get a blueprint.
        $blueprint = with(new BlueprintRepository)->setDirectory(__DIR__.'/../../../resources/blueprints')->find($this->type);

        // Get a Fields object
		/** @var \Statamic\Fields\Fields $fields */
        $fields = $blueprint->fields();
		$values = $request->all();

        // Avoid looping redirects (caught by validation, but give a more helpful error)
        if (($this->type == 'redirects') && ($values['to'] === $values['from'])) {
            $response = [
                'message' => "'To' and 'From' addresses cannot be identical",
                'errors' => [
                    'from' => ['This field must be unique.'],
                    'to' => ['This field must be unique.'],
                ],
            ];

            return response()->json($response, 422);
        }

        $fields = $fields->addValues($values);
        $fields->validate();

        Redirect::make(
			$fields->get('from'),
			$fields->get('to'),
			$fields->get('redirect_type'),
			$fields->get('sites')
		)->save();

        return [
            'data' => Redirect::all()->toArray(),
        ];
    }

	/**
	 * @throws Throwable
	 */
	public function delete(Request $request)
    {
        $id = $request->get('id');
        Redirect::query()->find($id)->deleteOrFail();

        return [
            'data' => Redirect::all()->toArray(),
        ];
    }

    // Import and Export can stay hardcoded to redirects since I/O for Query Strings aren't supported atm
    public function export(Request $request)
    {
        $redirects = Redirect::all();

        $callback = function () use ($redirects) {
            $stream = fopen('php://output', 'w');

            fputcsv($stream, ['from_md5', 'from', 'to', 'redirect_type', 'sites', 'id']);

            $redirects->each(function ($redirect) use ($stream) {
				fputcsv($stream, $redirect->toArray());
			});

            fclose($stream);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="redirects_'.date('Y-m-d\_H:i:s').'.csv"',
        ]);
    }

    public function import(Request $request)
    {
		ray($request->get('data'));
        $currentData = json_decode($request->get('data'), true);
        $file = $request->file('file');
        $handle = fopen($file->path(), 'r');
        if ($handle !== false) {
            $headers = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $temp = [
                    'from' => $row[0],
                    'to' => $row[1],
                    'redirect_type' => $row[2],
                    'sites' => !empty($row[3] ?? false) ? explode(',', $row[3]) : ['default'],
                    'id' => ! empty($row[4] ?? false) ? $row[4] : uniqid(),
                ];
                // Skip the redirect if it'll create an infinite loop (handles empty redirects too)
                if ($temp['to'] === $temp['from']) {
                    continue;
                }
                foreach ($currentData as $rdKey => $redirect) {
                    if ($redirect['id'] === $temp['id'] || $redirect['from'] === $temp['from']) {
                        $currentData[$rdKey] = $temp;

                        continue 2;
                    }
                }
                $currentData[] = $temp;
            }

            // Close the file handle
            fclose($handle);
        }
        $data = new Data('redirects');
        $data->saveAll($currentData);

    }

    // Toggle a key in a certain item and return the data afterwards
    public function toggle(Request $request)
    {
        $toggleKey =  $request->get('toggleKey');
        $index =  $request->get('index');
        $data = new Data($this->type);

        switch ($this->type) {
            case 'query-strings':
                $item = $data->getByKey('query_string', $index);
                if ($item === null) {
                    return response('Error finding item', 500);
                }

                if (!isset($item[$toggleKey])) {
                    $item[$toggleKey] = false;
                }
                $item[$toggleKey] = !$item[$toggleKey];
                $data->setAll($item);
                break;
            default:
                return response('Method not implemented', 500);
        }
        $data = new Data($this->type);
        $values = $data->all();

        return [
            'data' => $values,
        ];
    }
}
