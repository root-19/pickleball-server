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
        foreach (['id_image', 'court_image_1', 'court_image_2', 'court_image_3'] as $field) {
            if ($verification->{$field}) {
                $data[$field] = url('storage/' . $verification->{$field});
            }
        }
        $data['documents'] = array_map(fn($path) => [
            'name' => basename($path),
            'url'  => url('storage/' . $path),
        ], $verification->documents ?? []);
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
                'id_image'      => null,
                'court_image_1' => null,
                'court_image_2' => null,
                'court_image_3' => null,
                'facebook'      => null,
                'instagram'     => null,
                'tiktok'        => null,
                'website'       => null,
                'documents'     => [],
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
            'id_image'      => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'court_image_1' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'court_image_2' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'court_image_3' => 'nullable|image|mimes:jpeg,png,jpg|max:3072',
            'facebook'      => 'nullable|string|max:500',
            'instagram'     => 'nullable|string|max:500',
            'tiktok'        => 'nullable|string|max:500',
            'website'       => 'nullable|string|max:500',
            'documents'            => 'nullable|array',
            'documents.*'          => 'file|mimes:pdf,jpeg,png,jpg,doc,docx|max:5120',
            'existing_documents'   => 'nullable|array',
            'existing_documents.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $verification = VerificationAccount::firstOrNew(['user_id' => $user->id]);

        // Email is always taken from the registered account, not the request.
        $verification->email = $user->email;

        foreach (['id_image', 'court_image_1', 'court_image_2', 'court_image_3'] as $field) {
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

        // Documents (optional): keep the existing ones the client still wants,
        // delete the removed ones, then append newly uploaded files.
        $currentDocs = $verification->documents ?? [];
        $prefix = url('storage') . '/';
        $kept = [];
        foreach ((array) $request->input('existing_documents', []) as $entry) {
            $relative = str_starts_with($entry, $prefix) ? substr($entry, strlen($prefix)) : $entry;
            if (in_array($relative, $currentDocs, true)) {
                $kept[] = $relative;
            }
        }
        foreach ($currentDocs as $path) {
            if (!in_array($path, $kept, true) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $i => $file) {
                $basename = $user->id . '_doc_' . time() . '_' . $i . '.' . $file->getClientOriginalExtension();
                Storage::disk('public')->putFileAs('verifications', $file, $basename);
                $kept[] = 'verifications/' . $basename;
            }
        }
        $verification->documents = $kept;

        $verification->facebook  = $request->input('facebook');
        $verification->instagram = $request->input('instagram');
        $verification->tiktok    = $request->input('tiktok');
        $verification->website   = $request->input('website');
        $verification->status    = 'pending';

        $verification->save();

        return response()->json($this->format($verification));
    }
}
