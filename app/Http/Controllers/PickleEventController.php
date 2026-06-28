<?php

namespace App\Http\Controllers;

use App\Models\PickleEvent;
use Illuminate\Http\Request;

class PickleEventController extends Controller
{
    private function formatEvent(PickleEvent $event): array
    {
        $data = $event->toArray();
        if ($event->event_image) {
            $data['event_image'] = url('storage/' . $event->event_image);
        }
        return $data;
    }

    public function browse()
    {
        $events = PickleEvent::where('is_active', true)
            ->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date', 'asc')
            ->get()
            ->map(fn($e) => $this->formatEvent($e));

        return response()->json($events);
    }

    public function index(Request $request)
    {
        $events = PickleEvent::where('user_id', $request->user()->id)
            ->orderBy('event_date', 'desc')
            ->get()
            ->map(fn($e) => $this->formatEvent($e));

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'          => 'required|string|max:255',
            'location'       => 'nullable|string|max:255',
            'event_date'     => 'required|date',
            'open_time'      => 'required|string',
            'close_time'     => 'required|string',
            'max_players'    => 'required|integer|min:1',
            'price_per_head' => 'required|numeric|min:0',
            'rules'          => 'nullable|string',
            'about'          => 'nullable|string',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
            'event_image'    => 'nullable|image|max:4096',
        ]);

        $imagePath = null;
        if ($request->hasFile('event_image')) {
            $imagePath = $request->file('event_image')->store('event-images', 'public');
        }

        $event = PickleEvent::create([
            'user_id'        => $request->user()->id,
            'title'          => $request->title,
            'location'       => $request->location,
            'event_date'     => $request->event_date,
            'open_time'      => $request->open_time,
            'close_time'     => $request->close_time,
            'max_players'    => $request->max_players,
            'price_per_head' => $request->price_per_head,
            'rules'          => $request->rules,
            'about'          => $request->about,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'event_image'    => $imagePath ?? null,
        ]);

        return response()->json($this->formatEvent($event), 201);
    }

    public function show(string $id)
    {
        $event = PickleEvent::findOrFail($id);
        return response()->json($event);
    }

    public function update(Request $request, string $id)
    {
        $event = PickleEvent::where('user_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'title'          => 'sometimes|string|max:255',
            'location'       => 'sometimes|string|max:255',
            'event_date'     => 'sometimes|date',
            'open_time'      => 'sometimes|string',
            'close_time'     => 'sometimes|string',
            'max_players'    => 'sometimes|integer|min:1',
            'price_per_head' => 'sometimes|numeric|min:0',
            'rules'          => 'nullable|string',
            'about'          => 'nullable|string',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
            'event_image'    => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('event_image')) {
            $imagePath = $request->file('event_image')->store('event-images', 'public');
            $event->event_image = $imagePath;
        }

        $event->fill($request->except(['event_image', '_method']));
        $event->save();

        return response()->json($this->formatEvent($event));
    }

    public function destroy(Request $request, string $id)
    {
        $event = PickleEvent::where('user_id', $request->user()->id)->findOrFail($id);
        $event->delete();
        return response()->json(['message' => 'Event deleted.']);
    }
}
