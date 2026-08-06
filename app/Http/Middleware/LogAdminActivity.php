<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    // Handle an incoming request.
    // @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
    public function handle(Request $request, Closure $next): Response
    {
        // Resolve active operator email from token name or fall back to primary admin email before executing request
        $operatorEmail = null;
        $user = null;

        if ($request->is('api/admin/*') && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Retrieve authenticated user using bearer token directly
            $token = $request->bearerToken();
            if ($token) {
                $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($pat && $pat->tokenable) {
                    $user = $pat->tokenable;
                    $operatorEmail = $request->header('X-Operator-Email') ?: $user->email;
                }
            }
        }

        $response = $next($request);

        if ($user) {
            $method = strtoupper($request->method());
            $resource = $request->path();
            
            // Extract section name from resource path
            $section = 'system';
            if (preg_match('/api\/admin\/([^\/]+)/', $resource, $matches)) {
                $section = str_replace('-', ' ', $matches[1]);
            }
            if ($section === 'development-corporation') {
                $section = 'dev corp';
            }

            // Determine specific action type based on changes made
            $payload = $request->except(['password', 'password_confirmation', 'image_file', 'image', 'image2', 'image3', '_method']);
            $actionType = 'modified entry';
            
            if ($method === 'POST') {
                $actionType = 'create a new entry';
            } elseif ($method === 'DELETE') {
                $actionType = 'delete existing entry';
            } elseif ($method === 'PUT' || $method === 'PATCH') {
                if ($request->hasFile('image_file') || $request->hasFile('image') || $request->hasFile('image2') || $request->hasFile('image3')) {
                    $actionType = 'change image';
                } elseif ($request->has('description') && count($payload) === 1) {
                    $actionType = 'change description';
                } elseif ($request->has('displayorder') && count($payload) === 1) {
                    $actionType = 'change order';
                } elseif ($request->has('link') && count($payload) === 1) {
                    $actionType = 'change link';
                } else {
                    $actionType = 'modified entry';
                }
            }

            // Format payload parameters into clean plain text list instead of json
            $detailsList = [];
            foreach ($payload as $key => $value) {
                if (is_array($value)) {
                    $detailsList[] = "$key: " . json_encode($value);
                } else {
                    $detailsList[] = "$key: $value";
                }
            }
            if ($request->hasFile('image_file') || $request->hasFile('image') || $request->hasFile('image2') || $request->hasFile('image3')) {
                $detailsList[] = "image: [Uploaded New File]";
            }
            
            $details = count($detailsList) > 0 ? implode(" | ", $detailsList) : 'No payload parameters sent';

            // Insert into activity_logs table
            DB::table('activity_logs')->insert([
                'admin_id' => $user->id,
                'operator_email' => $operatorEmail,
                'action_type' => $actionType,
                'section' => $section,
                'resource' => $resource,
                'details' => $details,
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return $response;
    }
}
// Remove trailing block
