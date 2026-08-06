<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function index()
    {
        $agents = \App\Models\Agent::orderByRaw("STR_TO_DATE(validDate, '%Y-%m-%d') DESC")->get();
        return response()->json($agents);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'validDate' => 'nullable|string',
            'displayorder' => 'nullable|integer'
        ]);

        $data = $request->all();
        $data['updateDate'] = date('Y-m-d H:i:s');
        
        $agent = \App\Models\Agent::create($data);
        return response()->json($agent, 201);
    }

    public function show(string $id)
    {
        $agent = \App\Models\Agent::findOrFail($id);
        return response()->json($agent);
    }

    public function update(Request $request, string $id)
    {
        $agent = \App\Models\Agent::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'validDate' => 'nullable|string',
            'displayorder' => 'nullable|integer'
        ]);

        $data = $request->all();
        $data['updateDate'] = date('Y-m-d H:i:s');

        $agent->update($data);
        return response()->json($agent);
    }

    public function destroy(string $id)
    {
        $agent = \App\Models\Agent::findOrFail($id);
        $agent->delete();
        return response()->json(['message' => 'Agent deleted successfully']);
    }
}
