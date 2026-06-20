<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterDraftController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => 'nullable|email',
            'password' => 'nullable|min:8',
        ]);

        if (! $request->email) {
            return response()->json(['status' => 'ignored']);
        }

        User::updateOrCreate(
            ['email' => $request->email],
            [
                'password' => $request->password
                    ? Hash::make($request->password)
                    : null,
                'status' => 'draft'
            ]
        );

        return response()->json(['status' => 'saved']);
    }
}
