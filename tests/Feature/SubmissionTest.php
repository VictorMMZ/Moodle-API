<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\ClassStudent;
use App\Models\Exercise;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function makeExercise(User $teacher, array $attributes = []): Exercise
    {
        $classroom = ClassRoom::create([
            'teacher_id' => $teacher->id,
            'name' => 'Matemáticas',
            'description' => 'Clase de prueba',
            'code' => strtoupper(fake()->unique()->bothify('????####')),
        ]);

        return Exercise::create($attributes + [
            'class_id' => $classroom->id,
            'title' => 'Tarea',
            'description' => 'Resuelve los ejercicios.',
            'type' => 'assignment',
            'max_grade' => 10,
            'published_at' => now()->subMinute(),
        ]);
    }

    private function enroll(Exercise $exercise, User $student): void
    {
        ClassStudent::create([
            'class_id' => $exercise->class_id,
            'student_id' => $student->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_alumno_puede_entregar_archivos_pdf_doc_e_imagenes(): void
    {
        Storage::fake('local');
        $student = $this->makeUser('student');
        $teacher = $this->makeUser('teacher');
        $pdfExercise = $this->makeExercise($teacher);
        $docExercise = $this->makeExercise($teacher);
        $imageExercise = $this->makeExercise($teacher);
        $this->enroll($pdfExercise, $student);
        $this->enroll($docExercise, $student);
        $this->enroll($imageExercise, $student);
        $this->actingAs($student, 'web');

        $this->post('/api/submissionstore', [
            'exercise_id' => $pdfExercise->id,
            'file' => UploadedFile::fake()->create('respuesta.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'submitted');

        $this->post('/api/submissionstore', [
            'exercise_id' => $docExercise->id,
            'file' => UploadedFile::fake()->create('respuesta.doc', 20, 'application/msword'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'submitted');

        $this->post('/api/submissionstore', [
            'exercise_id' => $imageExercise->id,
            'file' => UploadedFile::fake()->createWithContent(
                'respuesta.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('status', 'submitted');

        $this->assertDatabaseCount('submissions', 3);
        $this->assertCount(3, Storage::disk('local')->allFiles('submissions'));
    }

    public function test_alumno_no_puede_entregar_en_una_clase_sin_matricula_activa(): void
    {
        $student = $this->makeUser('student');
        $exercise = $this->makeExercise($this->makeUser('teacher'));
        $this->actingAs($student, 'web');

        $this->postJson('/api/submissionstore', [
            'exercise_id' => $exercise->id,
            'content' => 'Mi respuesta',
        ])->assertForbidden();

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_profesor_puede_calificar_entrega_de_su_clase_y_no_superar_nota_maxima(): void
    {
        $teacher = $this->makeUser('teacher');
        $student = $this->makeUser('student');
        $exercise = $this->makeExercise($teacher);
        $this->enroll($exercise, $student);
        $submission = Submission::create([
            'exercise_id' => $exercise->id,
            'student_id' => $student->id,
            'content' => 'Mi respuesta',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $this->actingAs($teacher, 'web');

        $this->putJson("/api/submissiongrade/{$submission->id}", [
            'grade' => 8.5,
            'feedback' => 'Buen trabajo.',
        ])->assertOk()
            ->assertJsonPath('status', 'graded')
            ->assertJsonPath('grade', '8.50');

        $this->putJson("/api/submissiongrade/{$submission->id}", ['grade' => 11])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('grade');
    }

    public function test_no_se_aceptan_archivos_de_tipos_no_permitidos(): void
    {
        Storage::fake('local');
        $student = $this->makeUser('student');
        $exercise = $this->makeExercise($this->makeUser('teacher'));
        $this->enroll($exercise, $student);
        $this->actingAs($student, 'web');

        $this->post('/api/submissionstore', [
            'exercise_id' => $exercise->id,
            'file' => UploadedFile::fake()->create('respuesta.txt', 20, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }
}
