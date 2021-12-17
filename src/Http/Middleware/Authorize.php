<?php

namespace Eightbitsnl\NovaReports\Http\Middleware;

use Laravel\Nova\Nova;

class Authorize
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle($request, $next)
    {
		if( config('nova-reports.webview.enabled') == false)
			throw new \Exception('Webviews are disabled. Please enable it in config/nova-reports.php');
			
		return $next($request);
    }

    /**
     * Determine whether this tool belongs to the package.
     *
     * @param \Laravel\Nova\Tool $tool
     * @return bool
     */
    public function matchesTool($tool)
    {
        // return $tool instanceof QuerybuilderField;
    }
}