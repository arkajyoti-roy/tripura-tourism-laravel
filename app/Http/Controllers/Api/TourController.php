<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TourController extends Controller
{
    public function index()
    {
        $tours = DB::table('tour')->orderBy('order', 'asc')->orderBy('id', 'asc')->get();
        return response()->json($tours);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'accommodation' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'folder' => 'nullable|string|max:255',
            'destype' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'map' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image2' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image3' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image4' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image5' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image6' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image7' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
        ]);

        $data = $request->except(['image', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7']);
        $folder = $request->input('folder', '');
        $destinationPath = $folder ? public_path('images/tour/' . trim($folder)) : public_path('images/tour');

        foreach (['image', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7'] as $imgField) {
            if ($request->hasFile($imgField)) {
                $file = $request->file($imgField);
                $filename = rand(99999, 9999999) . '.' . $file->getClientOriginalExtension();
                $file->move($destinationPath, $filename);
                $data[$imgField] = $filename;
            }
        }

        DB::table('tour')->insert($data);
        return response()->json(['message' => 'Tour created successfully'], 201);
    }

    public function show(string $id)
    {
        $tour = DB::table('tour')->where('id', $id)->first();
        if (!$tour) return response()->json(['message' => 'Not found'], 404);
        return response()->json($tour);
    }

    public function update(Request $request, string $id)
    {
        $tour = DB::table('tour')->where('id', $id)->first();
        if (!$tour) return response()->json(['message' => 'Not found'], 404);

        $request->validate([
            'order' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'accommodation' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'folder' => 'nullable|string|max:255',
            'destype' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'map' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image2' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image3' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image4' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image5' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image6' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
            'image7' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
        ]);

        $data = $request->except(['image', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7']);
        $folder = $request->input('folder') ?? ($tour->folder ?? '');
        $destinationPath = $folder ? public_path('images/tour/' . trim($folder)) : public_path('images/tour');

        foreach (['image', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7'] as $imgField) {
            if ($request->hasFile($imgField)) {
                $file = $request->file($imgField);
                $filename = rand(99999, 9999999) . '.' . $file->getClientOriginalExtension();
                $file->move($destinationPath, $filename);
                $data[$imgField] = $filename;

                $oldFolder = $tour->folder ?? '';
                $oldFilePath = $oldFolder ? public_path('images/tour/' . $oldFolder . '/' . $tour->$imgField) : public_path('images/tour/' . $tour->$imgField);
                if ($tour->$imgField && file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
        }

        DB::table('tour')->where('id', $id)->update($data);
        return response()->json(['message' => 'Tour updated successfully']);
    }

    public function destroy(string $id)
    {
        $tour = DB::table('tour')->where('id', $id)->first();
        if (!$tour) return response()->json(['message' => 'Not found'], 404);

        $folder = $tour->folder ?? '';
        foreach (['image', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7'] as $imgField) {
            $filePath = $folder ? public_path('images/tour/' . $folder . '/' . $tour->$imgField) : public_path('images/tour/' . $tour->$imgField);
            if ($tour->$imgField && file_exists($filePath)) {
                unlink($filePath);
            }
        }

        DB::table('tour')->where('id', $id)->delete();
        return response()->json(['message' => 'Tour deleted successfully']);
    }

    public function toggleHome(string $id)
    {
        $tour = DB::table('tour')->where('id', $id)->first();
        if (!$tour) return response()->json(['message' => 'Not found'], 404);

        $newVal = ($tour->show_on_homepage == 1) ? 0 : 1;
        DB::table('tour')->where('id', $id)->update(['show_on_homepage' => $newVal]);

        return response()->json(['message' => 'Visibility updated successfully', 'show_on_homepage' => $newVal]);
    }
}
