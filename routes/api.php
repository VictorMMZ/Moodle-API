
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassRoomController;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

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

});
