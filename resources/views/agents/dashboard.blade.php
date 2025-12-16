<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neuron AI Agent Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-800">

<div class="max-w-7xl mx-auto p-6">

    <div class="mb-10 text-center">
        <h1 class="text-3xl font-bold text-indigo-600">🤖 Neuron Multi-Agent System</h1>
        <p class="text-gray-500 mt-2">Research → Analysis → Content Creation Pipeline</p>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-10 max-w-2xl mx-auto">
        <form id="agent-form" class="flex gap-4">
            @csrf
            <input type="text" id="topic-input" name="topic" placeholder="Enter a topic (e.g., 'Future of AI in Marketing')"
                   class="flex-1 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500" required>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition">
                Start Agents
            </button>
        </form>
    </div>

    <div id="status-container" class="hidden max-w-2xl mx-auto mb-10">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <div class="flex items-center gap-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                <div>
                    <p class="font-semibold text-gray-800">Processing...</p>
                    <p class="text-sm text-gray-500" id="status-text">Initializing agents</p>
                    <p class="text-xs text-gray-400 mt-1" id="run-id-display"></p>
                </div>
            </div>
        </div>
    </div>

    <div id="results-container" class="hidden">
        <div class="relative border-l-4 border-indigo-200 ml-6 md:ml-12 space-y-12">
            <div id="researcher-section" class="hidden relative pl-8 md:pl-12">
                <div class="absolute -left-[22px] bg-blue-500 h-10 w-10 rounded-full flex items-center justify-center text-white shadow-lg">
                    🔍
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-blue-50 px-6 py-3 border-b border-blue-100 flex justify-between items-center">
                        <h2 class="font-bold text-blue-800">Agent 1: The Researcher</h2>
                        <span class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded" id="researcher-status">Status: Processing</span>
                    </div>
                    <div class="p-6 max-h-64 overflow-y-auto bg-gray-50 font-mono text-xs text-gray-600">
                        <pre id="research-content"></pre>
                    </div>
                </div>
            </div>

            <div id="analyst-section" class="hidden relative pl-8 md:pl-12">
                <div class="absolute -left-[22px] bg-purple-500 h-10 w-10 rounded-full flex items-center justify-center text-white shadow-lg">
                    🧠
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-purple-50 px-6 py-3 border-b border-purple-100 flex justify-between items-center">
                        <h2 class="font-bold text-purple-800">Agent 2: The Analyst (Validator)</h2>
                        <span class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded" id="analyst-status">Status: Waiting</span>
                    </div>
                    <div class="p-6 prose prose-sm max-w-none text-gray-700">
                        <div id="analysis-content"></div>
                    </div>
                </div>
            </div>

            <div id="linkedin-section" class="hidden relative pl-8 md:pl-12">
                <div class="absolute -left-[22px] bg-green-500 h-10 w-10 rounded-full flex items-center justify-center text-white shadow-lg">
                    ✨
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-green-50 px-6 py-3 border-b border-green-100 flex justify-between items-center">
                        <h2 class="font-bold text-green-800">Agent 3: LinkedIn Output</h2>
                        <span class="text-xs bg-green-200 text-green-800 px-2 py-1 rounded" id="linkedin-status">Status: Waiting</span>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h3 class="font-bold text-gray-400 uppercase text-xs tracking-wider mb-3">Post Caption</h3>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 text-sm whitespace-pre-wrap" id="post-text">
                                Processing...
                            </div>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-400 uppercase text-xs tracking-wider mb-3">LinkedIn Carousel Plan</h3>
                            <div class="space-y-3" id="slides-container">
                                <p class="text-gray-500 text-sm">Processing...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="error-container" class="hidden max-w-2xl mx-auto">
        <div class="bg-red-50 border border-red-200 rounded-xl p-6">
            <p class="font-semibold text-red-800">Error</p>
            <p class="text-sm text-red-600 mt-2" id="error-message"></p>
        </div>
    </div>

</div>

<script>
    let pollInterval = null;
    let currentRunId = null;

    document.getElementById('agent-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const topic = document.getElementById('topic-input').value;
        const formData = new FormData(e.target);

        try {
            const response = await fetch('{{ route("agent.run") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const data = await response.json();

            if (response.ok) {
                currentRunId = data.run_id;
                document.getElementById('run-id-display').textContent = `Run ID: ${currentRunId}`;
                document.getElementById('status-container').classList.remove('hidden');
                document.getElementById('results-container').classList.add('hidden');
                document.getElementById('error-container').classList.add('hidden');

                startPolling(currentRunId);
            } else {
                showError(data.error || 'Failed to start agent pipeline');
            }
        } catch (error) {
            showError('Network error: ' + error.message);
        }
    });

    function startPolling(runId) {
        if (pollInterval) {
            clearInterval(pollInterval);
        }

        const resultBaseUrl = '{{ url('/agent-dashboard/result') }}/';

        pollInterval = setInterval(async () => {
            try {
                const response = await fetch(resultBaseUrl + runId);
                const data = response.status === 202 || response.status === 404 ? { status: 'pending' } : await response.json();

                if (response.ok || response.status === 202 || response.status === 404) {
                    updateUI(data);

                    if (data.status === 'completed' || data.status === 'failed') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                    }
                } else {
                    clearInterval(pollInterval);
                    showError('Failed to fetch run status');
                }
            } catch (error) {
                clearInterval(pollInterval);
                showError('Error polling status: ' + error.message);
            }
        }, 2000);
    }

    function updateUI(data) {
        document.getElementById('status-text').textContent = getStatusText(data.status);

        if (data.status === 'running' || data.status === 'research_completed') {
            if (data.research) {
                document.getElementById('researcher-section').classList.remove('hidden');
                document.getElementById('research-content').textContent = data.research;
                document.getElementById('researcher-status').textContent = 'Status: Completed';
                document.getElementById('results-container').classList.remove('hidden');
            }
        }

        if (data.status === 'analysis_completed' || data.status === 'completed') {
            if (data.analysis) {
                document.getElementById('analyst-section').classList.remove('hidden');
                document.getElementById('analysis-content').innerHTML = data.analysis.replace(/\n/g, '<br>');
                document.getElementById('analyst-status').textContent = 'Status: Validated';
            }
        }

        if (data.status === 'completed' && data.final) {
            document.getElementById('linkedin-section').classList.remove('hidden');
            document.getElementById('linkedin-status').textContent = 'Status: Ready to Post';

            if (data.final.post_text) {
                document.getElementById('post-text').textContent = data.final.post_text;
            }

            if (data.final.slides && Array.isArray(data.final.slides)) {
                const slidesContainer = document.getElementById('slides-container');
                slidesContainer.innerHTML = data.final.slides.map((slide, index) => `
                    <div class="flex items-start gap-3 bg-gray-50 p-3 rounded-lg border border-gray-200 hover:shadow-md transition">
                        <div class="bg-gray-200 w-16 h-16 flex-shrink-0 rounded flex items-center justify-center text-gray-400 text-xs font-bold">
                            Slide ${index + 1}
                        </div>
                        <div>
                            <p class="font-bold text-sm text-gray-800">${escapeHtml(slide.title || 'Untitled')}</p>
                            <p class="text-xs text-gray-500 mt-1">${escapeHtml(slide.content || '')}</p>
                            <p class="text-[10px] text-blue-500 mt-1 italic">🎨 Prompt: ${escapeHtml((slide.image_prompt || '').substring(0, 50))}</p>
                        </div>
                    </div>
                `).join('');
            } else if (data.final_raw) {
                document.getElementById('slides-container').innerHTML = `
                    <p class="text-sm text-gray-600">Raw response:</p>
                    <pre class="text-xs bg-gray-50 border border-gray-200 p-3 rounded-lg overflow-x-auto">${escapeHtml(data.final_raw)}</pre>
                `;
            }

            document.getElementById('status-container').classList.add('hidden');
        }

        if (data.status === 'failed') {
            document.getElementById('status-container').classList.add('hidden');
            showError(data.error || 'Pipeline failed');
        }
    }

    function getStatusText(status) {
        const statusMap = {
            'pending': 'Initializing agents...',
            'running': 'Running research agent...',
            'research_completed': 'Research completed. Analyzing data...',
            'analysis_completed': 'Analysis completed. Creating LinkedIn content...',
            'completed': 'Pipeline completed successfully!',
            'failed': 'Pipeline failed',
        };

        return statusMap[status] || 'Processing...';
    }

    function showError(message) {
        document.getElementById('error-message').textContent = message;
        document.getElementById('error-container').classList.remove('hidden');
        document.getElementById('status-container').classList.add('hidden');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    @if(isset($runId) && isset($runData))
        currentRunId = '{{ $runId }}';
        document.getElementById('run-id-display').textContent = `Run ID: ${currentRunId}`;
        updateUI(@json($runData));
        @if($runData['status'] !== 'completed' && $runData['status'] !== 'failed')
            startPolling(currentRunId);
        @endif
    @endif
</script>

</body>
</html>
