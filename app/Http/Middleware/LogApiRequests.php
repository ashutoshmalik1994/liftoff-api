<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiLog;
use Illuminate\Support\Facades\Auth;

class LogApiRequests
{
    public function handle(Request $request, Closure $next)
    {
        // Let the request continue
        $response = $next($request);

        // Save API log after response is generated
      ApiLog::create([
        'user_id'       => auth()->check() ? auth()->id() : null, // will be null if unauthenticated
        'method' => $request->method(),
        'url' => $request->fullUrl(),
        'request_data' => json_encode($request->all()),
        'response_data' => json_encode($response->getContent()),
        'status_code' => $response->status(),
    ]);

        return $response;
    }
}
