<?php

namespace App\Http\Controllers;

use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Gestiona las clases (tabla classrooms).
 * Un profesor por clase (classrooms.teacher_id) y alumnos en class_students.
 * Roles de users.role: student, teacher, admin.
 */
class ClassRoomController extends Controller
{
	// Lista las clases según el rol de quien inició sesión.
	public function index(Request $request): JsonResponse
	{
		$user = $request->user();

		if ($user->isAdmin()) {
			$classrooms = ClassRoom::all();
		} elseif ($user->isTeacher()) {
			$classrooms = $user->classrooms;
		} else {
			$classrooms = $user->enrolledClassrooms()
				->wherePivot('status', 'active')
				->get();
		}

		return response()->json($classrooms);
	}

	public function show(Request $request, ClassRoom $classroom): JsonResponse
	{
		if (!$this->canView($request->user(), $classroom)) {
			return response()->json(['message' => 'No tienes acceso a esta clase.'], 403);
		}

		return response()->json($classroom->load('teacher:id,first_name,last_name,email'));
	}

	// El profesor que la crea queda como teacher_id.
	public function store(Request $request): JsonResponse
	{
		$user = $request->user();
		if (!$user->isTeacher()) {
			return response()->json(['message' => 'Solo un profesor puede crear clases.'], 403);
		}

		$data = $request->validate([
			'name' => ['required', 'string', 'max:255'],
			'description' => ['nullable', 'string'],
		]);

		$classroom = ClassRoom::create($data + [
			'teacher_id' => $user->id,
			'code' => strtoupper(Str::random(8)),
		]);

		return response()->json($classroom, 201);
	}

	public function update(Request $request, ClassRoom $classroom): JsonResponse
	{
		if (!$this->canManage($request->user(), $classroom)) {
			return response()->json(['message' => 'No tienes permiso para editar esta clase.'], 403);
		}

		$data = $request->validate([
			'name' => ['sometimes', 'required', 'string', 'max:255'],
			'description' => ['sometimes', 'nullable', 'string'],
			'is_active' => ['sometimes', 'boolean'],
		]);

		$classroom->update($data);

		return response()->json($classroom);
	}

	// Soft delete: la migración usa softDeletes y las FK son restrictOnDelete.
	public function destroy(Request $request, ClassRoom $classroom): JsonResponse
	{
		if (!$this->canManage($request->user(), $classroom)) {
			return response()->json(['message' => 'No tienes permiso para eliminar esta clase.'], 403);
		}

		$classroom->delete();

		return response()->json(['message' => 'Clase eliminada.']);
	}

	// Matricula (o reactiva la matrícula de) un usuario con rol student.
	public function enrollStudent(Request $request, ClassRoom $classroom): JsonResponse
	{
		if (!$this->canManage($request->user(), $classroom)) {
			return response()->json(['message' => 'No tienes permiso para matricular alumnos.'], 403);
		}

		$data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

		$student = User::findOrFail($data['user_id']);
		if (!$student->isStudent()) {
			return response()->json(['message' => 'El usuario indicado no es un alumno.'], 422);
		}

		$classroom->enrollments()->updateOrCreate(
			['student_id' => $student->id],
			['status' => 'active', 'joined_at' => now()]
		);

		return response()->json(['message' => 'Alumno matriculado en la clase.']);
	}

	// No borra la fila: marca la matrícula como inactive para conservar el historial.
	public function unenrollStudent(Request $request, ClassRoom $classroom, int $studentId): JsonResponse
	{
		if (!$this->canManage($request->user(), $classroom)) {
			return response()->json(['message' => 'No tienes permiso para gestionar esta clase.'], 403);
		}

		$updated = $classroom->enrollments()
			->where('student_id', $studentId)
			->where('status', 'active')
			->update(['status' => 'inactive']);

		return $updated
			? response()->json(['message' => 'Matrícula cancelada.'])
			: response()->json(['message' => 'El alumno no está matriculado en esta clase.'], 404);
	}

	// Devuelve el profesor y los alumnos activos de la clase.
	public function participants(Request $request, ClassRoom $classroom): JsonResponse
	{
		if (!$this->canView($request->user(), $classroom)) {
			return response()->json(['message' => 'No tienes acceso a esta clase.'], 403);
		}

		$columns = ['users.id', 'users.first_name', 'users.last_name', 'users.email'];

		return response()->json([
			'teacher' => $classroom->teacher()->select($columns)->first(),
			'students' => $classroom->students()
				->wherePivot('status', 'active')
				->select($columns)
				->get(),
		]);
	}

	private function canManage(User $user, ClassRoom $classroom): bool
	{
		return $user->isAdmin() || ($user->isTeacher() && $classroom->teacher_id === $user->id);
	}

	private function canView(User $user, ClassRoom $classroom): bool
	{
		return $this->canManage($user, $classroom)
			|| $classroom->students()
				->where('users.id', $user->id)
				->wherePivot('status', 'active')
				->exists();
	}
}
