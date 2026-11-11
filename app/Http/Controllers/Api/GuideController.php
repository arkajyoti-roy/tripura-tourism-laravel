<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuideController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\Guide::query();

        // Optional search across Name, Address, Phone, Language, Spot
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('language', 'like', "%{$search}%")
                    ->orWhere('spot', 'like', "%{$search}%");
            });
        }

        // Optional filter by status: 'active' or 'expired'
        if ($status = $request->input('status')) {
            $today = date('Y-m-d');
            $dateExpr = "(CASE WHEN validDate REGEXP '^[0-9]{2}[-/][0-9]{2}[-/][0-9]{4}$' ".
                "THEN STR_TO_DATE(REPLACE(validDate, '/', '-'), '%d-%m-%Y') ".
                "ELSE STR_TO_DATE(REPLACE(validDate, '/', '-'), '%Y-%m-%d') END)";

            if ($status === 'active') {
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
            } elseif ($status === 'expired' || $status === 'inactive') {
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

        // Server-side pagination
        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(200, (int) $request->input('per_page', $request->input('pageSize', 30))));
            $paginated = $query->orderBy('displayorder', 'asc')->paginate($perPage);
            $paginated->getCollection()->transform(function ($guide) {
                $guide->image_url = $guide->image ? asset('images/guides/' . $guide->image) : null;
                return $guide;
            });
            return response()->json($paginated);
        }

        $records = $query->orderBy('displayorder', 'asc')->get();
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

        unset($data['_method']);

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

    /**
     * Update active status for Guide.
     */
    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $guide = \App\Models\Guide::findOrFail($id);
        $guide->is_active = $validated['is_active'];
        $guide->updated_at = now();
        $guide->save();

        return response()->json($guide->fresh());
    }

    /**
     * Trigger sync for tourist guides from external server (Code 74).
     */
    public function syncExternal(Request $request, \App\Services\ExternalGuideSyncService $syncService)
    {
        $since = $request->input('since');
        $result = $syncService->sync($since);

        return response()->json($result, $result['success'] ? 200 : 502);
    }
}
