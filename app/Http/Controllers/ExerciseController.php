<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\Exercise;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $exercises = Exercise::query()->with('classRoom:id,name');

        if ($user->isTeacher()) {
            $exercises->whereHas('classRoom', fn ($query) => $query->where('teacher_id', $user->id));
        } elseif ($user->isStudent()) {
            $exercises
                ->whereHas('classRoom.students', function ($query) use ($user) {
                    $query->where('users.id', $user->id)
                        ->where('class_students.status', 'active');
                })
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        } elseif (!$user->isAdmin()) {
            return response()->json(['message' => 'No tienes permiso para consultar ejercicios.'], 403);
        }

        return response()->json($exercises->get());
    }

    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        $user = $request->user();

        if (!$this->canView($user, $exercise)) {
            return response()->json(['message' => 'No tienes acceso a este ejercicio.'], 403);
        }

        return response()->json($exercise->load('classRoom:id,name'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classrooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'max_grade' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        $classroom = ClassRoom::findOrFail($data['class_id']);
        if (!$this->canManage($request->user(), $classroom)) {
            return response()->json(['message' => 'No tienes permiso para crear ejercicios en esta clase.'], 403);
        }

        if ($request->hasFile('file')) {
            $path = Storage::disk('local')->putFile('exercises', $request->file('file'));
            if ($path === false) {
                return response()->json(['message' => 'No se pudo guardar el archivo del ejercicio.'], 500);
            }
            $data['file_path'] = $path;
        }
        unset($data['file']);
        $exercise = Exercise::create($data);

        if ($exercise->published_at?->isPast()) {
            Notification::notifyNewExercise($exercise);
        }

        return response()->json($exercise->load('classRoom:id,name'), 201);
    }

    public function update(Request $request, Exercise $exercise): JsonResponse
    {
        if (!$this->canManage($request->user(), $exercise->classRoom)) {
            return response()->json(['message' => 'No tienes permiso para editar este ejercicio.'], 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'type' => ['sometimes', 'required', 'string', 'max:255'],
            'max_grade' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        $wasPublished = $exercise->published_at?->isPast() ?? false;
        $oldPath = $exercise->file_path;
        if ($request->hasFile('file')) {
            $uploadedPath = Storage::disk('local')->putFile('exercises', $request->file('file'));
            if ($uploadedPath === false) {
                return response()->json(['message' => 'No se pudo guardar el archivo del ejercicio.'], 500);
            }
            $data['file_path'] = $uploadedPath;
        }
        unset($data['file']);
        $exercise->update($data);

        if (! $wasPublished && $exercise->published_at?->isPast()) {
            Notification::notifyNewExercise($exercise);
        }

        if (isset($uploadedPath) && $oldPath && ! Storage::disk('local')->delete($oldPath)) {
            return response()->json(['message' => 'El ejercicio se guardó, pero no se pudo eliminar el archivo anterior.'], 500);
        }

        return response()->json($exercise->load('classRoom:id,name'));
    }

    public function file(Request $request, Exercise $exercise): BinaryFileResponse|JsonResponse
    {
        if (! $this->canView($request->user(), $exercise)) {
            return response()->json(['message' => 'No tienes acceso a este ejercicio.'], 403);
        }

        if (! $exercise->file_path || ! Storage::disk('local')->exists($exercise->file_path)) {
            return response()->json(['message' => 'El archivo de este ejercicio no está disponible.'], 404);
        }

        $path = Storage::disk('local')->path($exercise->file_path);
        $headers = ['X-Content-Type-Options' => 'nosniff'];

        return $request->boolean('download')
            ? response()->download($path, null, $headers)
            : response()->file($path, $headers);
    }

    public function destroy(Request $request, Exercise $exercise): JsonResponse
    {
        if (!$this->canManage($request->user(), $exercise->classRoom)) {
            return response()->json(['message' => 'No tienes permiso para eliminar este ejercicio.'], 403);
        }

        $exercise->delete();

        return response()->json(['message' => 'Ejercicio eliminado.']);
    }

    private function canManage(User $user, ClassRoom $classroom): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $classroom->teacher_id === $user->id);
    }

    private function canView(User $user, Exercise $exercise): bool
    {
        if ($this->canManage($user, $exercise->classRoom)) {
            return true;
        }

        return $user->isStudent()
            && $exercise->published_at !== null
            && $exercise->published_at->isPast()
            && $exercise->classRoom->students()
                ->where('users.id', $user->id)
                ->wherePivot('status', 'active')
                ->exists();
    }


    
}