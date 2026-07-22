<?php

use Illuminate\Support\Facades\Route;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

Route::get('/', function () {
    return view('welcome');
});

// Rota temporária para testar visualmente a geração do QR Code (remover depois do teste).
Route::get('/test-qrcode', function () {
    $data = request('data', 'urn:uuid:' . \Illuminate\Support\Str::uuid());

    $qrCode = Builder::create()
        ->writer(new PngWriter())
        ->data($data)
        ->size(240)
        ->margin(10)
        ->build();

    return response($qrCode->getString(), 200, [
        'Content-Type' => $qrCode->getMimeType(),
    ]);
});
