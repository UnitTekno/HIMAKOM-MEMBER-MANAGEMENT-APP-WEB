<?php

namespace App\Http\Controllers;

use App\Models\DBU;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class EventController extends Controller
{
    /**
     * Path to store posters.
     */
    private $path_poster_events;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->path_poster_events = config('dirpath.events.posters');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {

            $typeExpression = 'CASE ';
            foreach (Event::EVENT_TYPE as $key => $value) {
                $typeExpression .= 'WHEN "type" = \'' . $key . '\' THEN \'' . $value . '\' ';
            }
            $typeExpression .= 'END as type';

            $data = Event::select([
                'id',
                'name',
                'description',
                'location',
                'poster',
                'date',
                'link',
                'dbu_id',
                DB::raw($typeExpression),
            ])
                ->when(!auth()->user()->hasRole('SUPER ADMIN'), function ($query) {
                    return $query->where('dbu_id', '==', Auth::user()->dbu_id);
                })
                ->with('dbu:id,name')
                ->orderBy('date', 'desc');

            return DataTables::of($data)->make();
        }

        return view('pages.events.index', [
            'types' => Event::EVENT_TYPE,
            'dbus' => DBU::all(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:50',
            'description' => 'required',
            'location' => 'required|max:255',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'date' => 'required|date|after_or_equal:today',
            'type' => 'required|in:' . implode(',', array_keys(Event::EVENT_TYPE)),
            'link' => 'nullable|url',
            'dbu' => 'required|exists:dbus,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error!',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $poster_name = null;

            if ($request->hasFile('poster')) {
                $poster = $request->file('poster');
                $poster_name = date('Y-m-d-H-i-s') . '_' . $request->name . '.' . $poster->extension();
                $poster->storeAs($this->path_poster_events, $poster_name, 'public');
            }

            $event = Event::create([
                'poster' => $poster_name,
                'name' => $request->name,
                'description' => $request->description,
                'location' => $request->location,
                'date' => $request->date,
                'type' => $request->type,
                'link' => $request->link ?? '',
                'dbu_id' => $request->dbu
            ]);

            sendNotificationEvent($event);

            return response()->json([
                'status' => 'success',
                'message' => 'Event created successfully!',
                'data' => $event,
            ], 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong!',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        try {
            return response()->json([
                'status' => 'success',
                'message' => 'Event fetched successfully!',
                'data' => $event,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong!',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:50',
            'description' => 'required',
            'location' => 'required|max:255',
            'date' => 'required|date|after_or_equal:today',
            'type' => 'required|in:' . implode(',', array_keys(Event::EVENT_TYPE)),
            'link' => 'nullable|url',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'dbu' => 'required|exists:dbus,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error!',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->hasFile('poster')) {
                if ($event->getAttributes()['poster']) {
                    deleteFile($this->path_poster_events . '/' .  $event->getAttributes()['poster']);
                }
                $poster = $request->file('poster');
                $poster_name = date('Y-m-d-H-i-s') . '_' . $request->name . '.' . $poster->extension();
                $poster->storeAs($this->path_poster_events, $poster_name, 'public');
                $event->poster = $poster_name;
            }

            $event->update([
                'name' => $request->name,
                'description' => $request->description,
                'location' => $request->location,
                'date' => $request->date,
                'type' => $request->type,
                'link' => $request->link ?? '',
                'dbu_id' => $request->dbu
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Event updated successfully!',
                'data' => $event,
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong!',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        try {
            if ($event->getAttributes()['poster']) {
                deleteFile($this->path_poster_events . '/' .  $event->getAttributes()['poster']);
            }
            $event->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Event deleted successfully!',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong!',
            ], 500);
        }
    }

    public function notification(Event $event, Request $request)
    {
        try {
            if (Carbon::parse($event->date)->isPast()) {
                throw new \Exception('Event date is past!');
            }

            $message = $request->anyFilled(['title', 'body', 'link']) ? $request : null;

            sendNotificationEvent($event, $message);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification sent successfully!',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
