<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentsController extends Controller
{
    public function index()
    {
        $records = Agent::query()
            ->orderByRaw('displayorder IS NULL')
            ->orderBy('displayorder', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($records);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order' => 'nullable|numeric',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'phone' => 'nullable|string|max:255',
        ]);

        $now = now();

        $data = [
            // If no order was sent, place the new record after the last one.
            'displayorder' => $request->has('order')
                ? $request->order
                : Agent::nextDisplayOrder(),
            'name' => $request->name,
            'address' => $request->address,
            'email' => $request->email,
            'validDate' => $request->date,
            'is_active' => $request->has('is_active') ? (bool) $request->boolean('is_active') : null,
            'phone' => $request->phone,
            'updated_at' => $now,
        ];

        $id = DB::table('agents')->insertGetId($data);

        // Keep the legacy flow consistent: a newly written record supersedes
        // older records for the same person.
        Agent::find($id)?->deactivateOlderRecords();

        return response()->json(['message' => 'Agent created successfully'], 201);
    }

    public function show(string $id)
    {
        $rec = Agent::find($id);
        if (! $rec) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json($rec);
    }

    public function update(Request $request, string $id)
    {
        $rec = DB::table('agents')->where('id', $id)->first();
        if (! $rec) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'order' => 'sometimes|numeric',
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|nullable|string',
            'email' => 'sometimes|nullable|email|max:255',
            'date' => 'sometimes|nullable|date',
            'is_active' => 'sometimes|nullable|boolean',
            'phone' => 'sometimes|nullable|string|max:255',
        ]);

        // Partial update: only send the columns that were actually provided.
        $data = [];

        if ($request->has('order')) {
            $data['displayorder'] = $request->order;
        }
        if ($request->has('name')) {
            $data['name'] = $request->name;
        }
        if ($request->has('address')) {
            $data['address'] = $request->address;
        }
        if ($request->has('email')) {
            $data['email'] = $request->email;
        }
        if ($request->has('date')) {
            $data['validDate'] = $request->date;
        }
        if ($request->has('phone')) {
            $data['phone'] = $request->phone;
        }
        if ($request->has('is_active')) {
            $data['is_active'] = (bool) $request->boolean('is_active');
        }

        if (empty($data)) {
            return response()->json(['message' => 'Nothing to update']);
        }

        $data['updated_at'] = now();

        DB::table('agents')->where('id', $id)->update($data);

        // The most recently written record becomes the current one.
        Agent::find($id)?->deactivateOlderRecords();

        return response()->json(['message' => 'Agent updated successfully']);
    }

    public function destroy(string $id)
    {
        $rec = DB::table('agents')->where('id', $id)->first();
        if (! $rec) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('agents')->where('id', $id)->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
