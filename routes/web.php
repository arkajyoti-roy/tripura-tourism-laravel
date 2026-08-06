<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group.
|
*/

Route::get('/backend-status', function () {
    return view('backend');
});

Route::get('/{any?}', function ($any = null) {
    if ($any && $any !== 'index.html') {
        $filePath = public_path('tourism/' . $any);
        if (!file_exists($filePath) || is_dir($filePath)) {
            $filePath = public_path($any);
        }
        
        if (file_exists($filePath) && !is_dir($filePath)) {
            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
            $mimes = [
                'js' => 'application/javascript',
                'css' => 'text/css',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'webp' => 'image/webp',
                'ico' => 'image/x-icon',
                'mp4' => 'video/mp4',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'json' => 'application/json',
                'pdf' => 'application/pdf',
            ];
            $mime = $mimes[$ext] ?? mime_content_type($filePath);
            return response()->file($filePath, ['Content-Type' => $mime]);
        }
    }
    
    $path = public_path('tourism/index.html');
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $content = preg_replace('/<base\s+href="[^"]*">/', '<base href="/">', $content);
        return response($content)->header('Content-Type', 'text/html');
    }
    
    return view('backend');
})->where('any', '^(?!api).*$');
