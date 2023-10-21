<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class ActivityLogController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
     {
         if ($request->ajax()) {
            $data = Activity::with('causer')
                ->select([
                    'id',
                    'log_name',
                    'event',
                    'causer_id',
                    'causer_type',
                    'properties',
                    'created_at',
                ]);
             
             return DataTables::of($data)
                ->make(true);
         }
  
         return view('pages.activity-logs.index');
     }
}

