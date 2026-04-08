<?php

namespace Logcutter\LogPulse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Logcutter\LogPulse\Http\Requests\IssueIndexRequest;
use Logcutter\LogPulse\Services\LogExplorerService;

class LogPulseController
{
    public function index(IssueIndexRequest $request, LogExplorerService $logExplorerService): Response|View
    {
        $filters = $this->normalizedFilters($request->validated());
        $issues = $logExplorerService->groupedIssues(
            $filters['level'],
            $filters['search'],
            $filters['hours'],
            $filters['limit'],
            $filters['file'],
        );

        $viewData = [
            'issues' => $issues,
            'filters' => $filters,
            'logFiles' => $logExplorerService->availableLogFiles(),
            'uiSettings' => $this->uiSettings(),
        ];

        if ((string) config('logpulse.ui.driver', 'blade') === 'inertia') {
            Inertia::setRootView((string) config('logpulse.inertia.root_view', 'logpulse::app'));

            return Inertia::render('logpulse/index', $viewData);
        }

        return view('logpulse::index', $viewData);
    }

    public function issues(IssueIndexRequest $request, LogExplorerService $logExplorerService): JsonResponse
    {
        $filters = $this->normalizedFilters($request->validated());

        return response()->json([
            'data' => $logExplorerService->groupedIssues(
                $filters['level'],
                $filters['search'],
                $filters['hours'],
                $filters['limit'],
                $filters['file'],
            ),
            'meta' => [
                'filters' => $filters,
                'available_files' => $logExplorerService->availableLogFiles(),
                'ui_settings' => $this->uiSettings(),
            ],
        ]);
    }

    /**
     * @return array{
     *   liveUpdates: array{enabled: bool, pollIntervalSeconds: int},
     *   notifications: array{enabled: bool, threshold: int, level: string}
     * }
     */
    private function uiSettings(): array
    {
        return [
            'liveUpdates' => [
                'enabled' => (bool) config('logpulse.live_updates.enabled', true),
                'pollIntervalSeconds' => max(2, (int) config('logpulse.live_updates.poll_interval_seconds', 5)),
            ],
            'notifications' => [
                'enabled' => (bool) config('logpulse.notifications.enabled', true),
                'threshold' => max(1, (int) config('logpulse.notifications.threshold', 3)),
                'level' => (string) config('logpulse.notifications.level', 'error'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{level: string, search: ?string, file: ?string, hours: int, limit: int}
     */
    private function normalizedFilters(array $validated): array
    {
        return [
            'level' => (string) ($validated['level'] ?? config('logpulse.default_level', 'error')),
            'search' => isset($validated['search']) && $validated['search'] !== '' ? (string) $validated['search'] : null,
            'file' => isset($validated['file']) && $validated['file'] !== '' ? (string) $validated['file'] : 'all',
            'hours' => (int) ($validated['hours'] ?? config('logpulse.default_hours', 24)),
            'limit' => (int) ($validated['limit'] ?? config('logpulse.max_groups', 100)),
        ];
    }
}
