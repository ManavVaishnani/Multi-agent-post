<?php

namespace App\Jobs;

use App\Neuron\Agents\AnalystAgent;
use App\Neuron\Agents\LinkedinAgent;
use App\Neuron\Agents\ResearcherAgent;
use Exception;
use GuzzleHttp\Exception\ClientException;
use NeuronAI\Exceptions\ToolMaxTriesException;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\UserMessage;

class RunAgentPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $topic,
        public string $runId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->persist(['status' => 'running', 'topic' => $this->topic]);

        try {
            // Try running the Researcher agent. If the provider returns 429 repeatedly,
            // fall back to a direct search+fetch approach so the pipeline can continue.
            try {
                $research = $this->callAgentWithRetries(
                    ResearcherAgent::make(),
                    new UserMessage("Research this topic deeply: {$this->topic}")
                )->getContent();
            } catch (ClientException $e) {
                $status = null;
                if ($e->getResponse() !== null) {
                    $status = (int) $e->getResponse()->getStatusCode();
                }

                if ($status === 429) {
                    Log::warning('Researcher agent provider returned 429; using direct search fallback', ['run_id' => $this->runId]);

                    // Use direct HTTP search and fetch the top links (up to 10)
                    $raw = [];
                    try {
                        $searchJson = \App\Neuron\Tools\GoogleSearchTool::fetchResults($this->topic);
                        $searchData = json_decode($searchJson, true) ?? ['findings' => []];

                        $candidates = $searchData['findings'] ?? [];

                        foreach (array_slice($candidates, 0, 10) as $item) {
                            $url = $item['source_url'] ?? null;
                            if (! $url) continue;

                            $fetched = \App\Neuron\Tools\FetchUrlTool::fetchUrl($url);
                            $fetchedData = json_decode($fetched, true) ?? ['url' => $url];

                            $raw[] = [
                                'fact' => $item['fact'] ?? null,
                                'context' => $item['context'] ?? null,
                                'source_url' => $url,
                                'excerpt' => $fetchedData['excerpt'] ?? null,
                                'title' => $fetchedData['title'] ?? null,
                            ];
                        }

                        $research = json_encode(['findings' => $raw], JSON_PRETTY_PRINT);
                    } catch (\Throwable $inner) {
                        Log::error('Fallback search failed', ['exception' => $inner]);
                        throw $e; // rethrow original to let outer handler persist failure
                    }
                } else {
                    throw $e;
                }
            }

            $this->persist(['status' => 'research_completed', 'research' => $research]);

            $analysis = $this->callAgentWithRetries(
                AnalystAgent::make(),
                new UserMessage("Verify and structure this raw research data: \n\n{$research}")
            )->getContent();

            $this->persist(['status' => 'analysis_completed', 'analysis' => $analysis]);

            $finalRaw = $this->callAgentWithRetries(
                LinkedinAgent::make(),
                new UserMessage("Create a LinkedIn post and slide deck from this analysis: \n\n{$analysis}")
            )->getContent();

            $sanitized = $this->stripCodeFences($finalRaw);
            $decodedFinal = json_decode($sanitized, true);

            if (! is_array($decodedFinal)) {
                $decodedFinal = [
                    'post_text' => $finalRaw,
                    'slides' => [],
                    'warning' => 'LinkedIn agent did not return valid JSON; raw content captured.',
                ];
            }

            $this->persist([
                'status' => 'completed',
                'final' => $decodedFinal,
                'final_raw' => $finalRaw,
            ]);
        } catch (Exception $e) {
            // Provide clearer error messages for quota/tool-related failures
            $friendly = $e->getMessage();

            if ($e instanceof ClientException && $e->getResponse() !== null && (int) $e->getResponse()->getStatusCode() === 429) {
                $friendly = 'Quota exceeded: Generative API returned 429 Too Many Requests. Check your Google Cloud billing and API quotas.';
            } elseif ($e instanceof ToolMaxTriesException) {
                $friendly = 'Tool retries exhausted: ' . $e->getMessage();
            }

            Log::error('RunAgentPipeline failed', [
                'run_id' => $this->runId,
                'exception' => $e,
                'friendly' => $friendly,
            ]);

            $this->persist([
                'status' => 'failed',
                'error' => $friendly,
            ]);

            $this->fail($e);
        }
    }

    /**
     * Call an agent's chat method with simple retry/backoff for 429 responses.
     */
    protected function callAgentWithRetries(mixed $agent, UserMessage $message, int $maxAttempts = null)
    {
        $maxAttempts = $maxAttempts ?? (int) env('AGENT_MAX_ATTEMPTS', 6);

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $agent->chat($message);
            } catch (ClientException $e) {
                $status = null;

                if ($e->getResponse() !== null) {
                    $status = (int) $e->getResponse()->getStatusCode();
                }

                // Exponential backoff with small jitter for 429 responses
                if ($status === 429 && $attempt < $maxAttempts) {
                    $base = (int) pow(2, $attempt);
                    $jitter = random_int(0, 3);
                    $wait = min(60, $base + $jitter);
                    Log::warning("Generative API returned 429; retrying attempt {$attempt}/{$maxAttempts} after {$wait}s");
                    sleep($wait);
                    continue;
                }

                if ($status === 429) {
                    Log::error('Generative API quota likely exceeded (429)', ['attempt' => $attempt, 'maxAttempts' => $maxAttempts, 'exception' => $e]);
                } else {
                    Log::error('Generative API client exception', ['exception' => $e]);
                }

                throw $e;
            } catch (ToolMaxTriesException $e) {
                Log::error('Tool max tries exceeded', ['exception' => $e]);
                throw $e;
            } catch (Throwable $e) {
                Log::error('Generative API general exception', ['exception' => $e]);
                throw $e;
            }
        }
    }

    /**
     * Persist run state to storage for UI consumption.
     */
    protected function persist(array $data): void
    {
        $existing = $this->readRunData();

        $merged = array_merge(
            [
                'run_id' => $this->runId,
                'topic' => $this->topic,
                'status' => 'pending',
                'timestamps' => [],
            ],
            $existing,
            $data
        );

        $merged['timestamps'][$merged['status']] = now()->toIso8601String();

        $path = $this->storagePath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT));
    }

    protected function readRunData(): array
    {
        $path = $this->storagePath();

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        return json_decode($content, true) ?? [];
    }

    protected function stripCodeFences(string $content): string
    {
        $trimmed = trim($content);

        // Remove ```json ... ``` or ``` ... ```
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z0-9]*\\s*/', '', $trimmed);
            $trimmed = preg_replace('/```$/', '', $trimmed);
        }

        return trim($trimmed);
    }

    protected function storagePath(): string
    {
        return storage_path('app/agent-runs/'.$this->runId.'.json');
    }
}

