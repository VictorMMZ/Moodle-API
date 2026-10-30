<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_solo_consulta_mensajes_en_los_que_participa(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $thirdUser = User::factory()->create();

        $message = Message::create([
            'sender_id' => $otherUser->id,
            'receiver_id' => $user->id,
            'subject' => 'Mensaje recibido',
            'body' => 'Contenido',
        ]);
        Message::create([
            'sender_id' => $otherUser->id,
            'receiver_id' => $thirdUser->id,
            'subject' => 'Mensaje ajeno',
            'body' => 'Contenido',
        ]);

        $this->actingAs($user, 'web')
            ->getJson('/api/messageindex')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $message->id)
            ->assertJsonPath('0.sender.id', $otherUser->id)
            ->assertJsonPath('0.receiver.id', $user->id)
            ->assertJsonMissingPath('0.sender.password');
    }

    public function test_usuario_autenticado_envia_mensaje_con_su_propio_id(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($sender, 'web')
            ->postJson('/api/messagestore', [
                'sender_id' => $otherUser->id,
                'receiver_id' => $receiver->id,
                'subject' => 'Consulta',
                'body' => 'Hola',
            ])
            ->assertCreated()
            ->assertJsonPath('sender_id', $sender->id)
            ->assertJsonPath('receiver_id', $receiver->id);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'subject' => 'Consulta',
        ]);
    }

    public function test_el_destinatario_puede_marcar_el_mensaje_como_leido(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'subject' => 'Consulta',
            'body' => 'Hola',
        ]);

        $this->actingAs($receiver, 'web')
            ->putJson("/api/messageupdate/{$message->id}")
            ->assertOk()
            ->assertJsonStructure(['read_at']);

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_el_remitente_no_puede_marcar_su_mensaje_como_leido(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'subject' => 'Consulta',
            'body' => 'Hola',
        ]);

        $this->actingAs($sender, 'web')
            ->putJson("/api/messageupdate/{$message->id}")
            ->assertForbidden();
    }

    public function test_usuario_ajeno_no_puede_ver_ni_eliminar_un_mensaje(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $outsider = User::factory()->create();
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'subject' => 'Consulta',
            'body' => 'Hola',
        ]);

        $this->actingAs($outsider, 'web')
            ->getJson("/api/messageshow/{$message->id}")
            ->assertForbidden();

        $this->deleteJson("/api/messagedestroy/{$message->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }


    public function test_el_destinatario_puede_ver_su_mensaje(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'subject' => 'Consulta',
            'body' => 'Hola',
        ]);

        $this->actingAs($receiver, 'web')
            ->getJson("/api/messageshow/{$message->id}")
            ->assertOk()
            ->assertJsonPath('id', $message->id);
    }

    public function test_el_remitente_puede_ver_su_mensaje(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $message = Message::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'subject' => 'Consulta',
            'body' => 'Hola',
        ]);

        $this->actingAs($sender, 'web')
            ->getJson("/api/messageshow/{$message->id}")
            ->assertOk()
            ->assertJsonPath('id', $message->id);
    }

    
}
