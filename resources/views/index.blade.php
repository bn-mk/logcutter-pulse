<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="icon" type="image/png" href="/favicons/favicon.png" />
    <script defer src="/js/logpulse-live.js"></script>
    <title>LogPulse</title>
    <style>
        :root {
            --bg-0: #070b14;
            --bg-1: #0f172a;
            --bg-2: #111c33;
            --panel: rgba(15, 23, 42, 0.82);
            --panel-border: rgba(148, 163, 184, 0.25);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #22d3ee;
            --accent-2: #38bdf8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 800px at 100% -10%, rgba(34, 211, 238, 0.14), transparent 60%),
                radial-gradient(900px 700px at -10% 0%, rgba(56, 189, 248, 0.10), transparent 55%),
                linear-gradient(165deg, var(--bg-0), var(--bg-1) 45%, var(--bg-2));
            min-height: 100vh;
        }

        .container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 24px 20px 28px;
        }

        .page-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 14px;
        }

        h1 {
            margin: 0;
            font-size: 30px;
            letter-spacing: 0.02em;
        }

        .subtitle {
            margin-top: 4px;
            color: var(--muted);
            font-size: 14px;
        }

        .count-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            color: #d1fae5;
            border: 1px solid rgba(52, 211, 153, 0.35);
            background: rgba(6, 78, 59, 0.4);
            font-size: 13px;
            white-space: nowrap;
        }

        .toolbar {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .field input,
        .field select,
        .field button {
            height: 40px;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: rgba(15, 23, 42, 0.8);
            padding: 0 12px;
            font: inherit;
            color: var(--text);
        }

        .field button {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            color: #032533;
            border: none;
            font-weight: 700;
            cursor: pointer;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: 14px;
            margin-bottom: 16px;
            padding: 16px;
            backdrop-filter: blur(8px);
        }

        .workspace {
            display: grid;
            grid-template-columns: minmax(320px, 440px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
        }

        .issue-list {
            max-height: calc(100vh - 250px);
            overflow: auto;
            padding-right: 4px;
        }

        .issue-link {
            display: block;
            text-decoration: none;
            color: inherit;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-left: 4px solid transparent;
            background: rgba(15, 23, 42, 0.72);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .issue-link.active {
            border-color: rgba(56, 189, 248, 0.5);
            border-left-color: var(--accent);
            box-shadow: 0 0 0 1px rgba(34, 211, 238, 0.2) inset;
            background: rgba(30, 41, 59, 0.9);
        }

        .issue-title {
            margin: 8px 0 6px;
            font-size: 16px;
            line-height: 1.3;
        }

        .issue-message {
            color: #cbd5e1;
            font-size: 13px;
            display: -webkit-box;
            line-clamp: 2;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .issue-meta {
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .detail-panel {
            min-height: calc(100vh - 250px);
        }

        .detail-title {
            margin: 8px 0 12px;
            font-size: 24px;
        }

        .detail-grid {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-top: 10px;
        }

        .kv {
            background: rgba(2, 6, 23, 0.45);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 10px;
            padding: 10px;
        }

        .kv-blame {
            background: rgba(127, 29, 29, 0.24);
            border-color: rgba(251, 113, 133, 0.45);
        }

        .kv-route {
            background: rgba(8, 47, 73, 0.35);
            border-color: rgba(34, 211, 238, 0.45);
        }

        .first-app-box {
            margin-top: 14px;
            padding: 11px 12px;
            border-radius: 10px;
            border: 1px solid rgba(34, 211, 238, 0.42);
            background: rgba(6, 78, 59, 0.22);
        }

        .first-app-box .label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #67e8f9;
            margin-bottom: 6px;
        }

        .kv .k {
            display: block;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 5px;
        }

        .kv .v {
            font-size: 14px;
            color: var(--text);
            word-break: break-word;
        }

        .detail-section {
            margin-top: 14px;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
            padding-top: 12px;
        }

        .detail-section h3 {
            margin: 0 0 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
        }

        .trace {
            margin: 0;
            padding-left: 18px;
        }

        .trace li {
            margin-bottom: 6px;
            color: #cbd5e1;
            font-size: 13px;
            line-height: 1.35;
        }

        .trace-toggle {
            margin-top: 8px;
            display: flex;
            gap: 8px;
        }

        .trace-toggle a {
            color: #bae6fd;
            text-decoration: none;
            border: 1px solid rgba(125, 211, 252, 0.35);
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 12px;
        }

        .trace-toggle a.active {
            background: rgba(2, 132, 199, 0.22);
            border-color: rgba(125, 211, 252, 0.65);
        }

        .meta {
            color: var(--muted);
            font-size: 13px;
            margin-top: 8px;
        }

        .level {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid transparent;
            background: rgba(148, 163, 184, 0.15);
            color: #dbeafe;
        }

        .level.emergency,
        .level.alert,
        .level.critical,
        .level.error {
            background: rgba(244, 63, 94, 0.18);
            color: #fecdd3;
            border-color: rgba(244, 63, 94, 0.45);
        }

        .level.warning {
            background: rgba(251, 191, 36, 0.16);
            color: #fde68a;
            border-color: rgba(251, 191, 36, 0.45);
        }

        .level.notice,
        .level.info,
        .level.debug {
            background: rgba(34, 211, 238, 0.16);
            color: #a5f3fc;
            border-color: rgba(34, 211, 238, 0.4);
        }

        .empty {
            color: var(--muted);
            font-style: italic;
        }

        .muted {
            color: var(--muted);
            font-size: 13px;
        }

        .meta-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        code {
            background: rgba(2, 6, 23, 0.65);
            border: 1px solid rgba(148, 163, 184, 0.22);
            color: #bae6fd;
            border-radius: 4px;
            padding: 2px 6px;
        }

        @media (max-width: 900px) {
            .toolbar {
                grid-template-columns: 1fr 1fr 1fr;
            }

            .workspace {
                grid-template-columns: 1fr;
            }

            .issue-list,
            .detail-panel {
                max-height: none;
                min-height: 0;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .toolbar {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 16px 12px 22px;
            }

            .page-title {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    @php
        $namePrefix = rtrim((string) config('logpulse.routes.name_prefix', 'logpulse.'), '.') . '.';
        $indexRouteName = $namePrefix . 'index';
        $issuesRouteName = $namePrefix . 'issues';
        $selectedLevel = (string) ($filters['level'] ?? 'error');
        $selectedHours = (int) ($filters['hours'] ?? 24);
        $selectedFile = (string) ($filters['file'] ?? 'all');
        $selectedSearch = (string) ($filters['search'] ?? '');
        $selectedLimit = (int) ($filters['limit'] ?? (int) config('logpulse.max_groups', 100));
        $traceMode = request()->query('trace', 'compact') === 'expanded' ? 'expanded' : 'compact';
        $levels = ['all', 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        $liveUpdatesEnabled = (bool) ($uiSettings['liveUpdates']['enabled'] ?? true);
        $livePollSeconds = max(2, (int) ($uiSettings['liveUpdates']['pollIntervalSeconds'] ?? 5));
        $selectedFingerprint = (string) request()->query('fingerprint', $issues[0]['fingerprint'] ?? '');
        $selectedIssue = collect($issues)->first(
            fn (array $issue): bool => (string) ($issue['fingerprint'] ?? '') === $selectedFingerprint
        );
        if (! is_array($selectedIssue) && ! empty($issues)) {
            $selectedIssue = $issues[0];
            $selectedFingerprint = (string) ($selectedIssue['fingerprint'] ?? '');
        }
    @endphp
    <main
        class="container"
        id="logpulse-root"
        data-issues-url="{{ route($issuesRouteName) }}"
        data-live-enabled="{{ $liveUpdatesEnabled ? '1' : '0' }}"
        data-live-interval="{{ $livePollSeconds }}"
    >
        <div class="page-title">
            <div>
                <h1>LogPulse</h1>
                <div class="subtitle">Log intelligence view with grouped exceptions and blame hints</div>
            </div>
            <div class="count-pill">{{ count($issues) }} groups</div>
        </div>

        <div class="card">
            <form method="GET" action="{{ route($indexRouteName) }}" id="logpulse-filters-form">
                <div class="toolbar">
                    <div class="field">
                        <label for="level">Level</label>
                        <select id="level" name="level" data-filter-input="1">
                            @foreach ($levels as $level)
                                <option value="{{ $level }}" @selected($selectedLevel === $level)>{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="hours">Hours</label>
                        <input id="hours" name="hours" type="number" min="1" max="168" value="{{ $selectedHours }}" data-filter-input="1" />
                    </div>
                    <div class="field">
                        <label for="file">Log File</label>
                        <select id="file" name="file" data-filter-input="1">
                            <option value="all" @selected($selectedFile === 'all')>all</option>
                            @foreach ($logFiles as $logFile)
                                <option value="{{ $logFile }}" @selected($selectedFile === $logFile)>{{ $logFile }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="search">Search</label>
                        <input id="search" name="search" type="text" value="{{ $selectedSearch }}" placeholder="Exception, message, file..." data-filter-input="1" />
                    </div>
                    <div class="field">
                        <label for="limit">Limit</label>
                        <input id="limit" name="limit" type="number" min="1" max="250" value="{{ $selectedLimit }}" data-filter-input="1" />
                    </div>
                </div>
                <div class="toolbar" style="grid-template-columns: minmax(0, 1fr) 120px; margin-top: 10px;">
                    <div class="muted">{{ count($issues) }} issue group(s) loaded for these filters • live {{ $liveUpdatesEnabled ? 'on' : 'off' }} ({{ $livePollSeconds }}s)</div>
                    <div class="field" style="justify-self: end; width: 120px;">
                        <label>&nbsp;</label>
                        <button type="submit">Apply</button>
                    </div>
                </div>
            </form>
            <div class="meta">JSON endpoint: <code>{{ route($issuesRouteName, request()->query()) }}</code></div>
        </div>

        <section class="workspace">
            <aside class="card issue-list">
                @forelse ($issues as $issue)
                    @php
                        $issueFingerprint = (string) ($issue['fingerprint'] ?? '');
                        $query = array_merge(request()->query(), ['fingerprint' => $issueFingerprint]);
                        $isActive = $issueFingerprint !== '' && $issueFingerprint === $selectedFingerprint;
                    @endphp
                    <a href="{{ route($indexRouteName, $query) }}" class="issue-link {{ $isActive ? 'active' : '' }}">
                        <span class="level {{ e($issue['level'] ?? 'error') }}">{{ $issue['level'] ?? 'error' }}</span>
                        <h2 class="issue-title">{{ $issue['exception_class'] ?? 'Issue' }}</h2>
                        <div class="issue-message">{{ $issue['message'] ?? '' }}</div>
                        <div class="issue-meta">
                            <span>{{ $issue['occurrences'] ?? 0 }} hits</span>
                            <span>{{ isset($issue['last_seen_at']) && method_exists($issue['last_seen_at'], 'toDateTimeString') ? $issue['last_seen_at']->toDateTimeString() : ($issue['last_seen_at'] ?? 'unknown') }}</span>
                        </div>
                    </a>
                @empty
                    <div class="empty">No issues found for current filters.</div>
                @endforelse
            </aside>

            <article class="card detail-panel">
                @if (is_array($selectedIssue))
                    <div class="meta-row" style="justify-content: space-between; align-items: center;">
                        <span class="level {{ e($selectedIssue['level'] ?? 'error') }}">{{ $selectedIssue['level'] ?? 'error' }}</span>
                        <span class="muted">fingerprint: <code>{{ $selectedIssue['fingerprint'] ?? 'n/a' }}</code></span>
                    </div>

                    <h2 class="detail-title">{{ $selectedIssue['exception_class'] ?? 'Issue' }}</h2>
                    <p>{{ $selectedIssue['message'] ?? '' }}</p>

                    @if (! empty($selectedIssue['first_app_frame']))
                        <div class="first-app-box">
                            <span class="label">First App Entry</span>
                            <code>{{ $selectedIssue['first_app_frame'] }}</code>
                        </div>
                    @endif

                    <div class="detail-grid">
                        <div class="kv">
                            <span class="k">Occurrences</span>
                            <span class="v">{{ $selectedIssue['occurrences'] ?? 0 }}</span>
                        </div>
                        <div class="kv">
                            <span class="k">Source File</span>
                            <span class="v">{{ $selectedIssue['source_file'] ?? 'unknown' }}</span>
                        </div>
                        <div class="kv">
                            <span class="k">First Seen</span>
                            <span class="v">{{ isset($selectedIssue['first_seen_at']) && method_exists($selectedIssue['first_seen_at'], 'toDateTimeString') ? $selectedIssue['first_seen_at']->toDateTimeString() : ($selectedIssue['first_seen_at'] ?? 'unknown') }}</span>
                        </div>
                        <div class="kv">
                            <span class="k">Last Seen</span>
                            <span class="v">{{ isset($selectedIssue['last_seen_at']) && method_exists($selectedIssue['last_seen_at'], 'toDateTimeString') ? $selectedIssue['last_seen_at']->toDateTimeString() : ($selectedIssue['last_seen_at'] ?? 'unknown') }}</span>
                        </div>
                    </div>

                    @if (! empty($selectedIssue['source_files']) && is_array($selectedIssue['source_files']))
                        <div class="detail-section">
                            <h3>Observed Files</h3>
                            <div class="meta">{{ implode(', ', $selectedIssue['source_files']) }}</div>
                        </div>
                    @endif

                    @if (! empty($selectedIssue['blame']) && is_array($selectedIssue['blame']))
                        <div class="detail-section">
                            <h3>Blame Target</h3>
                            <div class="detail-grid">
                                <div class="kv kv-blame"><span class="k">Component</span><span class="v">{{ $selectedIssue['blame']['component'] ?? 'unknown' }}</span></div>
                                <div class="kv kv-blame"><span class="k">Symbol</span><span class="v">{{ $selectedIssue['blame']['class'] ?? '' }}{{ !empty($selectedIssue['blame']['method']) ? '::'.$selectedIssue['blame']['method'] : '' }}</span></div>
                                <div class="kv kv-blame"><span class="k">File</span><span class="v">{{ $selectedIssue['blame']['file'] ?? 'n/a' }}</span></div>
                                <div class="kv kv-blame"><span class="k">Line</span><span class="v">{{ $selectedIssue['blame']['line'] ?? 'n/a' }}</span></div>
                            </div>
                        </div>
                    @endif

                    @if (! empty($selectedIssue['route_context']) && is_array($selectedIssue['route_context']))
                        <div class="detail-section">
                            <h3>Route Context</h3>
                            <div class="detail-grid">
                                <div class="kv kv-route"><span class="k">URI</span><span class="v"><code>{{ $selectedIssue['route_context']['uri'] ?? 'n/a' }}</code></span></div>
                                <div class="kv kv-route"><span class="k">Action</span><span class="v"><code>{{ $selectedIssue['route_context']['action'] ?? 'n/a' }}</code></span></div>
                                <div class="kv kv-route"><span class="k">Methods</span><span class="v">{{ !empty($selectedIssue['route_context']['methods']) && is_array($selectedIssue['route_context']['methods']) ? implode(', ', $selectedIssue['route_context']['methods']) : 'n/a' }}</span></div>
                                <div class="kv kv-route"><span class="k">Middleware</span><span class="v">{{ !empty($selectedIssue['route_context']['middleware']) && is_array($selectedIssue['route_context']['middleware']) ? implode(', ', $selectedIssue['route_context']['middleware']) : 'n/a' }}</span></div>
                            </div>
                        </div>
                    @endif

                    @if (! empty($selectedIssue['trace']) && is_array($selectedIssue['trace']))
                        <div class="detail-section">
                            <h3>Stack Trace</h3>
                            <div class="trace-toggle">
                                <a class="{{ $traceMode === 'compact' ? 'active' : '' }}" href="{{ route($indexRouteName, array_merge(request()->query(), ['trace' => 'compact'])) }}">Compact</a>
                                <a class="{{ $traceMode === 'expanded' ? 'active' : '' }}" href="{{ route($indexRouteName, array_merge(request()->query(), ['trace' => 'expanded'])) }}">Expanded</a>
                            </div>
                            <ol class="trace">
                                @foreach (($traceMode === 'expanded' ? $selectedIssue['trace'] : array_slice($selectedIssue['trace'], 0, 12)) as $frame)
                                    <li><code>{{ $frame }}</code></li>
                                @endforeach
                            </ol>
                            @if ($traceMode === 'compact' && count($selectedIssue['trace']) > 12)
                                <div class="meta">Showing first 12 of {{ count($selectedIssue['trace']) }} frames.</div>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="empty">Select an issue to inspect full details.</div>
                @endif
            </article>
        </section>
    </main>
</body>
</html>
