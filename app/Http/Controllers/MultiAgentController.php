<?php

// app/Http/Controllers/MultiAgentController.php
namespace App\Http\Controllers;

use App\Neuron\Agents\ResearcherAgent;
use App\Neuron\Agents\AnalystAgent;
use App\Neuron\Agents\LinkedinAgent;
use Illuminate\Http\Request;
use NeuronAI\Chat\Messages\UserMessage;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Exception;

class MultiAgentController extends Controller
{

    public function index()
    {
        return view('agents.dashboard');
    }

    public function generate(Request $request)
    {
        $topic = $request->input('topic'); // e.g., "Future of AI in Marketing"

        // 1. RESEARCHER (with retry on 429)
        $researcher = ResearcherAgent::make();
        $researchResponse = $this->callAgentWithRetries($researcher, new UserMessage("Research this topic deeply: " . $topic));
        $rawResearch = $researchResponse->getContent();

        sleep(5);

        // 2. ANALYST (with retry on 429)
        $analyst = AnalystAgent::make();
        $analysisResponse = $this->callAgentWithRetries($analyst, new UserMessage("Verify and structure this raw research data: \n\n" . $rawResearch));
        $validatedContent = $analysisResponse->getContent();

        sleep(5);
        
        // 3. CREATOR (with retry on 429)
        $creator = LinkedinAgent::make();
        $finalResponse = $this->callAgentWithRetries($creator, new UserMessage("Create a LinkedIn post and slide deck from this analysis: \n\n" . $validatedContent));

        $finalOutput = json_decode($finalResponse->getContent(), true);

        return view('agents.dashboard', [
            'topic' => $topic,
            'research' => $rawResearch,
            'analysis' => $validatedContent,
            'final' => $finalOutput
        ]);
    }

    /**
     * Call an agent's chat method with simple retry/backoff for 429 responses.
     *
     * @param  mixed  $agent  An agent instance with a ->chat(UserMessage $msg) method
     * @param  UserMessage  $message
     * @param  int  $maxAttempts
     * @return mixed
     *
     * @throws Exception
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

                // If rate limited, retry with exponential backoff up to $maxAttempts
                if ($status === 429 && $attempt < $maxAttempts) {
                    $wait = (int) pow(2, $attempt); // 2, 4, 8 ... seconds
                    Log::warning("Generative API returned 429; retrying attempt {$attempt}/{$maxAttempts} after {$wait}s");
                    sleep($wait);
                    continue;
                }

                // Not a 429 or max attempts reached — rethrow so caller can handle
                Log::error('Generative API client exception', ['exception' => $e]);
                throw $e;
            } catch (Exception $e) {
                Log::error('Generative API general exception', ['exception' => $e]);
                throw $e;
            }
        }
    }
}
