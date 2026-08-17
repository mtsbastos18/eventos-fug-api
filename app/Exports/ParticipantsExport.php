<?php

namespace App\Exports;

use App\Models\Participant;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ParticipantsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping, WithColumnFormatting
{
    protected $eventId;

    public function __construct($eventId)
    {
        $this->eventId = $eventId;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Participant::where('event_id', $this->eventId)
            ->select('name', 'email', 'phone', 'company', 'position', 'city', 'checked_in_at')
            ->get();
    }

    /**
     * Mapeia os dados de cada linha antes de exportar
     */
    public function map($participant): array
    {
        return [
            $participant->name,
            $participant->email,
            $participant->phone,
            $participant->company,
            $participant->position,
            $participant->city,
            // Converte a data do banco para o valor numérico que o Excel entende como data
            $participant->checked_in_at ? Date::dateTimeToExcel(Carbon::parse($participant->checked_in_at)) : null,
        ];
    }

    /**
     * Formata as colunas específicas nativamente no Excel
     */
    public function columnFormats(): array
    {
        return [
            // A coluna 'G' corresponde ao 7º campo ('Data de Check-in')
            'G' => 'dd/mm/yyyy hh:mm:ss',
        ];
    }

    public function headings(): array
    {
        return [
            'Nome',
            'Email',
            'Telefone',
            'Empresa',
            'Cargo',
            'Cidade',
            'Data de Check-in',
        ];
    }
}