<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Models\User;
use App\Services\AiEngineClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OffersController extends Controller
{
    public function __construct(private readonly AiEngineClient $aiClient) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ]);

        $user = User::findOrFail($request->integer('user_id'));

        $offers = Offer::query()
            ->whereIn('financial_segment', [$user->financial_segment, 'ALL'])
            ->orderByDesc('cashback_percent')
            ->orderBy('partner_name')
            ->get();

        return response()->json(['data' => OfferResource::collection($offers)]);
    }

    public function crossSellOptimizer(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $data = $this->aiClient->getCrossSellOffers($userId, $user->financial_segment);
        return response()->json(['data' => $data]);
    }

    public function zeroClick(int $userId, Request $request): JsonResponse
    {
        User::findOrFail($userId);
        $query = $request->string('query')->toString();
        return response()->json(['data' => $this->aiClient->getZeroClick($userId, $query !== '' ? $query : null)]);
    }
}
