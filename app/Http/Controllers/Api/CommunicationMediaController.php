<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationMediaController extends Controller
{
    

    public function index()
    {
        $data = DB::table('communication_media')->first();
        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    

    public function update(Request $request)
    {
        $request->validate([
            'phone'     => 'nullable|string|max:255',
            'email'     => 'nullable|email|max:255',
            'address'   => 'nullable|string',
            'facebook'  => 'nullable|string|max:255',
            'twitter'   => 'nullable|string|max:255',
            'instagram' => 'nullable|string|max:255',
            'youtube'   => 'nullable|string|max:255',
            'linkedin'  => 'nullable|string|max:255',
        ]);

        $data = [
            'phone'     => $request->phone,
            'email'     => $request->email,
            'address'   => $request->address,
            'facebook'  => $request->facebook,
            'twitter'   => $request->twitter,
            'instagram' => $request->instagram,
            'youtube'   => $request->youtube,
            'linkedin'  => $request->linkedin,
            'updated_at' => now(),
        ];

        $existing = DB::table('communication_media')->first();
        if ($existing) {
            DB::table('communication_media')->where('id', $existing->id)->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('communication_media')->insert($data);
        }

        return response()->json(['message' => 'Communication media updated successfully']);
    }
}
