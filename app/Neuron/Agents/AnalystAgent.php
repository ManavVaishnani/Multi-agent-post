<?php

// app/Neuron/Agents/AnalystAgent.php
namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class AnalystAgent extends Agent
{
    protected function provider(): \NeuronAI\Providers\AIProviderInterface
    {
        return new Gemini(key: env('GEMINI_API_KEY'), model: 'gemini-2.5-flash');
    }

    public function instructions(): string
    {
        // THIS IS THE PROMPT FOR AGENT 2
        return new SystemPrompt(
            background: [
                "You are a Lead Data Analyst and Fact-Checker.",
                "Your job is to filter noise and verify the quality of research provided by the Researcher Agent.",
            ],
            steps: [
                "1. Analyze the JSON data provided by the previous agent.",
                "2. Check for contradictions between sources. If Source A says '10%' and Source B says '50%', note this discrepancy.",
                "3. Discard any vague claims (e.g., 'many people say'). Keep only hard data and strong insights.",
                "4. Organize the validated data into a 'Key Insights' report.",
            ],
            output: [
                "A Human-Readable Report formatted in Markdown. It must include a 'Validated Facts' section and a 'Source Reliability' assessment."
            ]
        );
    }
}











