<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectorateController extends Controller
{
    public function index()
    {
        $directorates = DB::table('directorate')->orderBy('displayorder', 'asc')->get();
        
        $directorates->transform(function ($member) {
            $member->image_url = $member->image ? asset('images/directorate/' . $member->image) : null;
            return $member;
        });

        return response()->json($directorates);
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
            $file->move(public_path('images/directorate'), $filename);
            $data['image'] = $filename;
        }

        DB::table('directorate')->insert($data);
        return response()->json(['message' => 'Directorate created successfully'], 201);
    }

    public function show(string $id)
    {
        $dir = DB::table('directorate')->where('id', $id)->first();
        if (!$dir) return response()->json(['message' => 'Not found'], 404);
        return response()->json($dir);
    }

    public function update(Request $request, string $id)
    {
        $dir = DB::table('directorate')->where('id', $id)->first();
        if (!$dir) return response()->json(['message' => 'Not found'], 404);

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
            $file->move(public_path('images/directorate'), $filename);
            $data['image'] = $filename;

            if ($dir->image && file_exists(public_path('images/directorate/' . $dir->image))) {
                unlink(public_path('images/directorate/' . $dir->image));
            }
        }

        unset($data['_method']);

        DB::table('directorate')->where('id', $id)->update($data);
        return response()->json(['message' => 'Directorate updated successfully']);
    }

    public function destroy(string $id)
    {
        $dir = DB::table('directorate')->where('id', $id)->first();
        if (!$dir) return response()->json(['message' => 'Not found'], 404);

        if ($dir->image && file_exists(public_path('images/directorate/' . $dir->image))) {
            unlink(public_path('images/directorate/' . $dir->image));
        }

        DB::table('directorate')->where('id', $id)->delete();
        return response()->json(['message' => 'Directorate deleted successfully']);
    }
}
