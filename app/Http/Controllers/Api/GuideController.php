<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuideController extends Controller
{
    public function index()
    {
        $records = DB::table('guides')->orderBy('displayorder', 'asc')->get();
        
        $records->transform(function ($guide) {
            $guide->image_url = $guide->image ? asset('images/guides/' . $guide->image) : null;
            return $guide;
        });

        return response()->json($records);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order' => 'required|numeric',
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'language' => 'required|string|max:255',
            'date' => 'required|date',
            'spot' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = [
            'displayorder' => $request->order,
            'name' => $request->name,
            'address' => $request->address,
            'language' => $request->language,
            'validDate' => $request->date,
            'spot' => $request->spot,
            'phone' => $request->phone,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/guides'), $filename);
            $data['image'] = $filename;
        }

        DB::table('guides')->insert($data);
        return response()->json(['message' => 'Guide created successfully'], 201);
    }

    public function show(string $id)
    {
        $rec = DB::table('guides')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);
        return response()->json($rec);
    }

    public function update(Request $request, string $id)
    {
        $rec = DB::table('guides')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);

        $request->validate([
            'order' => 'required|numeric',
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'language' => 'required|string|max:255',
            'date' => 'required|date',
            'spot' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = [
            'displayorder' => $request->order,
            'name' => $request->name,
            'address' => $request->address,
            'language' => $request->language,
            'validDate' => $request->date,
            'spot' => $request->spot,
            'phone' => $request->phone,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/guides'), $filename);
            $data['image'] = $filename;

            if ($rec->image && file_exists(public_path('images/guides/' . $rec->image))) {
                unlink(public_path('images/guides/' . $rec->image));
            }
        }

        DB::table('guides')->where('id', $id)->update($data);
        return response()->json(['message' => 'Guide updated successfully']);
    }

    public function destroy(string $id)
    {
        $rec = DB::table('guides')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);

        if ($rec->image && file_exists(public_path('images/guides/' . $rec->image))) {
            unlink(public_path('images/guides/' . $rec->image));
        }

        DB::table('guides')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
