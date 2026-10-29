<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Models\Notification;
use Illuminate\Console\Command;

class SendDueSoonNotifications extends Command
{
    protected $signature = 'notifications:due-soon';

    protected $description = 'Avisa a los alumnos de ejercicios sin entregar que vencen en los próximos 2 días';

    public function handle(): int
    {
        $exercises = Exercise::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereBetween('due_date', [now(), now()->addDays(2)])
            ->with('classRoom')
            ->get();

        $sent = 0;

        foreach ($exercises as $exercise) {
            $studentIds = $exercise->classRoom->students()
                ->wherePivot('status', 'active')
                ->pluck('users.id');

            $submittedIds = $exercise->submissions()->pluck('student_id');

            // Evita duplicados si el comando se ejecuta más de una vez
            $alreadyNotified = Notification::withTrashed()
                ->where('type', Notification::TYPE_DUE_SOON)
                ->where('data->exercise_id', $exercise->id)
                ->pluck('user_id');

            foreach ($studentIds->diff($submittedIds)->diff($alreadyNotified) as $studentId) {
                Notification::notifyDueSoon($exercise, $studentId);
                $sent++;
            }
        }

        $this->info("Notificaciones enviadas: {$sent}");

        return self::SUCCESS;
    }
}
