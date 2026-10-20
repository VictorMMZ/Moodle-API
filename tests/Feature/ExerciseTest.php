<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role]);
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

    private function makeExercise(ClassRoom $classroom, array $attributes = []): Exercise
    {
        return Exercise::create($attributes + [
            'class_id' => $classroom->id,
            'title' => 'Ecuaciones',
            'description' => 'Resuelve los ejercicios.',
            'type' => 'assignment',
            'max_grade' => 10,
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

    public function test_rutas_requieren_autenticacion(): void
    {
        $exercise = $this->makeExercise($this->makeClassroom($this->makeUser('teacher')));

        $this->getJson('/api/exerciseindex')->assertUnauthorized();
        $this->postJson('/api/exercisestore')->assertUnauthorized();
        $this->getJson("/api/exerciseshow/{$exercise->id}")->assertUnauthorized();
        $this->putJson("/api/exerciseupdate/{$exercise->id}")->assertUnauthorized();
        $this->deleteJson("/api/exercisedestroy/{$exercise->id}")->assertUnauthorized();
    }

    public function test_profesor_solo_lista_ejercicios_de_sus_clases(): void
    {
        $teacher = $this->makeUser('teacher');
        $mine = $this->makeExercise($this->makeClassroom($teacher));
        $this->makeExercise($this->makeClassroom($this->makeUser('teacher')));
        $this->actingAs($teacher, 'web');

        $this->getJson('/api/exerciseindex')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);
    }

    public function test_alumno_solo_lista_ejercicios_publicados_de_clases_activas(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $enrolled = $this->makeClassroom($teacher);
        $inactive = $this->makeClassroom($teacher);
        $this->enroll($enrolled, $student);
        $this->enroll($inactive, $student, 'inactive');
        $published = $this->makeExercise($enrolled, ['published_at' => now()->subMinute()]);
        $this->makeExercise($enrolled, ['published_at' => null]);
        $this->makeExercise($enrolled, ['published_at' => now()->addDay()]);
        $this->makeExercise($inactive, ['published_at' => now()->subMinute()]);
        $this->actingAs($student, 'web');

        $this->getJson('/api/exerciseindex')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $published->id);
    }

    public function test_admin_lista_todos_los_ejercicios(): void
    {
        $this->makeExercise($this->makeClassroom($this->makeUser('teacher')));
        $this->makeExercise($this->makeClassroom($this->makeUser('teacher')));
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->getJson('/api/exerciseindex')->assertOk()->assertJsonCount(2);
    }

    public function test_profesor_puede_crear_ejercicio_en_su_clase(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $response = $this->postJson('/api/exercisestore', [
            'class_id' => $classroom->id,
            'title' => 'Examen final',
            'description' => 'Temas 1 a 5.',
            'type' => 'exam',
            'max_grade' => 20,
            'published_at' => now()->toDateTimeString(),
            'due_date' => now()->addWeek()->toDateTimeString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'Examen final')
            ->assertJsonPath('class_id', $classroom->id)
            ->assertJsonPath('class_room.id', $classroom->id);
        $this->assertDatabaseHas('exercises', [
            'title' => 'Examen final',
            'class_id' => $classroom->id,
        ]);
    }

    public function test_usuario_sin_permisos_no_puede_crear_ejercicio_en_otra_clase(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->postJson('/api/exercisestore', [
            'class_id' => $classroom->id,
            'title' => 'No autorizado',
            'description' => 'Contenido',
            'type' => 'assignment',
            'max_grade' => 10,
        ])->assertForbidden();

        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_crear_ejercicio_valida_campos_y_no_acepta_nota_fuera_de_rango(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->postJson('/api/exercisestore', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['class_id', 'title', 'description', 'type', 'max_grade']);
        $this->postJson('/api/exercisestore', [
            'class_id' => $classroom->id,
            'title' => 'Inválido',
            'description' => 'Contenido',
            'type' => 'assignment',
            'max_grade' => -1,
        ])->assertUnprocessable()->assertJsonValidationErrors('max_grade');
    }

    public function test_alumno_solo_puede_ver_ejercicio_publicado_de_su_clase(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->enroll($classroom, $student);
        $published = $this->makeExercise($classroom, ['published_at' => now()->subMinute()]);
        $unpublished = $this->makeExercise($classroom, ['published_at' => null]);
        $this->actingAs($student, 'web');

        $this->getJson("/api/exerciseshow/{$published->id}")->assertOk();
        $this->getJson("/api/exerciseshow/{$unpublished->id}")->assertForbidden();
    }

    public function test_profesor_puede_actualizar_y_eliminar_su_ejercicio(): void
    {
        $teacher = $this->makeUser('teacher');
        $exercise = $this->makeExercise($this->makeClassroom($teacher));
        $this->actingAs($teacher, 'web');

        $this->putJson("/api/exerciseupdate/{$exercise->id}", ['title' => 'Título actualizado'])
            ->assertOk()
            ->assertJsonPath('title', 'Título actualizado');

        $this->deleteJson("/api/exercisedestroy/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Ejercicio eliminado.');

        $this->assertSoftDeleted('exercises', ['id' => $exercise->id]);
        $this->getJson("/api/exerciseshow/{$exercise->id}")->assertNotFound();
    }

    public function test_admin_puede_gestionar_ejercicio_de_cualquier_clase(): void
    {
        $exercise = $this->makeExercise($this->makeClassroom($this->makeUser('teacher')));
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->putJson("/api/exerciseupdate/{$exercise->id}", ['type' => 'quiz'])->assertOk();
        $this->deleteJson("/api/exercisedestroy/{$exercise->id}")->assertOk();
    }
}
