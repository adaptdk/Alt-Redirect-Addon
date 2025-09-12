<?php namespace AltDesign\AltRedirect\Http\Middleware;

use AltDesign\AltRedirect\Helpers\URISupport;
use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;

use Statamic\Facades\Site;
use Statamic\Facades\YAML;

use AltDesign\AltRedirect\Helpers\Data;

class CheckForRedirects
{
	/**
	 * Handle an incoming request.
	 *
	 * @param Closure(Request): (Response) $next
	 */
	public function handle(Request $request, Closure $next, string ...$guards): Response
	{
		$path = $request->path();

		if ($redirect = Redirect::getByFromMd5(md5($path))) {
			if (in_array(Site::current(), $redirect->sites)) {
				return $this->redirectWithPreservedParams($redirect->to, $redirect->redirect_type, $request);
			}
		}

		if ($redirect = Redirect::getRegexRedirect($path)) {
			// why #? preg_match delimiter must not be alphanumeric, backslash, or NUL
			if (in_array(Site::current(), $redirect->sites) && preg_match('#' . $redirect->from . '#', $path)) {
				$redirectTo = preg_replace('#' . $redirect->from . '#', $redirect->to, $path);
				return $this->redirectWithPreservedParams($redirectTo, $redirect->redirect_type, $request);
			}
		}

		//No redirect
		return $next($request);
	}

	private function redirectWithPreservedParams(string $to, string $redirectType, Request $request): RedirectResponse|Redirector
	{
		$queryStrings = [];
		foreach ($request->all() as $key => $value) {
			$queryStrings[] = sprintf("%s=%s", $key, $value);
		}

		if ($queryStrings) {
			$to .= str_contains($to, '?') ? '&' : '?';
			$to .= implode('&', $queryStrings);
		}

		return redirect($to, $redirectType, config('alt-redirect.headers', []));
	}
}

