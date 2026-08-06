<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TouristCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('category')->orderBy('id', 'desc')->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'order' => 'nullable|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'displayorder' => $request->input('order', 0),
            'created_at' => now()->format('d-m-Y h:i:s A')
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = rand(99999, 9999999) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/slider'), $filename);
            $data['image'] = $filename;
        }

        DB::table('category')->insert($data);
        return response()->json(['message' => 'Category created successfully'], 201);
    }

    public function show(string $id)
    {
        $category = DB::table('category')->where('id', $id)->first();
        if (!$category) return response()->json(['message' => 'Not found'], 404);
        return response()->json($category);
    }

    public function update(Request $request, string $id)
    {
        $category = DB::table('category')->where('id', $id)->first();
        if (!$category) return response()->json(['message' => 'Not found'], 404);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'order' => 'nullable|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'displayorder' => $request->input('order', 0),
            'updated_at' => now()->format('d-m-Y h:i:s A')
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = rand(99999, 9999999) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/slider'), $filename);
            $data['image'] = $filename;

            
            if ($category->image && file_exists(public_path('images/slider/' . $category->image))) {
                unlink(public_path('images/slider/' . $category->image));
            }
        }

        DB::table('category')->where('id', $id)->update($data);
        return response()->json(['message' => 'Category updated successfully']);
    }

    public function destroy(string $id)
    {
        $category = DB::table('category')->where('id', $id)->first();
        if (!$category) return response()->json(['message' => 'Not found'], 404);

        if ($category->image && file_exists(public_path('images/slider/' . $category->image))) {
            unlink(public_path('images/slider/' . $category->image));
        }

        DB::table('category')->where('id', $id)->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
