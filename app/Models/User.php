<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'id_number',
        'avatar',
        'is_active',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // Clases que imparte (profesor)
    public function classrooms(): HasMany
    {
        return $this->hasMany(ClassRoom::class, 'teacher_id');
    }

    // Clases en las que está matriculado (alumno)
    public function enrolledClassrooms(): BelongsToMany
    {
        return $this->belongsToMany(ClassRoom::class, 'class_students', 'student_id', 'classroom_id')
            ->withTimestamps();
    }

    // Entregas realizadas (alumno)
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    // No se llama notifications() para no pisar el de Notifiable
    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
