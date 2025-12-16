<?php

namespace App\Jobs;

use App\Neuron\Agents\AnalystAgent;
use App\Neuron\Agents\LinkedinAgent;
use App\Neuron\Agents\ResearcherAgent;
use Exception;
use GuzzleHttp\Exception\ClientException;
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
            $research = $this->callAgentWithRetries(
                ResearcherAgent::make(),
                new UserMessage("Research this topic deeply: {$this->topic}")
            )->getContent();

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
            Log::error('RunAgentPipeline failed', [
                'run_id' => $this->runId,
                'exception' => $e,
            ]);

            $this->persist([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

    /**
     * Call an agent's chat method with simple retry/backoff for 429 responses.
     */
    protected function callAgentWithRetries(mixed $agent, UserMessage $message, int $maxAttempts = 3)
    {
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

                if ($status === 429 && $attempt < $maxAttempts) {
                    $wait = (int) pow(2, $attempt);
                    Log::warning("Generative API returned 429; retrying attempt {$attempt}/{$maxAttempts} after {$wait}s");
                    sleep($wait);
                    continue;
                }

                Log::error('Generative API client exception', ['exception' => $e]);
                throw $e;
            } catch (Exception $e) {
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

