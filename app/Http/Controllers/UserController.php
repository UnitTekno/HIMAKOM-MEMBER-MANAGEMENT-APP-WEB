<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\DBU;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    /**
     * Path to store user pictures.
     */
    protected $path_picture_users;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->path_picture_users = config('dirpath.users.pictures');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = User::with('cabinet:id,name', 'roles:id,name', 'dbu:id,name')
                ->where('id', '!=', 1);

            return DataTables::of($data)
                ->addIndexColumn()
                ->make(true);
        }

        return view('pages.users.index', [
            'gender' => User::GENDER_TYPE,
            'dbus' => DBU::all(['id', 'name']),
            'roles' => Role::all(['id', 'name'])->whereNotIn('name', ['SUPER ADMIN']),
            'cabinets' => Cabinet::all(['id', 'name']),
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
            'name' => 'required|string|max:50',
            'nim' => 'required|numeric|digits:9|unique:users,nim',
            'npa' => 'required|numeric|digits:7|unique:users,npa',
            //'name_bagus' => 'required|string|max:50',
            'gender' => 'required|in:0,1',
            'year' => 'required|numeric|digits:4',
            'email' => 'required|string|email|max:255|unique:users',
            'picture' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'password' => 'required|string|min:8|confirmed',
            'cabinet' => 'required|exists:cabinets,id',
            'dbu' => 'required|exists:dbus,id',
            'role' => 'required|exists:roles,id|not_in:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error!',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {

            $picture = $request->file('picture');
            $picture_name = date('Y-m-d-H-i-s') . '_' . $request->name . '.' . $picture->extension();
            $picture->storeAs($this->path_picture_users, $picture_name, 'public');

            $user = User::create([
                'name' => $request->name,
                'nim' => $request->nim,
                'npa' => $request->npa,
                'name_bagus' => '-',
                'gender' => $request->gender,
                'year' => $request->year,   
                'email' => $request->email,
                'picture' => $picture_name,
                'password' => bcrypt($request->password),
                'dbu_id' => $request->dbu,
                'cabinet_id' => $request->cabinet,
                'puzzle_level' => 1,
            ]);

            $user->assignRole($request->role);

            WorkHistory::create([
                'user_id' => $user->id,
                'cabinet_id' => $request->cabinet,
                'dbu_id' => $request->dbu,
                'role_id' => $request->role,
                'start_date' => Carbon::now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully!',
                'data' => $user,
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
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        try {
            $user->load('cabinet:id,name', 'dbu:id,name', 'roles:id,name');
            return response()->json([
                'status' => 'success',
                'data' => $user,
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
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'nim' => 'required|numeric|digits:9|unique:users,nim,' . $user->id,
            'npa' => 'required|numeric|digits:7|unique:users,npa,' . $user->id,
            // 'name_bagus' => 'required|string|max:50',
            'year' => 'required|numeric|digits:4',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'gender' => 'required|in:0,1',
            'cabinet' => 'required|exists:cabinets,id',
            'dbu' => 'required|exists:dbus,id',
            'role' => 'required|exists:roles,id|not_in:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error!',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->hasFile('picture')) {
                deleteFile($this->path_picture_users . '/' . $user->getAttributes()['picture']);
                $picture = $request->file('picture');
                $picture_name = date('Y-m-d-H-i-s') . '_' . $request->name . '.' . $picture->extension();
                $picture->storeAs($this->path_picture_users, $picture_name, 'public');
                $user->picture = $picture_name;
            }

            $user->update([
                'name' => $request->name,
                'nim' => $request->nim,
                'npa' => $request->npa,
                'name_bagus' => '-',
                'gender' => $request->gender,
                'year' => $request->year,
                'email' => $request->email,
                'password' => $request->password ? bcrypt($request->password) : $user->password,
                'dbu_id' => $request->dbu,
                'cabinet_id' => $request->cabinet,
                'puzzle_level' => $request->puzzle_level,
            ]);

            $user->roles()->sync($request->role);

            $workHistory = WorkHistory::where('user_id', $user->id)
                ->where('cabinet_id', $request->cabinet)
                ->where('dbu_id', $request->dbu)
                ->where('role_id', $request->role)
                ->first();

            if (!$workHistory) {
                WorkHistory::create([
                    'user_id' => $user->id,
                    'cabinet_id' => $request->cabinet,
                    'dbu_id' => $request->dbu,
                    'role_id' => $request->role,
                    'start_date' => Carbon::now(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully!',
                'data' => $user,
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
    public function destroy(User $user)
    {
        try {
            $user->roles()->detach();

            deleteFile($this->path_picture_users . '/' . $user->getAttributes()['picture']);
            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully!',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong!',
            ], 500);
        }
    }
}
