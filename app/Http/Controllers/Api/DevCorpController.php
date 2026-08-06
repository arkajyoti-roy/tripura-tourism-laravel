<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DevCorpController extends Controller
{
    public function index()
    {
        $records = DB::table('development_co')->orderBy('displayorder', 'asc')->get();
        
        $records->transform(function ($member) {
            $member->image_url = $member->image ? asset('images/development_corporation/' . $member->image) : null;
            return $member;
        });

        return response()->json($records);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order' => 'required|numeric',
            'name' => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'facebook' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = [
            'displayorder' => $request->order,
            'name' => $request->name,
            'designation' => $request->designation,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'facebook' => $request->facebook,
            'Twitter' => $request->twitter,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/development_corporation'), $filename);
            $data['image'] = $filename;
        }

        DB::table('development_co')->insert($data);
        return response()->json(['message' => 'Development Corporation Member created successfully'], 201);
    }

    public function show(string $id)
    {
        $rec = DB::table('development_co')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);
        return response()->json($rec);
    }

    public function update(Request $request, string $id)
    {
        $rec = DB::table('development_co')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);

        $request->validate([
            'order' => 'required|numeric',
            'name' => 'required|string|max:255',
            'designation' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'facebook' => 'nullable|string|max:255',
            'twitter' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = [
            'displayorder' => $request->order,
            'name' => $request->name,
            'designation' => $request->designation,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'facebook' => $request->facebook,
            'Twitter' => $request->twitter,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/development_corporation'), $filename);
            $data['image'] = $filename;

            if ($rec->image && file_exists(public_path('images/development_corporation/' . $rec->image))) {
                unlink(public_path('images/development_corporation/' . $rec->image));
            }
        }

        DB::table('development_co')->where('id', $id)->update($data);
        return response()->json(['message' => 'Development Corporation Member updated successfully']);
    }

    public function destroy(string $id)
    {
        $rec = DB::table('development_co')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);

        if ($rec->image && file_exists(public_path('images/development_corporation/' . $rec->image))) {
            unlink(public_path('images/development_corporation/' . $rec->image));
        }

        DB::table('development_co')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
