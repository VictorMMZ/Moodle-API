<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClassRoom extends Model
{
    use SoftDeletes;

    protected $table = 'classrooms';

    protected $fillable = [
        'teacher_id',
        'name',
        'description',
        'code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Profesor que imparte la clase
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id')->where('role', 'teacher');
    }

    // Alumnos matriculados (tabla pivote class_students)
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_students', 'class_id', 'student_id')
            ->withPivot('status', 'joined_at')
            ->withTimestamps();
    }

    // Registros de matrícula (con status y joined_at)
    public function enrollments(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class, 'class_id');
    }
}
