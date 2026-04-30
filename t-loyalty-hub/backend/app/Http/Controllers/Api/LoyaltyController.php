<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoyaltyHistoryResource;
use App\Models\User;
use App\Services\AiEngineClient;
use App\Services\GamificationService;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly AiEngineClient $aiClient,
        private readonly GamificationService $gamification,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        return response()->json(['data' => $this->loyaltyService->getSummary($user)]);
    }

    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = User::findOrFail($request->integer('user_id'));
        $history = $this->loyaltyService->getHistory($user, $request->integer('per_page', 20));

        return response()->json(LoyaltyHistoryResource::collection($history)->response()->getData(true));
    }

    public function programs(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        return response()->json(['data' => $this->loyaltyService->getPrograms($user)]);
    }

    public function shadowPortfolio(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $data = $this->aiClient->getShadowPortfolio($userId);

        if (isset($data['real_cashback'], $data['shadow_cashback'])) {
            $this->gamification->updateScoreFromShadow(
                $user->id,
                (float) $data['real_cashback'],
                (float) $data['shadow_cashback']
            );
        }

        return response()->json(['data' => $data]);
    }

    public function dynamicNudging(int $userId): JsonResponse
    {
        User::findOrFail($userId);
        return response()->json(['data' => $this->aiClient->getDynamicNudging($userId)]);
    }

    private function resolveUser(Request $request): User
    {
        $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        return User::findOrFail($request->integer('user_id'));
    }
}
