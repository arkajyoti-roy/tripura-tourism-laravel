<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\Document::query();
        if ($request->has('doctype')) {
            $query->where('doctype', $request->doctype);
        }
        $documents = $query->orderBy('docid', 'desc')->get();
        
        
        $documents->transform(function($doc) {
            $doc->file_url = $doc->file ? asset('images/documents/' . $doc->file) : null;
            return $doc;
        });

        return response()->json($documents);
    }

    public function store(Request $request)
    {
        $request->validate([
            'des' => 'nullable|string',
            'doctype' => 'required|integer',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'status' => 'nullable|integer',
            'openDate' => 'nullable|date',
            'closeDate' => 'nullable|date',
            'whatsnew' => 'nullable|integer'
        ]);

        $data = $request->only(['des', 'doctype', 'status', 'whatsnew']);
        $data['updateDate'] = date('Y-m-d H:i:s');
        
        
        if ($request->has('openDate')) {
            $data['openDate'] = $request->openDate;
        }
        if ($request->has('closeDate')) {
            $data['closeDate'] = $request->closeDate;
        }
        
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/documents'), $filename);
            $data['file'] = $filename;
        }

        $document = \App\Models\Document::create($data);
        $document->file_url = asset('images/documents/' . $document->file);
        return response()->json($document, 201);
    }

    public function show($id)
    {
        $document = \App\Models\Document::findOrFail($id);
        $document->file_url = $document->file ? asset('images/documents/' . $document->file) : null;
        return response()->json($document);
    }

    public function update(Request $request, $id)
    {
        $document = \App\Models\Document::findOrFail($id);
        
        $request->validate([
            'des' => 'nullable|string',
            'doctype' => 'nullable|integer',
            'file' => 'nullable|file|mimes:pdf|max:20480',
            'status' => 'nullable|integer',
            'openDate' => 'nullable|date',
            'closeDate' => 'nullable|date',
            'whatsnew' => 'nullable|integer'
        ]);

        $data = $request->only(['des', 'doctype', 'status', 'whatsnew']);
        $data['updateDate'] = date('Y-m-d H:i:s');

        
        if ($request->has('openDate')) {
            $data['openDate'] = $request->openDate;
        }
        if ($request->has('closeDate')) {
            $data['closeDate'] = $request->closeDate;
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/documents'), $filename);
            $data['file'] = $filename;
        }

        unset($data['_method']);

        $document->update($data);
        $document->file_url = asset('images/documents/' . $document->file);
        return response()->json($document);
    }

    public function destroy($id)
    {
        $document = \App\Models\Document::findOrFail($id);
        $document->delete();
        return response()->json(['message' => 'Document deleted successfully']);
    }
}
