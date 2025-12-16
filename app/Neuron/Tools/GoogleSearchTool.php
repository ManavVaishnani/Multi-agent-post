<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleSearchTool
{
    public static function make(): Tool
    {
        return Tool::make(
            "google_search",
            "Search Google for a given query to find articles and data."
        )->addProperty(
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'The search topic or question.',
                required: true
            )
        )->setCallable(function (string $query) {
            static $invocationCount = 0;
            static $cache = [];

            $invocationCount++;

            if ($invocationCount > 1 && isset($cache[$query])) {
                return $cache[$query];
            }

            if ($invocationCount > 3) {
                return json_encode([
                    'findings' => [],
                    'error' => 'Too many search attempts in one run. Reuse prior results.',
                ], JSON_PRETTY_PRINT);
            }

            $apiKey = env('SERPER_API_KEY');

            if (empty($apiKey)) {
                return json_encode([
                    'findings' => [],
                    'error' => 'SERPER_API_KEY is missing in environment.',
                ], JSON_PRETTY_PRINT);
            }

            try {
                $response = Http::withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->post('https://google.serper.dev/search', [
                    'q' => $query,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Serper search failed', ['exception' => $e]);

                return json_encode([
                    'findings' => [],
                    'error' => 'Network error while calling search provider.',
                ], JSON_PRETTY_PRINT);
            }

            if ($response->failed()) {
                Log::warning('Serper search non-200 response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return json_encode([
                    'findings' => [],
                    'error' => 'Search provider returned an error.',
                ], JSON_PRETTY_PRINT);
            }

            $payload = $response->json();
            $organic = $payload['organic'] ?? [];

            $findings = collect($organic)
                ->take(8)
                ->map(function (array $item) {
                    $title = $item['title'] ?? 'Untitled';
                    $snippet = $item['snippet'] ?? ($item['description'] ?? '');
                    $link = $item['link'] ?? ($item['url'] ?? '');

                    return [
                        'fact' => $title,
                        'context' => Str::limit($snippet, 240),
                        'source_url' => $link,
                    ];
                })->values()->all();

            return json_encode([
                'findings' => $findings,
                'provider' => 'serper.dev',
            ], JSON_PRETTY_PRINT);
            $cache[$query] = json_encode([
                'findings' => $findings,
                'provider' => 'serper.dev',
            ], JSON_PRETTY_PRINT);
            return $cache[$query];
        });
    }
}
