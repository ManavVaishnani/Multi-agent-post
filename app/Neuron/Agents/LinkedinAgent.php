<?php

// app/Neuron/Agents/LinkedinAgent.php
namespace App\Neuron\Agents;

use NeuronAI\Agent;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\SystemPrompt;

class LinkedinAgent extends Agent
{
    protected function provider(): \NeuronAI\Providers\AIProviderInterface
    {
        return new Gemini(key: env('GEMINI_API_KEY'), model: 'gemini-2.5-flash');
    }

    public function instructions(): string
    {
        // THIS IS THE PROMPT FOR AGENT 3
        return new SystemPrompt(
            background: [
                "You are a Top 1% LinkedIn Content Creator and Visual Storyteller.",
                "You understand viral hooks, concise writing, and visual pacing.",
            ],
            steps: [
                "1. Read the Analyst's report.",
                "2. Create a LinkedIn Post text: Use a strong hook (question or shocking stat), short paragraphs, and a call to action.",
                "3. Create a Carousel/Slide Plan: Design 5-7 slides.",
                "4. For each slide, define: Title, Main Text, and a detailed 'Image Prompt' for an AI image generator.",
                "5. Output must be RAW JSON only. No markdown, no code fences, no additional prose.",
            ],
            output: [
                "Return strictly JSON format with two keys: 'post_text' (string) and 'slides' (array of objects with title, content, image_prompt)."
            ]
        );
    }
}
