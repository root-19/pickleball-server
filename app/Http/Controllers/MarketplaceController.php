<?php

namespace App\Http\Controllers;

use App\Models\MarketplacePost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MarketplaceController extends Controller
{
    private function formatPost($post)
    {
        $data = $post->toArray();
        if ($post->image) {
            $data['image'] = url('storage/' . $post->image);
        }
        if ($post->video) {
            $data['video'] = url('storage/' . $post->video);
        }
        $data['owner_name'] = $post->owner->name ?? null;
        $data['owner_image'] = $post->owner->profile_image
            ? url('storage/' . $post->owner->profile_image)
            : null;
        $data['views'] = $post->views()->count();
        return $data;
    }

    public function index()
    {
        $posts = MarketplacePost::with('owner')
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => $this->formatPost($p));

        return response()->json($posts);
    }

    public function reels()
    {
        $posts = MarketplacePost::with('owner')
            ->where('is_active', true)
            ->whereNotNull('video')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => $this->formatPost($p));

        return response()->json($posts);
    }

    public function incrementView(Request $request, $id)
    {
        $post = MarketplacePost::findOrFail($id);
        $userId = $request->user()->id;

        if (!$post->views()->where('user_id', $userId)->exists()) {
            $post->views()->attach($userId);
        }

        return response()->json(['ok' => true]);
    }

    public function toggleHeart(Request $request, $id)
    {
        $post = MarketplacePost::findOrFail($id);
        $action = $request->input('action', 'add');
        if ($action === 'remove' && $post->hearts > 0) {
            $post->decrement('hearts');
        } else {
            $post->increment('hearts');
        }
        return response()->json(['hearts' => $post->fresh()->hearts]);
    }

    public function myPosts(Request $request)
    {
        $posts = MarketplacePost::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => $this->formatPost($p));

        return response()->json($posts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price'       => 'nullable|numeric|min:0',
            'link'        => 'nullable|url|max:500',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
            'video'       => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-m4v,video/mpeg,video/webm,video/3gpp|max:102400',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'marketplace/' . $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $imagePath = $filename;
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $filename = 'marketplace/videos/' . $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $videoPath = $filename;
        }

        $post = MarketplacePost::create([
            'user_id'     => $request->user()->id,
            'title'       => $request->title,
            'description' => $request->description,
            'price'       => $request->price,
            'link'        => $request->link,
            'image'       => $imagePath,
            'video'       => $videoPath,
        ]);

        $post->load('owner');
        return response()->json($this->formatPost($post), 201);
    }

    public function update(Request $request, $id)
    {
        $post = MarketplacePost::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price'       => 'nullable|numeric|min:0',
            'link'        => 'nullable|url|max:500',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
            'video'       => 'nullable|mimetypes:video/mp4,video/quicktime,video/x-m4v,video/mpeg,video/webm,video/3gpp|max:102400',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile('image')) {
            if ($post->image && Storage::exists('public/' . $post->image)) {
                Storage::delete('public/' . $post->image);
            }
            $file = $request->file('image');
            $filename = 'marketplace/' . $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $post->image = $filename;
        }

        if ($request->hasFile('video')) {
            if ($post->video && Storage::exists('public/' . $post->video)) {
                Storage::delete('public/' . $post->video);
            }
            $file = $request->file('video');
            $filename = 'marketplace/videos/' . $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public', $filename);
            $post->video = $filename;
        }

        $post->update($request->only(['title', 'description', 'price', 'link']));
        $post->load('owner');
        return response()->json($this->formatPost($post));
    }

    public function destroy(Request $request, $id)
    {
        $post = MarketplacePost::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($post->image && Storage::exists('public/' . $post->image)) {
            Storage::delete('public/' . $post->image);
        }
        if ($post->video && Storage::exists('public/' . $post->video)) {
            Storage::delete('public/' . $post->video);
        }

        $post->delete();
        return response()->json(['message' => 'Post deleted']);
    }
}
