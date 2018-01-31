<?php

namespace App\Http\Middleware;

use Closure;

class Cors
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     *
     * @return mixed
     */
public function handle($request, Closure $next)
{

	// return $next($request)
	// ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
	// ->header('Access-Control-Max-Age', '10000')
	// ->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, useXDomain, withCredentials');

	$response = $next($request);

	$response->headers->set('Access-Control-Max-Age', '10000');
	$response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
	$response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, Accept, Authorization, useXDomain, withCredentials');

	return $response;

}
}