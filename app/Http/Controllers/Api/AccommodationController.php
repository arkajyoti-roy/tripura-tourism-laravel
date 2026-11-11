<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccommodationController extends Controller
{
    

    public function index(Request $request)
    {
        $query = \App\Models\Accommodation::query();

        // Optional search across Name, Address, Contact, Email, Lessee, Description
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('name_of_lessee', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Optional filter by Accommodation Type: 1 (Govt), 2 (Private Hotel), 3 (Leaseout), 4 (Homestay)
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Optional filter by Status: 'active' or 'expired' (Only applies to types 2 and 4)
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'expired' || $status === 'inactive') {
                $query->expired();
            }
        }

        // Check if server-side pagination is requested
        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(200, (int) $request->input('per_page', $request->input('pageSize', 30))));

            $sortable = ['aid', 'displayorder', 'name', 'type', 'created_at', 'updated_at', 'valid_upto', 'is_active'];
            $sortBy = in_array($request->input('sort_by'), $sortable, true)
                ? $request->input('sort_by')
                : 'displayorder';
            $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            $paginated = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

            // Transform items to include id alias and image_url
            $paginated->getCollection()->transform(function ($acc) {
                $acc->id = $acc->aid;
                return $acc;
            });

            return response()->json($paginated);
        }

        // Default behavior: all accommodations ordered by displayorder / aid
        $accommodations = $query->orderBy('displayorder', 'asc')->orderBy('aid', 'desc')->get()->map(function ($acc) {
            $acc->id = $acc->aid;
            return $acc;
        });

        return response()->json($accommodations);
    }

    

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'nullable|string',
            'address' => 'nullable|string',
            'contact' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|string',
            'displayorder' => 'nullable|integer',
            'name_of_lessee' => 'nullable|string',
            'valid_upto' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type ?? '1',
            'address' => $request->address,
            'contact' => $request->contact,
            'email' => $request->email,
            'website' => $request->website,
            'displayorder' => $request->displayorder ?? 0,
            'name_of_lessee' => $request->name_of_lessee,
            'valid_upto' => $request->valid_upto,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            $type = $request->type ?? '1';
            $path = ($type == '4') ? public_path('images/accommodation/homestay') : public_path('images/accommodation');
            $file->move($path, $filename);
            $data['image'] = $filename;
        }

        DB::table('accommodation')->insert($data);
        return response()->json(['message' => 'Accommodation created successfully'], 201);
    }

    

    public function show(string $id)
    {
        $acc = DB::table('accommodation')->where('aid', $id)->first();
        if (!$acc) return response()->json(['message' => 'Not found'], 404);
        $acc->id = $acc->aid;
        $path = ($acc->type == '4') ? 'images/accommodation/homestay/' : 'images/accommodation/';
        $acc->image_url = $acc->image ? asset($path . $acc->image) : null;
        return response()->json($acc);
    }

    

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'nullable|string',
            'address' => 'nullable|string',
            'contact' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|string',
            'displayorder' => 'nullable|integer',
            'name_of_lessee' => 'nullable|string',
            'valid_upto' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:61440'
        ]);

        $accommodation = DB::table('accommodation')->where('aid', $id)->first();

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'address' => $request->address,
            'contact' => $request->contact,
            'email' => $request->email,
            'website' => $request->website,
            'displayorder' => $request->displayorder,
            'name_of_lessee' => $request->name_of_lessee,
            'valid_upto' => $request->valid_upto,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . $file->getClientOriginalName();
            
            $newType = $request->type ?? $accommodation->type;
            $path = ($newType == '4') ? public_path('images/accommodation/homestay') : public_path('images/accommodation');
            $file->move($path, $filename);
            $data['image'] = $filename;

            
            $oldPath = ($accommodation->type == '4') ? public_path('images/accommodation/homestay/') : public_path('images/accommodation/');
            if ($accommodation && $accommodation->image && file_exists($oldPath . $accommodation->image)) {
                unlink($oldPath . $accommodation->image);
            }
        }

        unset($data['_method']);

        DB::table('accommodation')->where('aid', $id)->update(array_filter($data, function ($val) {
            return !is_null($val);
        }));

        return response()->json(['message' => 'Accommodation updated successfully']);
    }

    

    public function destroy(string $id)
    {
        $accommodation = DB::table('accommodation')->where('aid', $id)->first();
        if ($accommodation) {
            $oldPath = ($accommodation->type == '4') ? public_path('images/accommodation/homestay/') : public_path('images/accommodation/');
            if ($accommodation->image && file_exists($oldPath . $accommodation->image)) {
                unlink($oldPath . $accommodation->image);
            }
            DB::table('accommodation')->where('aid', $id)->delete();
        }

        return response()->json(['message' => 'Accommodation deleted successfully']);
    }

    /**
     * Update active status for accommodation / homestay.
     */
    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $acc = \App\Models\Accommodation::findOrFail($id);
        $acc->is_active = $validated['is_active'];
        $acc->updated_at = now();
        $acc->save();

        return response()->json($acc->fresh());
    }

    /**
     * Trigger sync for homestays from external server (Code 76).
     */
    public function syncExternalHomestays(Request $request, \App\Services\ExternalHomestaySyncService $syncService)
    {
        $since = $request->input('since');
        $result = $syncService->sync($since);

        return response()->json($result, $result['success'] ? 200 : 502);
    }

    /**
     * Trigger sync for hotels/resorts from external server (Code 73).
     */
    public function syncExternalHotels(Request $request, \App\Services\ExternalHotelSyncService $syncService)
    {
        $since = $request->input('since');
        $result = $syncService->sync($since);

        return response()->json($result, $result['success'] ? 200 : 502);
    }
}
