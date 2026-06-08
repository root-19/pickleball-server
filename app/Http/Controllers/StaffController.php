<?php

namespace App\Http\Controllers;

use App\Models\StaffAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = StaffAccount::where('owner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($staff);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'email'    => 'required|email|unique:staff_accounts,email',
            'password' => 'required|string|min:8',
            'role'     => 'sometimes|in:manager,staff',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staff = StaffAccount::create([
            'owner_id' => $request->user()->id,
            'username' => $request->username,
            'email'    => $request->email,
            'password' => $request->password,
            'role'     => $request->role ?? 'staff',
        ]);

        return response()->json($staff, 201);
    }

    public function update(Request $request, $id)
    {
        $staff = StaffAccount::where('id', $id)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'username'  => 'sometimes|string|max:255',
            'email'     => 'sometimes|email|unique:staff_accounts,email,' . $id,
            'password'  => 'sometimes|string|min:8',
            'role'      => 'sometimes|in:manager,staff',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['username', 'email', 'role', 'is_active']);
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        $staff->update($data);

        return response()->json($staff->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $staff = StaffAccount::where('id', $id)
            ->where('owner_id', $request->user()->id)
            ->firstOrFail();

        $staff->delete();

        return response()->json(['message' => 'Staff account deleted successfully']);
    }
}
