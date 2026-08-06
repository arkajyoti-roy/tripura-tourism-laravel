<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = DB::table('activity_logs')
            ->leftJoin('admin', 'activity_logs.admin_id', '=', 'admin.id')
            ->select(
                'activity_logs.id',
                'activity_logs.action_type',
                'activity_logs.section',
                'activity_logs.resource',
                'activity_logs.details',
                'activity_logs.ip_address',
                'activity_logs.created_at',
                'admin.name as admin_name',
                DB::raw('COALESCE(activity_logs.operator_email, admin.email) as admin_email')
            )
            ->orderBy('activity_logs.id', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }
}
