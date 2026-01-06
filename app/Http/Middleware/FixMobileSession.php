<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class FixMobileSession
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if this is a mobile browser
        $userAgent = $request->header('User-Agent', '');
        $isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent);
        
        if ($isMobile) {
            // For mobile browsers, ensure session cookies work properly
            if ($response instanceof \Illuminate\Http\Response || $response instanceof \Illuminate\Http\JsonResponse) {
                // Add headers to help with mobile session handling
                $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                $response->header('Pragma', 'no-cache');
                $response->header('Expires', '0');
                
                // Ensure CSRF token is always included in JSON responses for mobile
                if ($request->expectsJson() || $request->isXmlHttpRequest()) {
                    if ($response instanceof \Illuminate\Http\JsonResponse) {
                        $data = $response->getData(true);
                        if (!isset($data['_token'])) {
                            $data['_token'] = csrf_token();
                            $response->setData($data);
                        }
                    }
                }
            }
        }

        return $response;
    }
}