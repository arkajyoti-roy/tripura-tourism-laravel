<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestimonialController extends Controller
{
    public function index()
    {
        $items = DB::table('testimonials')->orderBy('displayorder', 'asc')->get()->map(function ($item) {
            $item->image_url = $item->image ? asset('images/testimonials/' . $item->image) : null;
            return $item;
        });
        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'testimonial' => 'required|string',
            'rating'      => 'nullable|integer|min:1|max:5',
            'displayorder'=> 'nullable|integer',
            'image_file'  => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
        ]);

        $data = [
            'name'         => $request->name,
            'designation'  => $request->designation,
            'testimonial'  => $request->testimonial,
            'rating'       => $request->rating ?? 5,
            'displayorder' => $request->displayorder ?? 0,
        ];

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/testimonials'), $filename);
            $data['image'] = $filename;
        }

        DB::table('testimonials')->insert($data);
        return response()->json(['message' => 'Testimonial created successfully'], 201);
    }

    public function show(string $id)
    {
        $item = DB::table('testimonials')->where('id', $id)->first();
        if (!$item) return response()->json(['message' => 'Not found'], 404);
        $item->image_url = $item->image ? asset('images/testimonials/' . $item->image) : null;
        return response()->json($item);
    }

    public function update(Request $request, string $id)
    {
        $item = DB::table('testimonials')->where('id', $id)->first();
        if (!$item) return response()->json(['message' => 'Not found'], 404);

        $request->validate([
            'name'        => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'testimonial' => 'required|string',
            'rating'      => 'nullable|integer|min:1|max:5',
            'displayorder'=> 'nullable|integer',
            'image_file'  => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440',
        ]);

        $data = [
            'name'         => $request->name,
            'designation'  => $request->designation,
            'testimonial'  => $request->testimonial,
            'rating'       => $request->rating ?? $item->rating,
            'displayorder' => $request->displayorder ?? $item->displayorder,
        ];

        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/testimonials'), $filename);
            $data['image'] = $filename;
            if ($item->image && file_exists(public_path('images/testimonials/' . $item->image))) {
                unlink(public_path('images/testimonials/' . $item->image));
            }
        }

        DB::table('testimonials')->where('id', $id)->update($data);
        return response()->json(['message' => 'Testimonial updated successfully']);
    }

    public function destroy(string $id)
    {
        $item = DB::table('testimonials')->where('id', $id)->first();
        if (!$item) return response()->json(['message' => 'Not found'], 404);
        if ($item->image && file_exists(public_path('images/testimonials/' . $item->image))) {
            unlink(public_path('images/testimonials/' . $item->image));
        }
        DB::table('testimonials')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
