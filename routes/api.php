
<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassRoomController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [UserController::class, 'store'])->middleware('throttle:5,1');

// Rutas protegidas: requieren autenticación
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/classindex', [ClassRoomController::class, 'index']);
    Route::post('/classstore', [ClassRoomController::class, 'store']);
    Route::get('/classshow/{classroom}', [ClassRoomController::class, 'show']);
    Route::put('/classupdate/{classroom}', [ClassRoomController::class, 'update']);
    Route::delete('/classdestroy/{classroom}', [ClassRoomController::class, 'destroy']);
    Route::get('/participants/{classroom}', [ClassRoomController::class, 'participants']);
    Route::post('/participants/{classroom}', [ClassRoomController::class, 'enrollStudent']);
    Route::delete('/participants/{classroom}/{studentId}', [ClassRoomController::class, 'unenrollStudent']);

    Route::get('/exerciseindex', [ExerciseController::class, 'index']);
    Route::post('/exercisestore', [ExerciseController::class, 'store']);
    Route::get('/exerciseshow/{exercise}', [ExerciseController::class, 'show']);
    Route::put('/exerciseupdate/{exercise}', [ExerciseController::class, 'update']);
    Route::delete('/exercisedestroy/{exercise}', [ExerciseController::class, 'destroy']);

    Route::get('/submissionindex', [SubmissionController::class, 'index']);
    Route::post('/submissionstore', [SubmissionController::class, 'store']);
    Route::get('/submissionshow/{submission}', [SubmissionController::class, 'show']);
    Route::put('/submissionupdate/{submission}', [SubmissionController::class, 'update']);
    Route::put('/submissiongrade/{submission}', [SubmissionController::class, 'grade']);
    Route::get('/submissionfile/{submission}', [SubmissionController::class, 'download']);

    Route::get('/messageindex', [MessageController::class, 'index']);
    Route::post('/messagestore', [MessageController::class, 'store']);
    Route::get('/messageshow/{message}', [MessageController::class, 'show']);
    Route::put('/messageupdate/{message}', [MessageController::class, 'update']);
    Route::delete('/messagedestroy/{message}', [MessageController::class, 'destroy']);
});
