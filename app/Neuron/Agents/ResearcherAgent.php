<?php

// app/Neuron/Agents/ResearcherAgent.php
namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;
use App\Neuron\Tools\GoogleSearchTool;

class ResearcherAgent extends Agent
{
    protected function provider(): \NeuronAI\Providers\AIProviderInterface
    {
        return new Gemini(key: env('GEMINI_API_KEY'), model: 'gemini-2.5-flash');
    }

    public function tools(): array
    {
        return [GoogleSearchTool::make()];
    }

    public function instructions(): string
    {
        // THIS IS THE PROMPT FOR AGENT 1
        return new SystemPrompt(
            background: [
                "You are a Senior Web Researcher with 10 years of experience in data mining.",
                "Your goal is to gather exhaustive, factual information on a given topic.",
            ],
            steps: [
                "1. Receive the user's topic.",
                "2. Call the 'google_search' tool exactly once to find high-quality articles/sources.",
                "3. Do NOT summarize yet. Extract key statistics, quotes, and specific data points.",
                "4. You must keep the URLs associated with every piece of data.",
            ],
            output: [
                "Return a JSON object containing a list of 'findings', where each finding has 'fact', 'context', and 'source_url'."
            ]
        );
    }
}
