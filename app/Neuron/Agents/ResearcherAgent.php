<?php

// app/Neuron/Agents/ResearcherAgent.php
namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;
use App\Neuron\Tools\GoogleSearchTool;
use App\Neuron\Tools\FetchUrlTool;

class ResearcherAgent extends Agent
{
    protected function provider(): \NeuronAI\Providers\AIProviderInterface
    {
        return new Gemini(key: env('GEMINI_API_KEY'), model: 'gemini-2.5-flash');
    }

    public function tools(): array
    {
        return [GoogleSearchTool::make(), FetchUrlTool::make()];
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
                "2. Call the 'google_search' tool exactly once to find high-quality articles/sources (return up to 10 top results).",
                "3. For each returned source_url (max 10), call the 'fetch_url' tool to retrieve the page excerpt.",
                "4. Do NOT create a final marketing summary here. Instead extract key statistics, quotes, and specific data points from the fetched excerpts.",
                "5. Keep the original source_url tied to each finding so downstream agents can verify.",
            ],
            output: [
                "Return a JSON object containing a list of 'findings', where each finding has 'fact', 'context', 'source_url', and (optionally) 'excerpt'. Limit to 10 findings."
            ]
        );
    }
}
