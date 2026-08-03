<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(): array
    {
        $user = User::factory()->create();
        $token = auth('api')->login($user);

        return ['Authorization' => "Bearer {$token}"];
    }

    private function eventWithParticipants(): Event
    {
        $event = Event::create([
            'title' => 'Evento de Teste',
            'date' => now(),
            'location' => 'Auditório',
            'capacity' => 10,
            'workload_hours' => 8,
        ]);

        Participant::create([
            'name' => 'Checado Silva',
            'email' => 'checado@example.com',
            'phone' => '11999999999',
            'document' => '11122233344',
            'event_id' => $event->id,
            'is_verified' => true,
            'checked_in_at' => now(),
        ]);

        Participant::create([
            'name' => 'Nao Checado',
            'email' => 'naochecado@example.com',
            'phone' => '11999999998',
            'document' => '55566677788',
            'event_id' => $event->id,
            'is_verified' => true,
            'checked_in_at' => null,
        ]);

        return $event;
    }

    public function test_issue_only_creates_certificates_for_checked_in_participants(): void
    {
        $event = $this->eventWithParticipants();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/admin/events/{$event->id}/certificates/issue");

        $response->assertOk()->assertJson(['issued' => 1, 'total_certificates' => 1]);

        $this->assertDatabaseCount('certificates', 1);
        $this->assertDatabaseHas('certificates', [
            'event_id' => $event->id,
            'participant_id' => Participant::where('email', 'checado@example.com')->value('id'),
        ]);
    }

    public function test_issue_is_idempotent(): void
    {
        $event = $this->eventWithParticipants();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson("/api/admin/events/{$event->id}/certificates/issue")->assertOk();
        $second = $this->withHeaders($headers)->postJson("/api/admin/events/{$event->id}/certificates/issue");

        $second->assertOk()->assertJson(['issued' => 0, 'total_certificates' => 1]);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_send_is_blocked_until_template_is_published(): void
    {
        $event = $this->eventWithParticipants();
        $headers = $this->authHeaders();

        $this->withHeaders($headers)->postJson("/api/admin/events/{$event->id}/certificates/issue");

        $response = $this->withHeaders($headers)->postJson("/api/admin/events/{$event->id}/certificates/send");

        $response->assertStatus(422);
        $this->assertDatabaseHas('certificates', ['event_id' => $event->id, 'email_status' => 'pending']);
    }

    public function test_send_queues_emails_once_template_is_published(): void
    {
        $event = $this->eventWithParticipants();
        $headers = $this->authHeaders();

        CertificateTemplate::create([
            'event_id' => $event->id,
            'fields' => [['id' => 'n', 'variable' => 'participante.nome', 'x' => 50, 'y' => 50, 'width' => 80, 'font_size' => 20]],
            'is_published' => true,
        ]);

        $this->withHeaders($headers)->postJson("/api/admin/events/{$event->id}/certificates/issue");
        $response = $this->withHeaders($headers)->postJson("/api/admin/events/{$event->id}/certificates/send");

        $response->assertOk()->assertJson(['queued' => 1]);
        $this->assertDatabaseHas('certificates', ['event_id' => $event->id, 'email_status' => 'sent']);
    }

    public function test_public_download_rejects_wrong_document_suffix(): void
    {
        $event = $this->eventWithParticipants();
        $participant = Participant::where('email', 'checado@example.com')->first();

        CertificateTemplate::create([
            'event_id' => $event->id,
            'fields' => [['id' => 'n', 'variable' => 'participante.nome', 'x' => 50, 'y' => 50, 'width' => 80, 'font_size' => 20]],
            'is_published' => true,
        ]);

        $certificate = Certificate::create(['event_id' => $event->id, 'participant_id' => $participant->id]);

        $response = $this->postJson("/api/certificates/{$certificate->token}/download", ['document' => '00000']);

        $response->assertStatus(422);
    }

    public function test_public_download_succeeds_with_correct_document_suffix(): void
    {
        $event = $this->eventWithParticipants();
        $participant = Participant::where('email', 'checado@example.com')->first();

        CertificateTemplate::create([
            'event_id' => $event->id,
            'fields' => [['id' => 'n', 'variable' => 'participante.nome', 'x' => 50, 'y' => 50, 'width' => 80, 'font_size' => 20]],
            'is_published' => true,
        ]);

        $certificate = Certificate::create(['event_id' => $event->id, 'participant_id' => $participant->id]);

        // document = 11122233344 -> últimos 5 dígitos: 33344
        $response = $this->postJson("/api/certificates/{$certificate->token}/download", ['document' => '33344']);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertEquals(1, $certificate->fresh()->download_count);
    }

    public function test_public_download_locks_token_after_too_many_wrong_attempts(): void
    {
        $event = $this->eventWithParticipants();
        $participant = Participant::where('email', 'checado@example.com')->first();

        CertificateTemplate::create([
            'event_id' => $event->id,
            'fields' => [],
            'is_published' => true,
        ]);

        $certificate = Certificate::create(['event_id' => $event->id, 'participant_id' => $participant->id]);

        for ($i = 0; $i < 15; $i++) {
            $this->postJson("/api/certificates/{$certificate->token}/download", ['document' => '00000']);
        }

        $response = $this->postJson("/api/certificates/{$certificate->token}/download", ['document' => '33344']);

        $response->assertStatus(429);
    }

    public function test_public_show_returns_masked_name_for_valid_token(): void
    {
        $event = $this->eventWithParticipants();
        $participant = Participant::where('email', 'checado@example.com')->first();
        $certificate = Certificate::create(['event_id' => $event->id, 'participant_id' => $participant->id]);

        $response = $this->getJson("/api/certificates/{$certificate->token}");

        $response->assertOk()
            ->assertJsonPath('participant_name_masked', 'C****** S****')
            ->assertJsonPath('code', $certificate->code);
    }

    public function test_public_show_returns_404_for_unknown_token(): void
    {
        $response = $this->getJson('/api/certificates/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_admin_can_delete_certificate_template(): void
    {
        $event = $this->eventWithParticipants();
        $headers = $this->authHeaders();

        CertificateTemplate::create([
            'event_id' => $event->id,
            'fields' => [['id' => 'n', 'variable' => 'participante.nome', 'x' => 50, 'y' => 50, 'width' => 80, 'font_size' => 20]],
            'is_published' => true,
        ]);

        $response = $this->withHeaders($headers)->deleteJson("/api/admin/events/{$event->id}/certificate-template");

        $response->assertOk();
        $this->assertDatabaseMissing('certificate_templates', ['event_id' => $event->id]);

        $show = $this->withHeaders($headers)->getJson("/api/admin/events/{$event->id}/certificate-template");
        $show->assertOk()->assertJson(['is_published' => false, 'fields' => []]);
    }

    public function test_deleting_template_returns_404_when_none_exists(): void
    {
        $event = $this->eventWithParticipants();

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson("/api/admin/events/{$event->id}/certificate-template");

        $response->assertStatus(404);
    }

    public function test_already_downloaded_certificate_survives_template_deletion(): void
    {
        $event = $this->eventWithParticipants();
        $participant = Participant::where('email', 'checado@example.com')->first();
        $headers = $this->authHeaders();

        CertificateTemplate::create([
            'event_id' => $event->id,
            'fields' => [['id' => 'n', 'variable' => 'participante.nome', 'x' => 50, 'y' => 50, 'width' => 80, 'font_size' => 20]],
            'is_published' => true,
        ]);

        $certificate = Certificate::create(['event_id' => $event->id, 'participant_id' => $participant->id]);

        // Gera e cacheia o PDF (download admin) antes de excluir a matriz.
        $this->withHeaders($headers)
            ->get("/api/admin/events/{$event->id}/certificates/{$certificate->id}/download")
            ->assertOk();

        $this->withHeaders($headers)->deleteJson("/api/admin/events/{$event->id}/certificate-template")->assertOk();

        // O participante ainda deve conseguir baixar o PDF já cacheado.
        $response = $this->postJson("/api/certificates/{$certificate->token}/download", ['document' => '33344']);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
