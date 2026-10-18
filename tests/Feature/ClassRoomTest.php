<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassRoomTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function makeClassroom(User $teacher, array $attrs = []): ClassRoom
    {
        return ClassRoom::create($attrs + [
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

    // ---------- Autenticación ----------

    public function test_rutas_requieren_autenticacion(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);

        $this->getJson('/api/classindex')->assertStatus(401);
        $this->postJson('/api/classstore', ['name' => 'X'])->assertStatus(401);
        $this->getJson("/api/classshow/{$classroom->id}")->assertStatus(401);
        $this->putJson("/api/classupdate/{$classroom->id}", ['name' => 'X'])->assertStatus(401);
        $this->deleteJson("/api/classdestroy/{$classroom->id}")->assertStatus(401);
        $this->getJson("/api/participants/{$classroom->id}")->assertStatus(401);
        $this->postJson("/api/participants/{$classroom->id}", ['user_id' => 1])->assertStatus(401);
        $this->deleteJson("/api/participants/{$classroom->id}/1")->assertStatus(401);
    }

    // ---------- index ----------

    public function test_profesor_solo_lista_sus_clases(): void
    {
        $teacher = $this->makeUser('teacher');
        $otherTeacher = $this->makeUser('teacher');
        $mine = $this->makeClassroom($teacher);
        $this->makeClassroom($otherTeacher);

        $this->actingAs($teacher, 'web');

        $this->getJson('/api/classindex')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);
    }

    public function test_admin_lista_todas_las_clases(): void
    {
        $teacher = $this->makeUser('teacher');
        $this->makeClassroom($teacher);
        $this->makeClassroom($teacher);

        $this->actingAs($this->makeUser('admin'), 'web');

        $this->getJson('/api/classindex')->assertOk()->assertJsonCount(2);
    }

    public function test_alumno_solo_lista_clases_con_matricula_activa(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $active = $this->makeClassroom($teacher);
        $inactive = $this->makeClassroom($teacher);
        $this->makeClassroom($teacher); // sin matrícula
        $this->enroll($active, $student);
        $this->enroll($inactive, $student, 'inactive');

        $this->actingAs($student, 'web');

        $this->getJson('/api/classindex')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $active->id);
    }

    // ---------- store ----------

    public function test_profesor_puede_crear_clase(): void
    {
        $teacher = $this->makeUser('teacher');
        $this->actingAs($teacher, 'web');

        $response = $this->postJson('/api/classstore', [
            'name' => 'Historia',
            'description' => 'Historia universal',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('name', 'Historia')
            ->assertJsonPath('teacher_id', $teacher->id);
        $this->assertNotEmpty($response->json('code'));
        $this->assertDatabaseHas('classrooms', ['name' => 'Historia', 'teacher_id' => $teacher->id]);
    }

    public function test_descripcion_es_opcional_al_crear(): void
    {
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->postJson('/api/classstore', ['name' => 'Sin descripcion'])->assertStatus(201);
    }

    public function test_alumno_no_puede_crear_clase(): void
    {
        $this->actingAs($this->makeUser('student'), 'web');

        $this->postJson('/api/classstore', ['name' => 'Historia'])->assertStatus(403);
        $this->assertDatabaseCount('classrooms', 0);
    }

    public function test_admin_no_puede_crear_clase(): void
    {
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->postJson('/api/classstore', ['name' => 'Historia'])->assertStatus(403);
    }

    public function test_crear_clase_valida_nombre(): void
    {
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->postJson('/api/classstore', [])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/classstore', ['name' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/classstore', ['name' => ['array']])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_crear_clase_valida_descripcion_string(): void
    {
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->postJson('/api/classstore', ['name' => 'X', 'description' => ['a']])
            ->assertStatus(422)->assertJsonValidationErrors('description');
    }

    public function test_crear_clase_ignora_teacher_id_y_code_enviados(): void
    {
        $teacher = $this->makeUser('teacher');
        $other = $this->makeUser('teacher');
        $this->actingAs($teacher, 'web');

        $response = $this->postJson('/api/classstore', [
            'name' => 'Segura',
            'teacher_id' => $other->id,
            'code' => 'HACKED',
        ]);

        $response->assertStatus(201)->assertJsonPath('teacher_id', $teacher->id);
        $this->assertNotSame('HACKED', $response->json('code'));
    }

    // ---------- show ----------

    public function test_profesor_de_la_clase_puede_verla(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->getJson("/api/classshow/{$classroom->id}")
            ->assertOk()
            ->assertJsonPath('id', $classroom->id)
            ->assertJsonPath('teacher.id', $teacher->id);
    }

    public function test_alumno_matriculado_puede_ver_la_clase(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($teacher);
        $this->enroll($classroom, $student);
        $this->actingAs($student, 'web');

        $this->getJson("/api/classshow/{$classroom->id}")->assertOk();
    }

    public function test_admin_puede_ver_cualquier_clase(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->getJson("/api/classshow/{$classroom->id}")->assertOk();
    }

    public function test_alumno_no_matriculado_no_puede_ver_la_clase(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('student'), 'web');

        $this->getJson("/api/classshow/{$classroom->id}")->assertStatus(403);
    }

    public function test_alumno_con_matricula_inactiva_no_puede_ver_la_clase(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->enroll($classroom, $student, 'inactive');
        $this->actingAs($student, 'web');

        $this->getJson("/api/classshow/{$classroom->id}")->assertStatus(403);
    }

    public function test_profesor_ajeno_no_puede_ver_la_clase(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->getJson("/api/classshow/{$classroom->id}")->assertStatus(403);
    }

    public function test_ver_clase_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->getJson('/api/classshow/9999')->assertStatus(404);
    }

    // ---------- update ----------

    public function test_profesor_puede_actualizar_su_clase(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->putJson("/api/classupdate/{$classroom->id}", [
            'name' => 'Nuevo nombre',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('name', 'Nuevo nombre');

        $this->assertDatabaseHas('classrooms', [
            'id' => $classroom->id,
            'name' => 'Nuevo nombre',
            'is_active' => false,
        ]);
    }

    public function test_actualizacion_parcial_no_modifica_otros_campos(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher, ['description' => 'Original']);
        $this->actingAs($teacher, 'web');

        $this->putJson("/api/classupdate/{$classroom->id}", ['name' => 'Solo nombre'])->assertOk();

        $this->assertDatabaseHas('classrooms', ['id' => $classroom->id, 'description' => 'Original']);
    }

    public function test_admin_puede_actualizar_cualquier_clase(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->putJson("/api/classupdate/{$classroom->id}", ['name' => 'Admin'])->assertOk();
    }

    public function test_profesor_ajeno_no_puede_actualizar(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'), ['name' => 'Original']);
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->putJson("/api/classupdate/{$classroom->id}", ['name' => 'Hack'])->assertStatus(403);
        $this->assertDatabaseHas('classrooms', ['id' => $classroom->id, 'name' => 'Original']);
    }

    public function test_alumno_no_puede_actualizar(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->enroll($classroom, $student);
        $this->actingAs($student, 'web');

        $this->putJson("/api/classupdate/{$classroom->id}", ['name' => 'Hack'])->assertStatus(403);
    }

    public function test_actualizar_valida_campos(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->putJson("/api/classupdate/{$classroom->id}", ['name' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson("/api/classupdate/{$classroom->id}", ['name' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson("/api/classupdate/{$classroom->id}", ['is_active' => 'no-es-booleano'])
            ->assertStatus(422)->assertJsonValidationErrors('is_active');
    }

    public function test_actualizar_no_permite_cambiar_teacher_id_ni_code(): void
    {
        $teacher = $this->makeUser('teacher');
        $other = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher, ['code' => 'ORIGINAL1']);
        $this->actingAs($teacher, 'web');

        $this->putJson("/api/classupdate/{$classroom->id}", [
            'name' => 'Ok',
            'teacher_id' => $other->id,
            'code' => 'CAMBIADO',
        ])->assertOk();

        $this->assertDatabaseHas('classrooms', [
            'id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'code' => 'ORIGINAL1',
        ]);
    }

    public function test_actualizar_clase_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->putJson('/api/classupdate/9999', ['name' => 'X'])->assertStatus(404);
    }

    // ---------- destroy ----------

    public function test_profesor_puede_eliminar_su_clase_con_soft_delete(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->deleteJson("/api/classdestroy/{$classroom->id}")->assertOk();

        $this->assertSoftDeleted('classrooms', ['id' => $classroom->id]);
    }

    public function test_admin_puede_eliminar_cualquier_clase(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->deleteJson("/api/classdestroy/{$classroom->id}")->assertOk();
    }

    public function test_profesor_ajeno_no_puede_eliminar(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->deleteJson("/api/classdestroy/{$classroom->id}")->assertStatus(403);
        $this->assertNotSoftDeleted('classrooms', ['id' => $classroom->id]);
    }

    public function test_alumno_no_puede_eliminar(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->enroll($classroom, $student);
        $this->actingAs($student, 'web');

        $this->deleteJson("/api/classdestroy/{$classroom->id}")->assertStatus(403);
    }

    public function test_clase_eliminada_ya_no_se_puede_consultar(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $classroom->delete();
        $this->actingAs($teacher, 'web');

        $this->getJson("/api/classshow/{$classroom->id}")->assertStatus(404);
    }

    // ---------- enrollStudent ----------

    public function test_profesor_puede_matricular_alumno(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->postJson("/api/participants/{$classroom->id}", ['email' => $student->email])->assertOk();

        $this->assertDatabaseHas('class_students', [
            'class_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_matricular_dos_veces_no_duplica_y_reactiva(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($teacher);
        $this->enroll($classroom, $student, 'inactive');
        $this->actingAs($teacher, 'web');

        $this->postJson("/api/participants/{$classroom->id}", ['email' => $student->email])->assertOk();
        $this->postJson("/api/participants/{$classroom->id}", ['email' => $student->email])->assertOk();

        $this->assertDatabaseCount('class_students', 1);
        $this->assertDatabaseHas('class_students', ['student_id' => $student->id, 'status' => 'active']);
    }

    public function test_no_se_puede_matricular_a_un_no_alumno(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->postJson("/api/participants/{$classroom->id}", ['user_id' => $this->makeUser('teacher')->id])
            ->assertStatus(422);
        $this->postJson("/api/participants/{$classroom->id}", ['user_id' => $this->makeUser('admin')->id])
            ->assertStatus(422);
        $this->assertDatabaseCount('class_students', 0);
    }

    public function test_matricular_valida_user_id(): void
    {
        $teacher = $this->makeUser('teacher');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->postJson("/api/participants/{$classroom->id}", [])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson("/api/participants/{$classroom->id}", ['email' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson("/api/participants/{$classroom->id}", ['email' => 'nonexistent@example.com'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_profesor_ajeno_no_puede_matricular(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->postJson("/api/participants/{$classroom->id}", ['user_id' => $student->id])->assertStatus(403);
        $this->assertDatabaseCount('class_students', 0);
    }

    public function test_alumno_no_puede_matricular(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($student, 'web');

        $this->postJson("/api/participants/{$classroom->id}", ['user_id' => $student->id])->assertStatus(403);
    }

    public function test_matricular_en_clase_inexistente_devuelve_404(): void
    {
        $student = $this->makeUser('student');
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->postJson('/api/participants/9999', ['user_id' => $student->id])->assertStatus(404);
    }

    // ---------- unenrollStudent ----------

    public function test_profesor_puede_dar_de_baja_alumno(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($teacher);
        $this->enroll($classroom, $student);
        $this->actingAs($teacher, 'web');

        $this->deleteJson("/api/participants/{$classroom->id}/{$student->id}")->assertOk();

        // La fila se conserva con status inactive
        $this->assertDatabaseHas('class_students', [
            'class_id' => $classroom->id,
            'student_id' => $student->id,
            'status' => 'inactive',
        ]);
    }

    public function test_dar_de_baja_alumno_no_matriculado_devuelve_404(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($teacher);
        $this->actingAs($teacher, 'web');

        $this->deleteJson("/api/participants/{$classroom->id}/{$student->id}")->assertStatus(404);
    }

    public function test_dar_de_baja_alumno_ya_inactivo_devuelve_404(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($teacher);
        $this->enroll($classroom, $student, 'inactive');
        $this->actingAs($teacher, 'web');

        $this->deleteJson("/api/participants/{$classroom->id}/{$student->id}")->assertStatus(404);
    }

    public function test_profesor_ajeno_no_puede_dar_de_baja(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->enroll($classroom, $student);
        $this->actingAs($this->makeUser('teacher'), 'web');

        $this->deleteJson("/api/participants/{$classroom->id}/{$student->id}")->assertStatus(403);
        $this->assertDatabaseHas('class_students', ['student_id' => $student->id, 'status' => 'active']);
    }

    public function test_alumno_no_puede_darse_de_baja_por_la_api(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->enroll($classroom, $student);
        $this->actingAs($student, 'web');

        $this->deleteJson("/api/participants/{$classroom->id}/{$student->id}")->assertStatus(403);
    }

    // ---------- participants ----------

    public function test_participantes_devuelve_profesor_y_alumnos_activos(): void
    {
        $teacher = $this->makeUser('teacher');
        $active = $this->makeUser('student');
        $inactive = $this->makeUser('student');
        $classroom = $this->makeClassroom($teacher);
        $this->enroll($classroom, $active);
        $this->enroll($classroom, $inactive, 'inactive');
        $this->actingAs($teacher, 'web');

        $this->getJson("/api/participants/{$classroom->id}")
            ->assertOk()
            ->assertJsonPath('teacher.id', $teacher->id)
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.id', $active->id)
            ->assertJsonMissingPath('students.0.password');
    }

    public function test_alumno_matriculado_puede_ver_participantes(): void
    {
        $student = $this->makeUser('student');
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->enroll($classroom, $student);
        $this->actingAs($student, 'web');

        $this->getJson("/api/participants/{$classroom->id}")->assertOk();
    }

    public function test_usuario_sin_acceso_no_puede_ver_participantes(): void
    {
        $classroom = $this->makeClassroom($this->makeUser('teacher'));
        $this->actingAs($this->makeUser('student'), 'web');

        $this->getJson("/api/participants/{$classroom->id}")->assertStatus(403);
    }

    public function test_participantes_de_clase_inexistente_devuelve_404(): void
    {
        $this->actingAs($this->makeUser('admin'), 'web');

        $this->getJson('/api/participants/9999')->assertStatus(404);
    }
}
