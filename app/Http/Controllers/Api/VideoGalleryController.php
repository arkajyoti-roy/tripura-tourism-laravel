<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VideoGalleryController extends Controller
{
    public function index()
    {
        $records = DB::table('video')->orderBy('id', 'desc')->get();
        return response()->json($records);
    }

    public function store(Request $request)
    {
        $request->validate([
            'link' => 'required|string',
            'name' => 'required|string|max:255'
        ]);

        $data = [
            'link' => $request->link,
            'name' => $request->name,
        ];

        DB::table('video')->insert($data);
        return response()->json(['message' => 'Video Gallery created successfully'], 201);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'link' => 'required|string',
        ]);

        unset($data['_method']);

        DB::table('video')->where('id', $id)->update([
            'name' => $request->name,
            'link' => $request->link,
        ]);

        return response()->json(['message' => 'Video updated successfully']);
    }

    public function destroy(string $id)
    {
        DB::table('video')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
