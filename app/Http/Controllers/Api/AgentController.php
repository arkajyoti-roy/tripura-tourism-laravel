<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $query = Agent::query();

        // Optional search across Name, Email, Phone, Address
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        // Optional filter by status: 'active' or 'expired'
        if ($status = $request->input('status')) {
            $today = date('Y-m-d');
            $dateExpr = $this->validDateExpression();

            if ($status === 'active') {
                // Manual true wins. Only NULL (not manually set) records fall
                // back to the validDate check. Manual false is never active.
                $query->where(function ($q) use ($today, $dateExpr) {
                    $q->where('is_active', true)
                        ->orWhere(function ($sub) use ($today, $dateExpr) {
                            $sub->whereNull('is_active')
                                ->where(function ($date) use ($today, $dateExpr) {
                                    $date->whereNull('validDate')
                                        ->orWhere('validDate', '')
                                        ->orWhereRaw("{$dateExpr} >= ?", [$today]);
                                });
                        });
                });
            } elseif ($status === 'expired') {
                // Manual false wins. Only NULL records fall back to the date.
                $query->where(function ($q) use ($today, $dateExpr) {
                    $q->where('is_active', false)
                        ->orWhere(function ($sub) use ($today, $dateExpr) {
                            $sub->whereNull('is_active')
                                ->whereNotNull('validDate')
                                ->where('validDate', '!=', '')
                                ->whereRaw("{$dateExpr} < ?", [$today]);
                        });
                });
            }
        }

        // Check if server-side pagination is requested
        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(200, (int) $request->input('per_page', $request->input('pageSize', 30))));

            // Only allow known columns to be used for sorting.
            $sortable = ['displayorder', 'name', 'email', 'phone', 'validDate', 'is_active', 'updated_at', 'id'];
            $sortBy = in_array($request->input('sort_by'), $sortable, true)
                ? $request->input('sort_by')
                : 'displayorder';
            $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            if ($sortBy === 'displayorder') {
                $query->orderByRaw('displayorder IS NULL');
            }

            $query->orderBy($sortBy, $sortDir);

            return response()->json($query->paginate($perPage));
        }

        // Default behavior: follow the admin-managed sequence. Records with no
        // sequence yet are placed at the end instead of jumping to the top.
        $agents = $query->orderByRaw('displayorder IS NULL')
            ->orderBy('displayorder', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($agents);
    }

    /**
     * SQL expression that normalises both YYYY-MM-DD and DD-MM-YYYY values,
     * so the status filter matches the model's date fallback.
     */
    private function validDateExpression(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "(CASE WHEN validDate LIKE '__-__-____' OR validDate LIKE '__/__/____' ".
                "THEN substr(validDate, 7, 4) || '-' || substr(validDate, 4, 2) || '-' || substr(validDate, 1, 2) ".
                "ELSE replace(validDate, '/', '-') END)";
        }

        return "(CASE WHEN validDate REGEXP '^[0-9]{2}[-/][0-9]{2}[-/][0-9]{4}$' ".
            "THEN STR_TO_DATE(REPLACE(validDate, '/', '-'), '%d-%m-%Y') ".
            "ELSE STR_TO_DATE(REPLACE(validDate, '/', '-'), '%Y-%m-%d') END)";
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'address' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'validDate' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'displayorder' => 'nullable|integer',
        ]);

        $data = collect($validated)->except('is_active')->all();

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        // updated_at (and the legacy updateDate) is set by the model.
        $agent = Agent::create($data);

        return response()->json($agent->fresh(), 201);
    }

    public function show(string $id)
    {
        $agent = Agent::findOrFail($id);

        return response()->json($agent);
    }

    public function update(Request $request, string $id)
    {
        $agent = Agent::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'address' => 'sometimes|nullable|string',
            'email' => 'sometimes|nullable|email',
            'phone' => 'sometimes|nullable|string',
            'validDate' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|nullable|boolean',
            'displayorder' => 'sometimes|nullable|integer',
        ]);

        // Partial update: only fields present in the request are touched.
        $data = collect($validated)->except('is_active')->all();

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        if (! empty($data) || $request->has('is_active')) {
            $agent->fill($data);

            // A manual status change always refreshes the last-modified time.
            if ($request->has('is_active')) {
                $agent->updated_at = now();
            }

            $agent->save();
        }

        return response()->json($agent->fresh());
    }

    /**
     * Dedicated endpoint for external systems to push a status update without
     * having to send the full agent payload.
     */
    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $agent = Agent::findOrFail($id);
        $agent->is_active = $validated['is_active'];
        $agent->updated_at = now();
        $agent->save();

        return response()->json($agent->fresh());
    }

    /**
     * Trigger sync from external Tripura Tourism server.
     */
    public function syncExternal(Request $request, \App\Services\ExternalAgentSyncService $syncService)
    {
        $serviceCode = $request->input('service_code', \App\Services\ExternalAgentSyncService::AGENT_SERVICE_CODE);
        $since = $request->input('since');

        $result = $syncService->sync((int) $serviceCode, $since);

        return response()->json($result, $result['success'] ? 200 : 502);
    }

    public function destroy(string $id)
    {
        $agent = Agent::findOrFail($id);
        $agent->delete();

        return response()->json(['message' => 'Agent deleted successfully']);
    }
}
