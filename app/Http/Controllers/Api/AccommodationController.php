<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccommodationController extends Controller
{
    

    public function index()
    {
        $accommodations = DB::table('accommodation')->orderBy('aid', 'desc')->get()->map(function ($acc) {
            $acc->id = $acc->aid; 
            $path = ($acc->type == '4') ? 'images/accommodation/homestay/' : 'images/accommodation/';
            $acc->image_url = $acc->image ? asset($path . $acc->image) : null;
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
}
