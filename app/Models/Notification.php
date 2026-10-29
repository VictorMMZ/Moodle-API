<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class Notification extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'title',
        'type',
        'message',
        'user_id',
        'read_at',
        "data"
    ];

    #[Override]
    protected function casts():array
    {
        return [
            'read_at' => 'datetime',
            'data' => 'array',
        ];
    }

    // Tipos de notificación disponibles
    public const TYPE_EXERCISE_SUBMITTED = 'exercise_submitted';
    public const TYPE_NEW_EXERCISE = 'new_exercise';
    public const TYPE_DUE_SOON = 'exercise_due_soon';
    public const TYPE_GRADED = 'submission_graded';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    // Avisa a todos los alumnos activos de la clase de que hay un ejercicio nuevo
    public static function notifyNewExercise(Exercise $exercise): void
    {
        $exercise->loadMissing('classRoom');
        $studentIds = $exercise->classRoom->students()
            ->wherePivot('status', 'active')
            ->pluck('users.id');

        foreach ($studentIds as $studentId) {
            self::create([
                'user_id' => $studentId,
                'type' => self::TYPE_NEW_EXERCISE,
                'title' => 'Nuevo ejercicio',
                'message' => "El profesor ha publicado \"{$exercise->title}\" en {$exercise->classRoom->name}.",
                'data' => ['exercise_id' => $exercise->id, 'class_id' => $exercise->class_id],
            ]);
        }
    }

    // Avisa al alumno de que su entrega ha sido corregida
    public static function notifyGraded(Submission $submission): void
    {
        $submission->loadMissing('exercise');

        self::create([
            'user_id' => $submission->student_id,
            'type' => self::TYPE_GRADED,
            'title' => 'Entrega corregida',
            'message' => "Tu entrega de \"{$submission->exercise->title}\" ha sido calificada.",
            'data' => ['exercise_id' => $submission->exercise_id, 'submission_id' => $submission->id],
        ]);
    }

    // Avisa a un alumno de que un ejercicio vence pronto
    public static function notifyDueSoon(Exercise $exercise, int $studentId): void
    {
        self::create([
            'user_id' => $studentId,
            'type' => self::TYPE_DUE_SOON,
            'title' => 'Entrega próxima',
            'message' => "\"{$exercise->title}\" vence el {$exercise->due_date->format('d/m/Y H:i')}.",
            'data' => ['exercise_id' => $exercise->id, 'class_id' => $exercise->class_id],
        ]);
    }


}
