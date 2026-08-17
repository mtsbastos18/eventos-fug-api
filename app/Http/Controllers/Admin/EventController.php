<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Event;
use Log;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->boolean('archived')
            ? Event::archived()
            : Event::notArchived();

        return response()->json($query->orderBy('date', 'desc')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'date' => 'required|date',
            'location' => 'required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'required|integer|min:1',
            'workload_hours' => 'nullable|integer|min:1|max:1000',
            'image' => 'nullable|image|max:5120',
        ]);

        // Primeiro criamos o evento para ter o ID
        $event = Event::create(collect($validated)->except('image')->toArray());

        if ($request->hasFile('image')) {
            $extension = $request->file('image')->getClientOriginalExtension();
            $filename = $event->id . '.' . $extension;

            // Salvando no nosso disco customizado de hospedagem
            $path = $request->file('image')->storeAs('events', $filename, env('FILESYSTEM_DISK'));

            $event->update(['image_path' => $path]);
        }

        // Carrega o evento atualizado com o path
        return response()->json($event->fresh(), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $event->loadCount([
            'participants as checkin_count' => fn($query) => $query->whereNotNull('checked_in_at'),
        ]);

        return response()->json($event);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'date' => 'sometimes|required|date',
            'location' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'capacity' => 'sometimes|required|integer|min:1',
            'workload_hours' => 'nullable|integer|min:1|max:1000',
            'image' => 'nullable|image|max:5120',
        ]);

        if ($request->hasFile('image')) {
            // Remove a imagem antiga, se existir
            if ($event->image_path) {
                Storage::disk(env('FILESYSTEM_DISK'))->delete($event->image_path);
            }

            // Salva a nova imagem com o ID do evento
            $extension = $request->file('image')->getClientOriginalExtension();
            $filename = $event->id . '.' . $extension;

            $validated['image_path'] = $request->file('image')->storeAs('events', $filename, env('FILESYSTEM_DISK'));
        }

        $event->update($validated);

        return response()->json($event);
    }

    public function archive(Event $event)
    {
        Log::info('Archiving event: ' . $event->id);
        Log::info('Current archived_at value: ' . now());
        $event->update(['archived_at' => now()]);
        return response()->json($event);
    }

    public function unarchive(Event $event)
    {
        $event->update(['archived_at' => null]);
        return response()->json($event);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        if ($event->image_path) {
            Storage::disk(env('FILESYSTEM_DISK'))->delete($event->image_path);
        }
        $event->delete();
        return response()->json(null, 204);
    }

    public function participants(Request $request, Event $event)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'filter_type' => 'nullable|in:name,cpf,email',
            'per_page' => 'nullable|integer|min:1|max:100',
            'order_by' => 'nullable|in:name,latest',
        ]);

        $participants = $event->participants()
            ->search($validated['search'] ?? null, $validated['filter_type'] ?? null)
            ->when(
                ($validated['order_by'] ?? 'latest') === 'name',
                fn($q) => $q->orderBy('name'),
                fn($q) => $q->latest(),
            )
            ->paginate($validated['per_page'] ?? 10);

        return response()->json($participants);
    }

    public function dashboard()
    {
        $events = Event::withCount('participants')->get()->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'capacity' => $event->capacity,
                'participants_count' => $event->participants_count,
                'available_spots' => $event->capacity - $event->participants_count,
                'image_url' => $event->image_url,
            ];
        });

        return response()->json($events);
    }
}
