<?php

// app/Http/Controllers/MultiAgentController.php
namespace App\Http\Controllers;

use App\Neuron\Agents\ResearcherAgent;
use App\Neuron\Agents\AnalystAgent;
use App\Neuron\Agents\LinkedinAgent;
use Illuminate\Http\Request;
use NeuronAI\Chat\Messages\UserMessage;
use App\Jobs\RunAgentPipeline;
use Illuminate\Support\Str;

class MultiAgentController extends Controller
{

    public function index()
    {
        return view('agents.dashboard');
    }

    public function generate(Request $request)
    {
        $topic = $request->input('topic'); // e.g., "Future of AI in Marketing"

        // Create a run id and dispatch the pipeline job so the frontend can poll for results.
        $runId = (string) Str::uuid();

        RunAgentPipeline::dispatch($topic, $runId);

        return response()->json(['run_id' => $runId], 202);
    }

    /**
     * Return the run state JSON for the frontend poller.
     */
    public function getResult(string $runId)
    {
        $path = storage_path('app/agent-runs/'.$runId.'.json');

        if (! file_exists($path)) {
            return response()->json(['status' => 'pending'], 202);
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true) ?? ['status' => 'failed', 'error' => 'Corrupt run data'];

        return response()->json($data);
    }
}
