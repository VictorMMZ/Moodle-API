<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\Exercise;
use App\Models\Notification;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    // Cambia de usuario dentro de un mismo test limpiando el guard cacheado
    private function asUser(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }

    private function makeClassroom(User $teacher): ClassRoom
    {
        return ClassRoom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Matemáticas',
            'description' => 'Clase de prueba',
            'code' => strtoupper(fake()->unique()->bothify('????####')),
        ]);
    }

    private function enroll(ClassRoom $classroom, User $student, string $status = 'active'): void
    {
        ClassStudent::create([
            'class_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => $status,
            'joined_at' => now(),
        ]);
    }

    private function makeNotification(User $user, array $attributes = []): Notification
    {
        return Notification::create($attributes + [
            'user_id' => $user->id,
            'type' => Notification::TYPE_NEW_EXERCISE,
            'title' => 'Título',
            'message' => 'Mensaje',
        ]);
    }

    private function exercisePayload(ClassRoom $classroom, array $overrides = []): array
    {
        return $overrides + [
            'class_id' => $classroom->id,
            'title' => 'Tarea nueva',
            'description' => 'Resuelve los ejercicios.',
            'type' => 'tarea',
            'max_grade' => 10,
            'published_at' => now()->subMinute()->toDateTimeString(),
        ];
    }

   // TESTS

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/notificationindex')->assertUnauthorized();
    }

    public function test_index_devuelve_solo_las_notificaciones_del_usuario(): void
    {
        $student = $this->makeUser('student');
        $other = $this->makeUser('student');
        $mine = $this->makeNotification($student);
        $this->makeNotification($other);

        $this->actingAs($student, 'web')
            ->getJson('/api/notificationindex')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);
    }

    public function test_index_filtra_no_leidas(): void
    {
        $student = $this->makeUser('student');
        $this->makeNotification($student);
        $this->makeNotification($student, ['read_at' => now()]);

        $this->actingAs($student, 'web');

        $this->getJson('/api/notificationindex')->assertJsonCount(2);
        $this->getJson('/api/notificationindex?unread=1')->assertJsonCount(1);
    }

    public function test_unread_count_cuenta_solo_no_leidas_propias(): void
    {
        $student = $this->makeUser('student');
        $this->makeNotification($student);
        $this->makeNotification($student);
        $this->makeNotification($student, ['read_at' => now()]);
        $this->makeNotification($this->makeUser('student'));

        $this->actingAs($student, 'web')
            ->getJson('/api/notificationunread')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_show_devuelve_la_notificacion_propia(): void
    {
        $student = $this->makeUser('student');
        $notification = $this->makeNotification($student);

        $this->actingAs($student, 'web')
            ->getJson("/api/notificationshow/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('id', $notification->id);
    }

    public function test_show_prohibe_notificacion_ajena(): void
    {
        $notification = $this->makeNotification($this->makeUser('student'));

        $this->actingAs($this->makeUser('student'), 'web')
            ->getJson("/api/notificationshow/{$notification->id}")
            ->assertForbidden();
    }

    public function test_mark_as_read_marca_la_notificacion(): void
    {
        $student = $this->makeUser('student');
        $notification = $this->makeNotification($student);

        $this->actingAs($student, 'web')
            ->putJson("/api/notificationread/{$notification->id}")
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_no_cambia_la_fecha_si_ya_estaba_leida(): void
    {
        $student = $this->makeUser('student');
        $readAt = now()->subDay()->startOfSecond();
        $notification = $this->makeNotification($student, ['read_at' => $readAt]);

        $this->actingAs($student, 'web')
            ->putJson("/api/notificationread/{$notification->id}")
            ->assertOk();

        $this->assertTrue($notification->fresh()->read_at->equalTo($readAt));
    }

    public function test_mark_as_read_prohibe_notificacion_ajena(): void
    {
        $notification = $this->makeNotification($this->makeUser('student'));

        $this->actingAs($this->makeUser('student'), 'web')
            ->putJson("/api/notificationread/{$notification->id}")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_solo_afecta_al_usuario(): void
    {
        $student = $this->makeUser('student');
        $other = $this->makeUser('student');
        $this->makeNotification($student);
        $this->makeNotification($student);
        $otherNotification = $this->makeNotification($other);

        $this->actingAs($student, 'web')
            ->putJson('/api/notificationreadall')
            ->assertOk();

        $this->assertSame(0, $student->userNotifications()->unread()->count());
        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_destroy_elimina_la_notificacion_propia(): void
    {
        $student = $this->makeUser('student');
        $notification = $this->makeNotification($student);

        $this->actingAs($student, 'web')
            ->deleteJson("/api/notificationdestroy/{$notification->id}")
            ->assertOk();

        $this->assertSoftDeleted('notifications', ['id' => $notification->id]);
    }

    public function test_destroy_prohibe_notificacion_ajena(): void
    {
        $notification = $this->makeNotification($this->makeUser('student'));

        $this->actingAs($this->makeUser('student'), 'web')
            ->deleteJson("/api/notificationdestroy/{$notification->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('notifications', ['id' => $notification->id]);
    }

    //  Disparadores: la notificación llega al alumno 

    public function test_ejercicio_nuevo_notifica_solo_a_alumnos_activos_de_la_clase(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $active = $this->makeUser('student');
        $inactive = $this->makeUser('student');
        $outsider = $this->makeUser('student');
        $this->enroll($classroom, $active);
        $this->enroll($classroom, $inactive, 'inactive');

        $response = $this->actingAs($teacher, 'web')
            ->postJson('/api/exercisestore', $this->exercisePayload($classroom))
            ->assertCreated();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $active->id,
            'type' => Notification::TYPE_NEW_EXERCISE,
        ]);
        $this->assertSame($response->json('id'), $active->userNotifications()->first()->data['exercise_id']);
        $this->assertSame(0, $inactive->userNotifications()->count());
        $this->assertSame(0, $outsider->userNotifications()->count());

        // y el alumno la recibe por la API
        $this->asUser($active)
            ->getJson('/api/notificationindex')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.type', Notification::TYPE_NEW_EXERCISE);
    }

    public function test_ejercicio_sin_publicar_no_notifica_hasta_que_se_publica(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $student = $this->makeUser('student');
        $this->enroll($classroom, $student);
        $this->actingAs($teacher, 'web');

        $response = $this->postJson('/api/exercisestore', $this->exercisePayload($classroom, ['published_at' => null]))
            ->assertCreated();
        $this->assertDatabaseCount('notifications', 0);

        $this->putJson('/api/exerciseupdate/'.$response->json('id'), [
            'published_at' => now()->subMinute()->toDateTimeString(),
        ])->assertOk();

        $this->assertSame(1, $student->userNotifications()->count());
    }

    public function test_ejercicio_programado_a_futuro_no_notifica_al_crearlo(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $student = $this->makeUser('student');
        $this->enroll($classroom, $student);

        $this->actingAs($teacher, 'web')
            ->postJson('/api/exercisestore', $this->exercisePayload($classroom, [
                'published_at' => now()->addDays(3)->toDateTimeString(),
            ]))
            ->assertCreated();

        $this->assertSame(0, $student->userNotifications()->count());
    }

    public function test_editar_un_ejercicio_ya_publicado_no_vuelve_a_notificar(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $student = $this->makeUser('student');
        $this->enroll($classroom, $student);
        $this->actingAs($teacher, 'web');

        $response = $this->postJson('/api/exercisestore', $this->exercisePayload($classroom))->assertCreated();
        $this->putJson('/api/exerciseupdate/'.$response->json('id'), ['title' => 'Otro título'])->assertOk();

        $this->assertSame(1, $student->userNotifications()->count());
    }

    public function test_calificar_una_entrega_notifica_al_alumno(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $student = $this->makeUser('student');
        $this->enroll($classroom, $student);
        $exercise = Exercise::factory()->create(['class_id' => $classroom->id, 'max_grade' => 10]);
        $submission = Submission::factory()->create([
            'exercise_id' => $exercise->id,
            'student_id' => $student->id,
        ]);

        $this->actingAs($teacher, 'web')
            ->putJson("/api/submissiongrade/{$submission->id}", ['grade' => 8])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $student->id,
            'type' => Notification::TYPE_GRADED,
        ]);

        $this->asUser($student)
            ->getJson('/api/notificationindex?unread=1')
            ->assertJsonCount(1)
            ->assertJsonPath('0.data.submission_id', $submission->id);
    }

    public function test_calificar_con_error_de_validacion_no_notifica(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $student = $this->makeUser('student');
        $exercise = Exercise::factory()->create(['class_id' => $classroom->id, 'max_grade' => 10]);
        $submission = Submission::factory()->create([
            'exercise_id' => $exercise->id,
            'student_id' => $student->id,
        ]);

        $this->actingAs($teacher, 'web')
            ->putJson("/api/submissiongrade/{$submission->id}", ['grade' => 99])
            ->assertUnprocessable();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_comando_avisa_de_ejercicios_que_vencen_en_2_dias_sin_duplicar(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $pending = $this->makeUser('student');
        $submitted = $this->makeUser('student');
        $this->enroll($classroom, $pending);
        $this->enroll($classroom, $submitted);

        $soon = Exercise::factory()->create([
            'class_id' => $classroom->id,
            'due_date' => now()->addDay(),
        ]);
        Exercise::factory()->create(['class_id' => $classroom->id, 'due_date' => now()->addDays(10)]);
        Exercise::factory()->create(['class_id' => $classroom->id, 'due_date' => now()->subDay()]);
        Submission::factory()->create(['exercise_id' => $soon->id, 'student_id' => $submitted->id]);

        Artisan::call('notifications:due-soon');
        Artisan::call('notifications:due-soon');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $pending->id,
            'type' => Notification::TYPE_DUE_SOON,
        ]);
        $this->assertSame(0, $submitted->userNotifications()->count());

        $this->asUser($pending)
            ->getJson('/api/notificationindex')
            ->assertJsonCount(1)
            ->assertJsonPath('0.data.exercise_id', $soon->id);
    }
}
