<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhotoGalleryController extends Controller
{
    public function index()
    {
        $records = DB::table('photo')->orderBy('id', 'desc')->get()->map(function ($photo) {
            
            $photo->image_url = url('/images/galleries/' . ($photo->photo_div ?? '') . '/' . ($photo->image ?? ''));
            return $photo;
        });
        return response()->json($records);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $photo_div = time() . rand(10, 1000);
        $data = [
            'name' => $request->name,
            'photo_div' => $photo_div
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            
            
            $destinationPath = public_path('images/galleries/' . $photo_div);
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            
            $file->move($destinationPath, $filename);
            $data['image'] = $filename;
        }

        DB::table('photo')->insert($data);
        return response()->json(['message' => 'Photo Gallery created successfully'], 201);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $rec = DB::table('photo')->where('id', $id)->first();
        if (!$rec) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $data = ['name' => $request->name];

        if ($request->hasFile('image')) {
            
            $oldImagePath = public_path('images/galleries/' . $rec->photo_div . '/' . $rec->image);
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }

            $file = $request->file('image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path('images/galleries/' . $rec->photo_div);
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $file->move($destinationPath, $filename);
            $data['image'] = $filename;
        }

        DB::table('photo')->where('id', $id)->update($data);
        return response()->json(['message' => 'Photo Gallery updated successfully']);
    }

    public function destroy(string $id)
    {
        $rec = DB::table('photo')->where('id', $id)->first();
        if ($rec) {
            
            $dirPath = public_path('images/galleries/' . $rec->photo_div);
            if (is_dir($dirPath)) {
                array_map('unlink', glob("$dirPath/*.*"));
                rmdir($dirPath);
            }
            DB::table('photo')->where('id', $id)->delete();
        }
        return response()->json(['message' => 'Deleted successfully']);
    }
}
