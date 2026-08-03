<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\CheckinController;
use App\Http\Controllers\Admin\CertificateController;
use App\Http\Controllers\Admin\CertificateTemplateController;
use App\Http\Controllers\ParticipantController;
use App\Http\Controllers\PublicCertificateController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);

    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

// Rotas do Admin (protegidas por JWT)
Route::middleware('auth:api')->prefix('admin')->group(function () {
    Route::get('/events/dashboard', [EventController::class, 'dashboard']);
    Route::get('/events/{event}/participants', [EventController::class, 'participants']);
    Route::apiResource('events', EventController::class);

    // Rota para deletar participante (protegida)
    Route::delete('/events/{event}/participants/{participant}', [ParticipantController::class, 'destroy']);

    // Rotas de check-in (leitura de QR Code via câmera ou baixa manual)
    Route::post('/events/{event}/checkin', [CheckinController::class, 'byToken']);
    Route::post('/events/{event}/participants/{participant}/checkin', [CheckinController::class, 'byParticipant']);
    Route::post('/events/{event}/participants/checkin-bulk', [CheckinController::class, 'bulkCheckin']);

    // Rota para exportar participantes em Excel
    Route::get('/events/{event}/participants/export', [ParticipantController::class, 'export']);

    // Rotas para informações pós-evento
    Route::post('/events/{event}/post-detail', [\App\Http\Controllers\EventPostDetailController::class, 'store']);
    Route::put('/events/{event}/post-detail', [\App\Http\Controllers\EventPostDetailController::class, 'update']);
    Route::get('/events/{event}/post-detail', [\App\Http\Controllers\EventPostDetailController::class, 'show']);

    // Matriz (template) do certificado de participação
    Route::get('/events/{event}/certificate-template', [CertificateTemplateController::class, 'show']);
    Route::post('/events/{event}/certificate-template', [CertificateTemplateController::class, 'store']);
    Route::post('/events/{event}/certificate-template/preview', [CertificateTemplateController::class, 'preview']);
    Route::post('/events/{event}/certificate-template/copy-from/{source}', [CertificateTemplateController::class, 'copyFrom']);

    // Emissão e envio de certificados
    Route::get('/events/{event}/certificates', [CertificateController::class, 'index']);
    Route::post('/events/{event}/certificates/issue', [CertificateController::class, 'issue']);
    Route::post('/events/{event}/certificates/send', [CertificateController::class, 'send']);
    Route::get('/events/{event}/certificates/{certificate}/download', [CertificateController::class, 'download']);
});

// Rotas Públicas (Participantes)
Route::get('/events', [PublicEventController::class, 'index']);
Route::get('/events/past', [PublicEventController::class, 'getPastEvents']);
Route::get('/events/{event}', [PublicEventController::class, 'show']);
Route::post('/events/register', [ParticipantController::class, 'store']);
Route::post('/events/register/verify', [ParticipantController::class, 'verify']);
Route::get('/events/{event}/post-detail', [\App\Http\Controllers\EventPostDetailController::class, 'show']);
Route::post('/events/{event}/post-detail/flickr', [\App\Http\Controllers\EventPostDetailController::class, 'saveFlickrImages']);

// Página pública de download do certificado (autenticada por token + confirmação de CPF)
Route::get('/certificates/{token}', [PublicCertificateController::class, 'show'])
    ->middleware('throttle:30,1');
Route::post('/certificates/{token}/download', [PublicCertificateController::class, 'download'])
    ->middleware('throttle:10,1');