<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\PropertyType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchUrlTool
{
    public static function make(): Tool
    {
        return Tool::make(
            'fetch_url',
            'Fetch the body text of a given URL. Returns title and a short excerpt.'
        )->addProperty(
            new ToolProperty(
                name: 'url',
                type: PropertyType::STRING,
                description: 'The URL to fetch.',
                required: true
            )
        )->setMaxTries(12)
        ->setCallable(function (string $url) {
            try {
                $resp = Http::timeout(8)->get($url);
            } catch (\Throwable $e) {
                Log::warning('FetchUrlTool network error', ['url' => $url, 'exception' => $e]);
                return json_encode(['url' => $url, 'error' => 'Network error fetching URL']);
            }

            if ($resp->failed()) {
                Log::warning('FetchUrlTool non-200', ['url' => $url, 'status' => $resp->status()]);
                return json_encode(['url' => $url, 'error' => 'Non-200 response: '.$resp->status()]);
            }

            $body = strip_tags($resp->body());
            $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', $body)), 0, 1200);

            // Try to extract <title>
            preg_match('/<title>(.*?)<\/title>/is', $resp->body(), $m);
            $title = $m[1] ?? null;

            return json_encode([
                'url' => $url,
                'title' => $title,
                'excerpt' => $excerpt,
            ], JSON_PRETTY_PRINT);
        });
    }

    /**
     * Direct helper to fetch a URL (used by fallback pipeline).
     */
    public static function fetchUrl(string $url): string
    {
        try {
            $resp = Http::timeout(8)->get($url);
        } catch (\Throwable $e) {
            Log::warning('FetchUrlTool network error', ['url' => $url, 'exception' => $e]);
            return json_encode(['url' => $url, 'error' => 'Network error fetching URL']);
        }

        if ($resp->failed()) {
            Log::warning('FetchUrlTool non-200', ['url' => $url, 'status' => $resp->status()]);
            return json_encode(['url' => $url, 'error' => 'Non-200 response: '.$resp->status()]);
        }

        $body = strip_tags($resp->body());
        $excerpt = mb_substr(trim(preg_replace('/\s+/', ' ', $body)), 0, 1200);

        preg_match('/<title>(.*?)<\/title>/is', $resp->body(), $m);
        $title = $m[1] ?? null;

        return json_encode([
            'url' => $url,
            'title' => $title,
            'excerpt' => $excerpt,
        ], JSON_PRETTY_PRINT);
    }
}
