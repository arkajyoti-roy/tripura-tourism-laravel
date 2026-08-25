<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Adventure;

class AdventureController extends Controller
{
    

    private function appendImageUrl($adventure)
    {
        $adventure->image_url = $adventure->image
            ? asset('images/adventure/' . $adventure->image)
            : null;
        return $adventure;
    }

    public function index()
    {
        $adventures = Adventure::orderBy('id', 'desc')->get();
        $adventures->transform(fn($a) => $this->appendImageUrl($a));
        return response()->json($adventures);
    }

    

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'nullable|string',
            'price' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'adventure_type' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/adventure'), $imageName);
            $data['image'] = $imageName;
        }

        $adventure = Adventure::create($data);
        $this->appendImageUrl($adventure);

        return response()->json(['message' => 'Adventure created successfully', 'data' => $adventure], 201);
    }

    

    public function show(string $id)
    {
        $adventure = Adventure::findOrFail($id);
        return response()->json($this->appendImageUrl($adventure));
    }

    

    public function update(Request $request, string $id)
    {
        $adventure = Adventure::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'location' => 'nullable|string',
            'price' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'adventure_type' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = $request->except('image');

        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/adventure'), $imageName);
            $data['image'] = $imageName;
        }

        unset($data['_method']);

        $adventure->update($data);
        $this->appendImageUrl($adventure);

        return response()->json(['message' => 'Adventure updated successfully', 'data' => $adventure]);
    }

    

    public function destroy(string $id)
    {
        $adventure = Adventure::findOrFail($id);
        $adventure->delete();

        return response()->json(['message' => 'Adventure deleted successfully']);
    }
}
