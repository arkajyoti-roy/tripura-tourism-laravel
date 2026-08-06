<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentsController extends Controller
{
    public function index()
    {
        $records = DB::table('agents')->orderBy('displayorder', 'asc')->get();
        return response()->json($records);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order' => 'required|numeric',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'date' => 'nullable|date',
            'phone' => 'nullable|string|max:255'
        ]);

        $data = [
            'displayorder' => $request->order,
            'name' => $request->name,
            'address' => $request->address,
            'email' => $request->email,
            'validDate' => $request->date,
            'phone' => $request->phone,
        ];

        DB::table('agents')->insert($data);
        return response()->json(['message' => 'Agent created successfully'], 201);
    }

    public function show(string $id)
    {
        $rec = DB::table('agents')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);
        return response()->json($rec);
    }

    public function update(Request $request, string $id)
    {
        $rec = DB::table('agents')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);

        $request->validate([
            'order' => 'required|numeric',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'date' => 'nullable|date',
            'phone' => 'nullable|string|max:255'
        ]);

        $data = [
            'displayorder' => $request->order,
            'name' => $request->name,
            'address' => $request->address,
            'email' => $request->email,
            'validDate' => $request->date,
            'phone' => $request->phone,
        ];

        DB::table('agents')->where('id', $id)->update($data);
        return response()->json(['message' => 'Agent updated successfully']);
    }

    public function destroy(string $id)
    {
        $rec = DB::table('agents')->where('id', $id)->first();
        if (!$rec) return response()->json(['message' => 'Not found'], 404);

        DB::table('agents')->where('id', $id)->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
