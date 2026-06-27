<?php

namespace App\Http\Controllers;

use App\Models\MarketplacePost;
use App\Models\MarketplaceComment;
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
        $data['comment_count'] = $post->comments()->count();
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
            $basename = $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('marketplace', $file, $basename);
            $imagePath = 'marketplace/' . $basename;
        }

        $videoPath = null;
        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $basename = $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('marketplace/videos', $file, $basename);
            $videoPath = 'marketplace/videos/' . $basename;
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
            if ($post->image && Storage::disk('public')->exists($post->image)) {
                Storage::disk('public')->delete($post->image);
            }
            $file = $request->file('image');
            $basename = $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('marketplace', $file, $basename);
            $post->image = 'marketplace/' . $basename;
        }

        if ($request->hasFile('video')) {
            if ($post->video && Storage::disk('public')->exists($post->video)) {
                Storage::disk('public')->delete($post->video);
            }
            $file = $request->file('video');
            $basename = $request->user()->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            Storage::disk('public')->putFileAs('marketplace/videos', $file, $basename);
            $post->video = 'marketplace/videos/' . $basename;
        }

        $post->update($request->only(['title', 'description', 'price', 'link']));
        $post->load('owner');
        return response()->json($this->formatPost($post));
    }

    public function getComments($id)
    {
        $post = MarketplacePost::findOrFail($id);
        $comments = $post->comments()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'comment' => $c->comment,
                    'created_at' => $c->created_at,
                    'user_name' => $c->user->name ?? 'Unknown',
                    'user_image' => $c->user->profile_image
                        ? url('storage/' . $c->user->profile_image)
                        : null,
                ];
            });

        return response()->json($comments);
    }

    public function addComment(Request $request, $id)
    {
        $post = MarketplacePost::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $comment = MarketplaceComment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'comment' => $request->comment,
        ]);

        $comment->load('user');

        return response()->json([
            'id' => $comment->id,
            'comment' => $comment->comment,
            'created_at' => $comment->created_at,
            'user_name' => $comment->user->name ?? 'Unknown',
            'user_image' => $comment->user->profile_image
                ? url('storage/' . $comment->user->profile_image)
                : null,
        ], 201);
    }

    public function deleteComment(Request $request, $commentId)
    {
        $comment = MarketplaceComment::where('id', $commentId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $comment->delete();
        return response()->json(['message' => 'Comment deleted']);
    }

    public function destroy(Request $request, $id)
    {
        $post = MarketplacePost::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        if ($post->image && Storage::disk('public')->exists($post->image)) {
            Storage::disk('public')->delete($post->image);
        }
        if ($post->video && Storage::disk('public')->exists($post->video)) {
            Storage::disk('public')->delete($post->video);
        }

        $post->delete();
        return response()->json(['message' => 'Post deleted']);
    }
}
