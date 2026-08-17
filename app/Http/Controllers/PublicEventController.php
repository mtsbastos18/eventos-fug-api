<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;

class PublicEventController extends Controller
{
    /**
     * List available events (e.g., future events).
     */
    public function index()
    {
        // Retorna apenas eventos com data e hora maior ou igual ao momento atual no fuso horário do Brasil
        $nowBrazil = now()->setTimezone('America/Sao_Paulo')->subHours(5);
        $events = Event::notArchived()
            ->where('date', '>=', $nowBrazil)
            ->orderBy('date', 'asc')
            ->get();

        return response()->json($events);
    }

    public function getPastEvents()
    {
        // Retorna apenas eventos com data menor que hoje, ordenados pela data mais recente
        $nowBrazil = now()->setTimezone('America/Sao_Paulo')->subHours(5);
        $events = Event::notArchived()
            ->where('date', '<', $nowBrazil)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json($events);
    }

    /**
     * Show details of a specific event.
     */
    public function show($identifier)
    {
        $event = Event::where('slug', $identifier)
            ->firstOrFail();

        // Opcional: carregar contagem de participantes se quiser mostrar vagas restantes
        $event->loadCount('participants');
        $event->available_spots = $event->capacity - $event->participants_count;

        return response()->json($event);
    }
}
