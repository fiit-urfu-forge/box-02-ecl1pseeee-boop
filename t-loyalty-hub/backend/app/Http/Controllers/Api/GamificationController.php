<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Http\JsonResponse;

class GamificationController extends Controller
{
    public function __construct(private readonly GamificationService $service) {}

    public function show(int $userId): JsonResponse
    {
        User::findOrFail($userId);
        return response()->json(['data' => $this->service->getForUser($userId)]);
    }

    public function recordVisit(int $userId): JsonResponse
    {
        User::findOrFail($userId);
        return response()->json(['data' => $this->service->recordVisit($userId)]);
    }
}
