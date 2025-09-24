<?php namespace AltDesign\AltRedirect\Http\Middleware;

use AltDesign\AltRedirect\Helpers\URISupport;
use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;
use Statamic\Facades\Site;

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

		if (!$request->isMethod('GET')) {
			//No redirect
			return $next($request);
		}

		$redirect = Redirect::getByFromMd5(md5($path)) ?: Redirect::getRegexRedirect($path);

		if ($redirect) {
			if (in_array(Site::current(), $redirect->sites)) {
				$maxDepth = config('alt_redirect.redirect_chain_max_length', 100);
				$redirect = Redirect::getRecursiveRedirect($redirect, $maxDepth);
				return $this->redirectWithPreservedParams($redirect, $request);
			}
		}

		//No redirect
		return $next($request);
	}

	private function redirectWithPreservedParams(Redirect $redirect, Request $request): RedirectResponse|Redirector
	{
		$to = $redirect->to;

		if ($redirect->is_regex) {
			// why #? preg_match delimiter must not be alphanumeric, backslash, or NUL
			$to = preg_replace('#' . $redirect->from . '#', $to, $request->path());
		}

		$queryStrings = [];
		foreach ($request->all() as $key => $value) {
			$queryStrings[] = sprintf("%s=%s", $key, $value);
		}

		if ($queryStrings) {
			$to .= str_contains($to, '?') ? '&' : '?';
			$to .= implode('&', $queryStrings);
		}

		return redirect($to, $redirect->redirect_type, config('alt-redirect.headers', []));
	}
}

