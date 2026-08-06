<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function track(Request $request)
    {
        $ip = $request->ip();

        $visitedToday = Visitor::where('ip_address', $ip)
            ->whereDate('created_at', today())
            ->exists();

        if (!$visitedToday) {
            Visitor::create(['ip_address' => $ip]);
            \Illuminate\Support\Facades\DB::table('visitor_counter')->where('id', 1)->increment('count');
        }

        $count = \Illuminate\Support\Facades\DB::table('visitor_counter')->where('id', 1)->value('count') ?? 11225460;
        return response()->json(['count' => $count]);
    }

    public function count()
    {
        $count = \Illuminate\Support\Facades\DB::table('visitor_counter')->where('id', 1)->value('count') ?? 11225460;
        return response()->json(['count' => $count]);
    }
}
