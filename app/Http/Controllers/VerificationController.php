<?php

namespace App\Http\Controllers;

use App\Models\VerificationAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VerificationController extends Controller
{
    /**
     * Format the record with full image URLs.
     */
    private function format(VerificationAccount $verification)
    {
        $data = $verification->toArray();
        foreach (['court_image_1', 'court_image_2', 'court_image_3'] as $field) {
            if ($verification->{$field}) {
                $data[$field] = url('storage/' . $verification->{$field});
            }
        }
        return $data;
    }

    /**
     * Get the authenticated user's verification account.
     * Returns the existing record, or defaults pre-filled with the
     * email used during registration.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $verification = VerificationAccount::where('user_id', $user->id)->first();

        if (!$verification) {
            return response()->json([
                'user_id'       => $user->id,
                'email'         => $user->email,
                'court_image_1' => null,
                'court_image_2' => null,
                'court_image_3' => null,
                'facebook'      => null,
                'instagram'     => null,
                'tiktok'        => null,
                'website'       => null,
                'status'        => 'not_submitted',
            ]);
        }

        return response()->json($this->format($verification));
    }

    /**
     * Create or update the authenticated user's verification account.
     * Accepts multipart form data with up to 3 court images and social links.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'court_image_1' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'court_image_2' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'court_image_3' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'facebook'      => 'nullable|string|max:500',
            'instagram'     => 'nullable|string|max:500',
            'tiktok'        => 'nullable|string|max:500',
            'website'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $verification = VerificationAccount::firstOrNew(['user_id' => $user->id]);

        // Email is always taken from the registered account, not the request.
        $verification->email = $user->email;

        foreach (['court_image_1', 'court_image_2', 'court_image_3'] as $field) {
            if ($request->hasFile($field)) {
                // Remove the previously uploaded image for this slot.
                if ($verification->{$field} && Storage::disk('public')->exists($verification->{$field})) {
                    Storage::disk('public')->delete($verification->{$field});
                }
                $file = $request->file($field);
                $basename = $user->id . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                Storage::disk('public')->putFileAs('verifications', $file, $basename);
                $verification->{$field} = 'verifications/' . $basename;
            }
        }

        $verification->facebook  = $request->input('facebook');
        $verification->instagram = $request->input('instagram');
        $verification->tiktok    = $request->input('tiktok');
        $verification->website   = $request->input('website');
        $verification->status    = 'pending';

        $verification->save();

        return response()->json($this->format($verification));
    }
}
