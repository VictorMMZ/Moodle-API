<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\Exercise;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $submissions = Submission::query()
            ->with([
                'exercise:id,class_id,title,max_grade',
                'student:id,first_name,last_name,email',
            ]);

        if ($user->isStudent()) {
            $submissions->where('student_id', $user->id);
        } elseif ($user->isTeacher()) {
            $submissions->whereHas('exercise.classRoom', fn ($query) => $query->where('teacher_id', $user->id));
        } elseif (! $user->isAdmin()) {
            return response()->json(['message' => 'No tienes permiso para consultar entregas.'], 403);
        }

        if ($request->filled('exercise_id')) {
            $request->validate(['exercise_id' => ['integer', 'exists:exercises,id']]);
            $submissions->where('exercise_id', $request->integer('exercise_id'));
        }

        return response()->json($submissions->latest('submitted_at')->get());
    }

    public function show(Request $request, Submission $submission): JsonResponse
    {
        if (! $this->canView($request->user(), $submission)) {
            return response()->json(['message' => 'No tienes acceso a esta entrega.'], 403);
        }

        return response()->json($submission->load([
            'exercise:id,class_id,title,max_grade',
            'student:id,first_name,last_name,email',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isStudent()) {
            return response()->json(['message' => 'Solo un alumno puede entregar una tarea.'], 403);
        }

        $data = $request->validate([
            'exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'content' => ['nullable', 'string', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp', 'max:10240', 'required_without:content'],
        ]);

        $exercise = Exercise::with('classRoom')->findOrFail($data['exercise_id']);
        $isEnrolled = $exercise->classRoom->students()
            ->where('users.id', $user->id)
            ->wherePivot('status', 'active')
            ->exists();

        if (! $isEnrolled || $exercise->published_at === null || $exercise->published_at->isFuture()) {
            return response()->json(['message' => 'No tienes acceso a este ejercicio.'], 403);
        }

        $submission = Submission::query()
            ->where('exercise_id', $exercise->id)
            ->where('student_id', $user->id)
            ->first();

        if ($submission?->status === 'graded') {
            return response()->json(['message' => 'Esta entrega ya ha sido calificada y no se puede modificar.'], 422);
        }

        $oldPath = $submission?->file_path;
        $filePath = $oldPath;
        if ($request->hasFile('file')) {
            $uploadedPath = Storage::disk('local')->putFile('submissions', $request->file('file'));
            if ($uploadedPath === false) {
                return response()->json(['message' => 'No se pudo guardar el archivo de la entrega.'], 500);
            }
            $filePath = $uploadedPath;
        }

        $submission ??= new Submission([
            'exercise_id' => $exercise->id,
            'student_id' => $user->id,
        ]);
        $submission->fill([
            'content' => $data['content'] ?? null,
            'file_path' => $filePath,
            'status' => 'submitted',
            'submitted_at' => now(),
            'grade' => null,
            'feedback' => null,
            'graded_at' => null,
        ]);
        $submission->save();

        if (isset($uploadedPath) && $oldPath && $oldPath !== $uploadedPath && ! Storage::disk('local')->delete($oldPath)) {
            return response()->json(['message' => 'La entrega se guardó, pero no se pudo eliminar el archivo anterior.'], 500);
        }

        return response()->json($submission->load('exercise:id,class_id,title,max_grade'), 201);
    }

    public function update(Request $request, Submission $submission): JsonResponse
    {
        $user = $request->user();
        if (! $user->isStudent() || $submission->student_id !== $user->id) {
            return response()->json(['message' => 'No tienes permiso para modificar esta entrega.'], 403);
        }

        if ($submission->status === 'graded') {
            return response()->json(['message' => 'Esta entrega ya ha sido calificada y no se puede modificar.'], 422);
        }

        $data = $request->validate([
            'content' => ['sometimes', 'nullable', 'string'],
            'file' => ['sometimes', 'nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        if (! array_key_exists('content', $data) && ! $request->hasFile('file')) {
            return response()->json(['message' => 'Debes enviar contenido o un archivo para actualizar la entrega.'], 422);
        }

        $oldPath = $submission->file_path;
        if ($request->hasFile('file')) {
            $uploadedPath = Storage::disk('local')->putFile('submissions', $request->file('file'));
            if ($uploadedPath === false) {
                return response()->json(['message' => 'No se pudo guardar el archivo de la entrega.'], 500);
            }
            $submission->file_path = $uploadedPath;
        }

        if (array_key_exists('content', $data)) {
            $submission->content = $data['content'];
        }

        $submission->submitted_at = now();
        $submission->save();

        if (isset($uploadedPath) && $oldPath && ! Storage::disk('local')->delete($oldPath)) {
            return response()->json(['message' => 'La entrega se guardó, pero no se pudo eliminar el archivo anterior.'], 500);
        }

        return response()->json($submission->load('exercise:id,class_id,title,max_grade'));
    }

    public function grade(Request $request, Submission $submission): JsonResponse
    {
        if (! $this->canManage($request->user(), $submission->exercise->classRoom)) {
            return response()->json(['message' => 'No tienes permiso para calificar esta entrega.'], 403);
        }

        $data = $request->validate([
            'grade' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$submission->exercise->max_grade],
            'feedback' => ['sometimes', 'nullable', 'string'],
        ]);

        $submission->fill([
            'grade' => $data['grade'],
            'feedback' => $data['feedback'] ?? null,
            'status' => 'graded',
            'graded_at' => now(),
        ])->save();

        return response()->json($submission->load([
            'exercise:id,class_id,title,max_grade',
            'student:id,first_name,last_name,email',
        ]));
    }

    public function download(Request $request, Submission $submission): BinaryFileResponse|JsonResponse
    {
        if (! $this->canView($request->user(), $submission)) {
            return response()->json(['message' => 'No tienes acceso a esta entrega.'], 403);
        }

        if (! $submission->file_path || ! Storage::disk('local')->exists($submission->file_path)) {
            return response()->json(['message' => 'El archivo de esta entrega no está disponible.'], 404);
        }

        return response()->download(Storage::disk('local')->path($submission->file_path));
    }

    private function canView(User $user, Submission $submission): bool
    {
        return $user->isAdmin()
            || ($user->isStudent() && $submission->student_id === $user->id)
            || ($user->isTeacher() && $submission->exercise->classRoom->teacher_id === $user->id);
    }

    private function canManage(User $user, ClassRoom $classroom): bool
    {
        return $user->isAdmin() || ($user->isTeacher() && $classroom->teacher_id === $user->id);
    }
}
