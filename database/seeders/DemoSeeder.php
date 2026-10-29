<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\Exercise;
use App\Models\Message;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración. Todas las cuentas usan la contraseña "password".
 *   admin@moodle.test · profesor@moodle.test · alumno@moodle.test
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'username' => 'admin', 'first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@moodle.test',
        ]);

        $mainTeacher = User::factory()->teacher()->create([
            'username' => 'profesor', 'first_name' => 'Pablo', 'last_name' => 'Profesor', 'email' => 'profesor@moodle.test',
        ]);
        $teachers = User::factory()->teacher()->count(2)->create()->prepend($mainTeacher);

        $mainStudent = User::factory()->create([
            'username' => 'alumno', 'first_name' => 'Alba', 'last_name' => 'Alumna', 'email' => 'alumno@moodle.test',
        ]);
        $students = User::factory()->count(20)->create()->prepend($mainStudent);

        foreach ($teachers as $teacher) {
            ClassRoom::factory()->count(2)->create(['teacher_id' => $teacher->id])
                ->each(fn (ClassRoom $class) => $this->fillClass($class, $students, $class->teacher_id === $mainTeacher->id));
        }

        // El alumno de prueba siempre está matriculado en las clases del profesor de prueba
        foreach ($mainTeacher->classrooms as $class) {
            $class->enrollments()->updateOrCreate(
                ['student_id' => $mainStudent->id],
                ['status' => 'active', 'joined_at' => now()]
            );
        }

        $this->seedMessages($mainTeacher, $mainStudent);
    }

    private function fillClass(ClassRoom $class, $students, bool $isMain): void
    {
        $enrolled = $students->random(min(10, $students->count()));
        foreach ($enrolled as $student) {
            $class->enrollments()->create([
                'student_id' => $student->id,
                'status' => 'active',
                'joined_at' => now()->subDays(rand(1, 60)),
            ]);
        }

        // Un par de bajas para ver el estado "inactive"
        $class->enrollments()->inRandomOrder()->limit(1)->update(['status' => 'inactive']);

        $exercises = Exercise::factory()->count(4)->create(['class_id' => $class->id]);
        Exercise::factory()->draft()->create(['class_id' => $class->id]);
        Exercise::factory()->scheduled()->create(['class_id' => $class->id]);

        foreach ($exercises as $exercise) {
            foreach ($class->enrollments()->where('status', 'active')->get() as $enrollment) {
                // ~70% entrega; de esas, la mitad está calificada
                if (rand(1, 100) > 70) {
                    continue;
                }
                $submission = Submission::factory()->for($exercise)->state(['student_id' => $enrollment->student_id]);
                (rand(0, 1) ? $submission->graded((float) $exercise->max_grade) : $submission)->create();
            }
        }
    }

    private function seedMessages(User $teacher, User $student): void
    {
        Message::factory()->count(3)->create(['sender_id' => $teacher->id, 'receiver_id' => $student->id]);
        Message::factory()->read()->count(2)->create(['sender_id' => $student->id, 'receiver_id' => $teacher->id]);
    }
}
