<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class AuthTest extends TestCase
{
    use RefreshDatabase;
    public function test_usuario_puede_autenticarse(): void{
        $user = User::factory()->create([
            'password' => bcrypt($password = 'password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
    }

    public function test_usuario_no_puede_autenticarse_con_credenciales_incorrectas(): void{
        $user = User::factory()->create([
            'password' => bcrypt($password = 'password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'incorrect_password',
        ]);

        $response->assertStatus(401);
    }

    public function test_usuario_no_puede_autenticarse_sin_credenciales(): void{
        $response = $this->postJson('/api/login', [
            'email' => '',
            'password' => '',
        ]);

        $response->assertStatus(422);
    }

    public function test_usuario_no_puede_autenticarse_con_email_invalido(): void{
        $user = User::factory()->create([
            'password' => bcrypt($password = 'password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'invalid_email@examp',
            'password' => $password,
        ]);

        $response->assertStatus(401);
    }

 public function test_usuario_hace_logout(): void
{
$user = User::factory()->create();

$response = $this->actingAs($user, 'web')
    ->withSession([])
    ->postJson('/api/logout');

$response->assertOk()
    ->assertJson([
        'message' => 'Sesión cerrada correctamente',
    ]);

}
    public function test_usuario_obtiene_informacion_autenticada(): void{
        $user = User::factory()->create([
            'password' => bcrypt($password = 'password'),
        ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response = $this->getJson('/api/user');

        $response->assertStatus(200);
    }
}
