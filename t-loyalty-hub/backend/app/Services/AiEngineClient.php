<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiEngineClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.ai_engine.url', 'http://ai-engine:8000'), '/');
        $this->timeout = (int) config('services.ai_engine.timeout', 5);
    }

    public function getShadowPortfolio(int $userId): array
    {
        return $this->request('GET', "/ai/shadow-portfolio/{$userId}", [], [
            'real_cashback' => 0,
            'shadow_cashback' => 0,
            'gap' => 0,
            'insight' => 'Аналитика временно недоступна',
            'health_score' => 0,
            'is_stub' => true,
            'is_fallback' => true,
        ]);
    }

    public function getDynamicNudging(int $userId): array
    {
        return $this->request('GET', "/ai/nudging/{$userId}", [], [
            'message' => null,
            'category' => null,
            'boost_multiplier' => 1.0,
            'trigger_time' => null,
            'is_stub' => true,
            'is_fallback' => true,
        ]);
    }

    public function getCrossSellOffers(int $userId, string $segment): array
    {
        $data = $this->request('GET', "/ai/cross-sell/{$userId}", ['segment' => $segment], []);

        return $data ?: [];
    }

    public function getZeroClick(int $userId, ?string $query = null): array
    {
        $params = $query !== null && $query !== '' ? ['query' => $query] : [];

        return $this->request('GET', "/ai/zero-click/{$userId}", $params, [
            'activated_offer' => null,
            'partner_name' => null,
            'probability' => 0,
            'intent' => null,
            'cashback_percent' => null,
            'match_accuracy' => null,
            'query' => $query,
            'is_stub' => true,
            'is_fallback' => true,
        ]);
    }

    public function request(string $method, string $path, array $query = [], array $fallback = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->send($method, "{$this->baseUrl}{$path}", [
                    'query' => $query,
                ]);

            if ($response->successful()) {
                $json = $response->json();
                return is_array($json) ? $json : $fallback;
            }

            Log::warning('AI Engine non-2xx response', [
                'path' => $path,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI Engine unavailable', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return $fallback;
    }
}
