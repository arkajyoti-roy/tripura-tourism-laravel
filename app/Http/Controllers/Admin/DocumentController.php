<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    // Display a listing of the documents.
    public function index()
    {
        if(session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            @session_start();
        }
        
        // Check if admin is logged in
        if(strlen(@$_SESSION['alogin']) == '0') {
            return redirect()->route('admin.home');
        }
        
        $documents = Document::orderBy('docid', 'desc')->get();
        
        return view('toursim.admin.manage-document', compact('documents'));
    }

    // Show the form for creating a new document.
    public function create()
    {
        if(session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            @session_start();
        }
        
        // Check if admin is logged in
        if(strlen(@$_SESSION['alogin']) == '0') {
            return redirect()->route('admin.home');
        }
        
        @$_SESSION['token'] = bin2hex(random_bytes(32));
        
        return view('toursim.admin.manage-document');
    }

    // Store a newly created document in storage.
    public function store(Request $request)
    {
        if(session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            @session_start();
        }

        // Check if admin is logged in
        if(strlen(@$_SESSION['alogin']) == '0') {
            return redirect()->route('admin.home');
        }

        if ($request->has('submit')) {
            if ($_POST["token"] == @$_SESSION["token"]) {
                $des = htmlentities($request->input('des'));
                $status = htmlentities($request->input('status'));
                $whats = htmlentities($request->input('whats'));
                $odate = strtotime(htmlentities($request->input('odate')));
                $cdate = strtotime(htmlentities($request->input('cdate')));
                $doctype = htmlentities($request->input('document'));

                $errors = array();
                $file = $request->file('image');
                $file_name = $file->getClientOriginalName();
                $file_size = $file->getSize();
                $file_ext = strtolower($file->getClientOriginalExtension());

                $newfilename = time() . '.' . $file_ext;

                if (preg_match('/\.\./', $file_name)) {
                    @$_SESSION['msg'] = "Invalid file name.";
                } else {
                    if ($file_size > 20480000) { // 20MB
                        @$_SESSION['msg'] = "File too large. File must be less than 20 megabytes.";
                    } elseif ($file_ext != 'pdf') {
                        @$_SESSION['msg'] = "You should upload a .pdf file.";
                    } else {
                        if ($file->move(public_path('images/documents'), $newfilename)) {
                            $document = Document::create([
                                'des' => $des,
                                'file' => $newfilename,
                                'openDate' => $odate,
                                'closeDate' => $cdate,
                                'status' => $status,
                                'doctype' => $doctype,
                                'whatsnew' => $whats,
                                'updateDate' => date('Y-m-d H:i:s')
                            ]);

                            if ($document) {
                                @$_SESSION['msg'] = "Well done! Entry Added!!";
                            } else {
                                @$_SESSION['msg'] = "Something went wrong!!";
                            }
                        } else {
                            @$_SESSION['msg'] = "Failed to move uploaded file.";
                        }
                    }
                }
            }
        }

        return redirect()->route('admin.documents.index');
    }

    // Show the form for editing the specified document.
    public function edit($id)
    {
        if(session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            @session_start();
        }
        
        // Check if admin is logged in
        if(strlen(@$_SESSION['alogin']) == '0') {
            return redirect()->route('admin.home');
        }
        
        $document = Document::findOrFail($id);
        @$_SESSION['token'] = bin2hex(random_bytes(32));
        
        return view('toursim.admin.edit-document', compact('document'));
    }

    // Update the specified document in storage.
    public function update(Request $request, $id)
    {
        if(session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            @session_start();
        }

        // Check if admin is logged in
        if(strlen(@$_SESSION['alogin']) == '0') {
            return redirect()->route('admin.home');
        }

        if ($request->has('submit')) {
            if ($_POST["token"] == @$_SESSION["token"]) {
                $doctype = htmlentities($request->input('document'));
                $des = htmlentities($request->input('des'));
                $status = htmlentities($request->input('status'));
                $odate = strtotime(htmlentities($request->input('odate')));
                $cdate = strtotime(htmlentities($request->input('cdate')));
                $whats = htmlentities($request->input('whats'));
                $time = time();

                $errors = array();
                $newfilename = null;

                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    $file_name = $file->getClientOriginalName();
                    $file_ext = strtolower($file->getClientOriginalExtension());
                    $newfilename = $time . '.' . $file_ext;

                    if ($file->getSize() > 2048000) { // 2MB
                        $errors[] = "File too large. File must be less than 2 megabytes.";
                    }

                    if ($file_ext != 'pdf') {
                        $errors[] = "Invalid file type. Only PDF files are allowed.";
                    }

                    if (empty($errors)) {
                        if (!$file->move(public_path('images/documents'), $newfilename)) {
                            $errors[] = "Failed to upload the file.";
                        }
                    }
                } else {
                    $document = Document::findOrFail($id);
                    $newfilename = $document->file;
                }

                if (empty($errors)) {
                    $document = Document::findOrFail($id);
                    $document->update([
                        'des' => $des,
                        'file' => $newfilename,
                        'openDate' => $odate,
                        'closeDate' => $cdate,
                        'status' => $status,
                        'updateDate' => $time,
                        'doctype' => $doctype,
                        'whatsnew' => $whats
                    ]);

                    @$_SESSION['msg'] = "Well done! Entry Updated!!";
                } else {
                    @$_SESSION['msg'] = implode("<br>", $errors);
                }
            }
        }

        return redirect()->route('admin.documents.index');
    }

    // Remove the specified document from storage.
    public function destroy($id)
    {
        if(session_status() === PHP_SESSION_NONE) {
            session_save_path(storage_path('framework/sessions'));
            @session_start();
        }
        
        // Check if admin is logged in
        if(strlen(@$_SESSION['alogin']) == '0') {
            return redirect()->route('admin.home');
        }
        
        $document = Document::findOrFail($id);
        
        // Delete the file from storage
        if ($document->file && file_exists(public_path('images/documents/' . $document->file))) {
            unlink(public_path('images/documents/' . $document->file));
        }
        
        $document->delete();
        
        @$_SESSION['delmsg'] = "Document deleted successfully !!";
        
        return redirect()->route('admin.documents.index');
    }
}