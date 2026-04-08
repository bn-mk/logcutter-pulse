<?php

namespace Logcutter\LogPulse\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class LogExplorerService
{
    /**
     * @var array<string, int>
     */
    private const LEVEL_RANK = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function groupedIssues(
        ?string $minimumLevel = null,
        ?string $search = null,
        ?int $hours = null,
        ?int $limit = null,
        ?string $file = null,
    ): array {
        $configuredLevel = (string) config('logpulse.default_level', 'error');
        $configuredHours = (int) config('logpulse.default_hours', 24);
        $configuredLimit = (int) config('logpulse.max_groups', 100);

        $effectiveLevel = Str::lower($minimumLevel ?? $configuredLevel);
        $effectiveHours = $hours ?? $configuredHours;
        $effectiveLimit = $limit ?? $configuredLimit;

        $thresholdRank = self::LEVEL_RANK[$effectiveLevel] ?? self::LEVEL_RANK['error'];
        $applyLevelFilter = $effectiveLevel !== 'all';
        $searchTerm = Str::lower(trim((string) $search));
        $cutoff = CarbonImmutable::now()->subHours($effectiveHours);

        $entries = $this->allEntries($file);
        $groups = [];

        foreach ($entries as $entry) {
            $entryRank = self::LEVEL_RANK[$entry['level']] ?? self::LEVEL_RANK['debug'];
            if ($applyLevelFilter && $entryRank > $thresholdRank) {
                continue;
            }

            if ($entry['timestamp']->lt($cutoff)) {
                continue;
            }

            $searchable = Str::lower(implode(' ', [
                $entry['message'],
                $entry['exception_class'] ?? '',
                $entry['first_app_frame'] ?? '',
                $entry['source_file'],
            ]));

            if ($searchTerm !== '' && ! str_contains($searchable, $searchTerm)) {
                continue;
            }

            $fingerprint = $this->fingerprintForEntry($entry);

            if (! isset($groups[$fingerprint])) {
                $groups[$fingerprint] = [
                    'fingerprint' => $fingerprint,
                    'exception_class' => $entry['exception_class'],
                    'message' => $entry['message'],
                    'level' => $entry['level'],
                    'occurrences' => 0,
                    'first_seen_at' => $entry['timestamp'],
                    'last_seen_at' => $entry['timestamp'],
                    'first_app_frame' => $entry['first_app_frame'],
                    'trace' => $entry['trace'],
                    'enriched_trace' => $entry['enriched_trace'],
                    'source_file' => $entry['source_file'],
                    'source_files' => [$entry['source_file']],
                    'route_context' => $entry['route_context'],
                    'blame' => $entry['blame'],
                ];
            }

            $groups[$fingerprint]['occurrences']++;

            if ($entry['timestamp']->lt($groups[$fingerprint]['first_seen_at'])) {
                $groups[$fingerprint]['first_seen_at'] = $entry['timestamp'];
            }

            if ($entry['timestamp']->gt($groups[$fingerprint]['last_seen_at'])) {
                $groups[$fingerprint]['last_seen_at'] = $entry['timestamp'];
                $groups[$fingerprint]['message'] = $entry['message'];
                $groups[$fingerprint]['level'] = $entry['level'];
                $groups[$fingerprint]['first_app_frame'] = $entry['first_app_frame'];
                $groups[$fingerprint]['trace'] = $entry['trace'];
                $groups[$fingerprint]['enriched_trace'] = $entry['enriched_trace'];
                $groups[$fingerprint]['source_file'] = $entry['source_file'];
                $groups[$fingerprint]['route_context'] = $entry['route_context'];
                $groups[$fingerprint]['blame'] = $entry['blame'];
            }

            if (! in_array($entry['source_file'], $groups[$fingerprint]['source_files'], true)) {
                $groups[$fingerprint]['source_files'][] = $entry['source_file'];
            }
        }

        usort($groups, function (array $left, array $right): int {
            $lastSeenComparison = $right['last_seen_at']->getTimestamp() <=> $left['last_seen_at']->getTimestamp();
            if ($lastSeenComparison !== 0) {
                return $lastSeenComparison;
            }

            return $right['occurrences'] <=> $left['occurrences'];
        });

        $groups = array_slice($groups, 0, $effectiveLimit);

        return array_map(function (array $group): array {
            return [
                'fingerprint' => $group['fingerprint'],
                'exception_class' => $group['exception_class'],
                'message' => $group['message'],
                'level' => $group['level'],
                'occurrences' => $group['occurrences'],
                'first_seen_at' => $group['first_seen_at']->toIso8601String(),
                'last_seen_at' => $group['last_seen_at']->toIso8601String(),
                'first_app_frame' => $group['first_app_frame'],
                'trace' => $group['trace'],
                'enriched_trace' => $group['enriched_trace'],
                'source_file' => $group['source_file'],
                'source_files' => $group['source_files'],
                'route_context' => $group['route_context'],
                'blame' => $group['blame'],
            ];
        }, $groups);
    }

    /**
     * @return array<int, string>
     */
    public function availableLogFiles(): array
    {
        return $this->resolveLogFiles(null)
            ->map(fn (\SplFileInfo $file): string => $file->getFilename())
            ->all();
    }

    /**
     * @return array<int, array{
     *   timestamp: CarbonImmutable,
     *   level: string,
     *   message: string,
     *   exception_class: ?string,
     *   first_app_frame: ?string,
     *   trace: array<int, string>,
     *   source_file: string
     * }>
     */
    private function allEntries(?string $selectedFile = null): array
    {
        $entries = [];

        foreach ($this->resolveLogFiles($selectedFile) as $file) {
            $fileContent = $this->readLogTail($file);

            foreach ($this->parseEntries($fileContent, $file->getFilename()) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function readLogTail(\SplFileInfo $file): string
    {
        $logFile = $file->getPathname();
        if (! File::exists($logFile)) {
            return '';
        }

        $maxReadBytes = max(4096, (int) config('logpulse.max_read_bytes', 2 * 1024 * 1024));
        $size = File::size($logFile);
        $startOffset = max(0, $size - $maxReadBytes);

        $handle = fopen($logFile, 'rb');
        if (! is_resource($handle)) {
            return '';
        }

        fseek($handle, $startOffset);
        $content = stream_get_contents($handle);
        fclose($handle);

        if (! is_string($content)) {
            return '';
        }

        if ($startOffset > 0) {
            $firstNewlinePos = strpos($content, "\n");
            if ($firstNewlinePos !== false) {
                $content = substr($content, $firstNewlinePos + 1);
            }
        }

        return $content;
    }

    /**
     * @return Collection<int, \SplFileInfo>
     */
    private function resolveLogFiles(?string $selectedFile): Collection
    {
        $configuredPath = (string) config('logpulse.log_path', storage_path('logs'));

        if (File::isFile($configuredPath)) {
            $singleFile = new \SplFileInfo($configuredPath);

            return collect([$singleFile]);
        }

        $directory = File::isDirectory($configuredPath) ? $configuredPath : storage_path('logs');

        $logFiles = collect(File::files($directory))
            ->filter(fn (\SplFileInfo $file): bool => Str::endsWith($file->getFilename(), '.log'))
            ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
            ->values();

        if ($selectedFile === null || $selectedFile === '' || $selectedFile === 'all') {
            return $logFiles;
        }

        $safeSelectedFile = basename($selectedFile);

        return $logFiles
            ->filter(fn (\SplFileInfo $file): bool => $file->getFilename() === $safeSelectedFile)
            ->values();
    }

    /**
     * @return array<int, array{
     *   timestamp: CarbonImmutable,
     *   level: string,
     *   message: string,
     *   exception_class: ?string,
     *   first_app_frame: ?string,
     *   trace: array<int, string>,
     *   enriched_trace: array<int, array<string, mixed>>,
     *   route_context: array<string, mixed>|null,
     *   blame: array<string, mixed>|null
     * }>
     */
    private function parseEntries(string $content, string $sourceFile): array
    {
        if (trim($content) === '') {
            return [];
        }

        // Match only canonical Laravel log headers and avoid multi-line over-capture.
        preg_match_all('/^\[(?<timestamp>[^\]\r\n]+)\]\s+(?<channel>[A-Za-z0-9_.-]+)\.(?<level>[A-Z]+):\s(?<message>.*)$/m', $content, $matches, PREG_OFFSET_CAPTURE);

        if (! isset($matches[0])) {
            return [];
        }

        $entries = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            $start = $matches[0][$index][1];
            $end = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($content);
            $block = trim(substr($content, $start, $end - $start));

            $timestampRaw = $matches['timestamp'][$index][0];
            $level = Str::lower($matches['level'][$index][0]);
            $message = trim($matches['message'][$index][0]);

            $trace = $this->extractTrace($block);
            $enrichedTrace = array_map(fn (string $frame): array => $this->parseTraceFrame($frame), $trace);

            $firstAppTrace = collect($enrichedTrace)->first(fn (array $frame): bool => ($frame['frame_type'] ?? null) === 'app');
            $actionableAppTrace = collect($enrichedTrace)->first(
                fn (array $frame): bool => ($frame['frame_type'] ?? null) === 'app'
                    && (($frame['class'] ?? null) !== null || ($frame['method'] ?? null) !== null)
            );
            $firstAppFrame = is_array($firstAppTrace) ? ($firstAppTrace['raw'] ?? null) : null;
            $controllerFrame = collect($enrichedTrace)->first(
                fn (array $frame): bool => ($frame['component'] ?? null) === 'controller'
                    && ($frame['class'] ?? null) !== null
                    && ($frame['method'] ?? null) !== null
            );

            if (! is_array($controllerFrame)) {
                $controllerFrame = collect($enrichedTrace)->first(fn (array $frame): bool => ($frame['component'] ?? null) === 'controller');
            }

            $requestContext = $this->extractRequestContext($message, $block);
            $routeContext = $this->resolveRouteContext($requestContext, is_array($controllerFrame) ? $controllerFrame : null);
            $exceptionOrigin = $this->extractExceptionOrigin($message, $block);

            $blameTarget = $this->resolveBlameTarget(
                $exceptionOrigin,
                is_array($firstAppTrace) ? $firstAppTrace : null,
                is_array($actionableAppTrace) ? $actionableAppTrace : null,
                is_array($controllerFrame) ? $controllerFrame : null,
            );

            try {
                $timestamp = CarbonImmutable::parse($timestampRaw);
            } catch (\Throwable) {
                continue;
            }

            $entries[] = [
                'timestamp' => $timestamp,
                'level' => array_key_exists($level, self::LEVEL_RANK) ? $level : 'error',
                'message' => $message,
                'exception_class' => $this->extractExceptionClass($message),
                'first_app_frame' => $firstAppFrame,
                'trace' => $trace,
                'enriched_trace' => $enrichedTrace,
                'source_file' => $sourceFile,
                'route_context' => $routeContext,
                'blame' => $blameTarget === null ? null : [
                    'file' => $blameTarget['file'],
                    'line' => $blameTarget['line'],
                    'class' => $blameTarget['class'],
                    'method' => $blameTarget['method'],
                    'component' => $blameTarget['component'],
                ],
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function extractTrace(string $block): array
    {
        preg_match_all('/^#\d+\s+(.+)$/m', $block, $matches);

        return array_values(array_map('trim', $matches[1] ?? []));
    }

    private function extractExceptionClass(string $message): ?string
    {
        if (preg_match('/(?<class>[A-Za-z0-9_\\\\]+(?:Exception|Error))(?:[:\s]|$)/', $message, $matches) === 1) {
            return $matches['class'];
        }

        return null;
    }

    /**
     * @return array{
     *   raw: string,
     *   file: ?string,
     *   line: ?int,
     *   class: ?string,
     *   method: ?string,
     *   frame_type: string,
     *   component: string
     * }
     */
    private function parseTraceFrame(string $line): array
    {
        $file = null;
        $lineNumber = null;
        $class = null;
        $method = null;

        if (preg_match('/^(?<file>.+?)\((?<line>\d+)\):\s(?<call>.+)$/', $line, $parts) === 1) {
            $file = trim($parts['file']);
            $lineNumber = (int) $parts['line'];
            $call = trim($parts['call']);

            if (preg_match('/^(?<class>[A-Za-z0-9_\\\\]+)(?:->|::)(?<method>[A-Za-z0-9_]+)\(.*\)$/', $call, $callParts) === 1) {
                $class = str_replace('\\\\', '\\', $callParts['class']);
                $method = $callParts['method'];
            }
        }

        $frameType = $this->classifyFrameType($file, $class);
        $component = $this->classifyComponent($file, $class);

        return [
            'raw' => $line,
            'file' => $file,
            'line' => $lineNumber,
            'class' => $class,
            'method' => $method,
            'frame_type' => $frameType,
            'component' => $component,
        ];
    }

    private function classifyFrameType(?string $file, ?string $class): string
    {
        $fileValue = $this->normalizedTraceSubject($file);
        $classValue = $this->normalizedTraceSubject($class);

        if (
            str_contains($fileValue, '/app/') ||
            str_contains($classValue, 'app/') ||
            str_contains($fileValue, '/packages/logcutter/logpulse/src/') ||
            str_contains($classValue, 'logcutter/logpulse/')
        ) {
            return 'app';
        }

        if (str_contains($fileValue, '/vendor/')) {
            return 'vendor';
        }

        if (
            str_contains($fileValue, '/bootstrap/') ||
            str_contains($fileValue, '/public/index.php') ||
            str_contains($classValue, 'laravel/framework')
        ) {
            return 'core';
        }

        return 'other';
    }

    private function classifyComponent(?string $file, ?string $class): string
    {
        $subject = trim($this->normalizedTraceSubject($file).' '.$this->normalizedTraceSubject($class));

        return match (true) {
            str_contains($subject, '/http/controllers/') => 'controller',
            str_contains($subject, '/jobs/') => 'job',
            str_contains($subject, '/listeners/') => 'listener',
            str_contains($subject, '/console/commands/') => 'command',
            str_contains($subject, '/http/middleware/') => 'middleware',
            str_contains($subject, '/models/') => 'model',
            str_contains($subject, '/services/') => 'service',
            default => 'other',
        };
    }

    private function normalizedTraceSubject(?string $value): string
    {
        $subject = str_replace('\\\\', '\\', (string) $value);
        $subject = str_replace('\\', '/', $subject);

        return Str::lower($subject);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractExceptionOrigin(string $message, string $block): ?array
    {
        $search = $message.' '.$block;

        if (preg_match('/\sat\s(?<file>\/[^\s:]+\/(?:app|packages\/logcutter\/logpulse\/src)\/[^\s:]+):(?<line>\d+)/', $search, $matches) !== 1) {
            return null;
        }

        $file = str_replace('\\', '/', $matches['file']);

        return [
            'file' => $file,
            'line' => (int) $matches['line'],
            'class' => null,
            'method' => null,
            'component' => $this->classifyComponent($file, null),
            'frame_type' => $this->classifyFrameType($file, null),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $firstAppTrace
     * @param  array<string, mixed>|null  $actionableAppTrace
     * @param  array<string, mixed>|null  $controllerFrame
     * @return array<string, mixed>|null
     */
    private function resolveBlameTarget(?array $exceptionOrigin, ?array $firstAppTrace, ?array $actionableAppTrace, ?array $controllerFrame): ?array
    {
        if ($exceptionOrigin !== null) {
            $metadataSource = $actionableAppTrace ?? $controllerFrame ?? $exceptionOrigin;

            return [
                'file' => $exceptionOrigin['file'] ?? null,
                'line' => $exceptionOrigin['line'] ?? null,
                'class' => $metadataSource['class'] ?? null,
                'method' => $metadataSource['method'] ?? null,
                'component' => $exceptionOrigin['component'] ?? ($metadataSource['component'] ?? 'other'),
            ];
        }

        if ($firstAppTrace !== null) {
            $metadataSource = $actionableAppTrace ?? $controllerFrame ?? $firstAppTrace;

            return [
                'file' => $firstAppTrace['file'] ?? null,
                'line' => $firstAppTrace['line'] ?? null,
                'class' => $metadataSource['class'] ?? null,
                'method' => $metadataSource['method'] ?? null,
                'component' => $firstAppTrace['component'] ?? ($metadataSource['component'] ?? 'other'),
            ];
        }

        if ($actionableAppTrace !== null) {
            return $actionableAppTrace;
        }

        return $controllerFrame;
    }

    /**
     * @return array{method: string|null, path: string}|null
     */
    private function extractRequestContext(string $message, string $block): ?array
    {
        $search = $message.' '.$block;

        if (preg_match('/\b(?<method>GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD)\s+(?<path>https?:\/\/[^\s]+|\/[^\s]+)/i', $search, $matches) === 1) {
            $rawPath = trim($matches['path']);
            $path = parse_url($rawPath, PHP_URL_PATH);

            return [
                'method' => Str::upper($matches['method']),
                'path' => is_string($path) && $path !== '' ? $path : $rawPath,
            ];
        }

        if (preg_match('/"url":"(?<url>[^"]+)"/i', $search, $matches) === 1) {
            $decodedUrl = stripcslashes($matches['url']);
            $path = parse_url($decodedUrl, PHP_URL_PATH);

            if (is_string($path) && $path !== '') {
                return [
                    'method' => null,
                    'path' => $path,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array{method: string|null, path: string}|null  $requestContext
     * @param  array<string, mixed>|null  $controllerFrame
     * @return array<string, mixed>|null
     */
    private function resolveRouteContext(?array $requestContext, ?array $controllerFrame): ?array
    {
        $routes = Route::getRoutes();

        if ($controllerFrame !== null && $controllerFrame['class'] !== null && $controllerFrame['method'] !== null) {
            $action = $controllerFrame['class'].'@'.$controllerFrame['method'];

            foreach ($routes as $route) {
                if ($route->getActionName() === $action) {
                    return [
                        'matched_by' => 'controller_action',
                        'name' => $route->getName(),
                        'uri' => $route->uri(),
                        'methods' => $route->methods(),
                        'middleware' => $route->gatherMiddleware(),
                        'action' => $route->getActionName(),
                    ];
                }
            }
        }

        if ($requestContext === null) {
            return null;
        }

        $requestedPath = trim($requestContext['path'], '/');
        $requestedMethod = $requestContext['method'] === null ? null : Str::upper($requestContext['method']);

        foreach ($routes as $route) {
            $routeUri = trim($route->uri(), '/');
            $routeMethods = array_map(static fn (string $method): string => Str::upper($method), $route->methods());

            $methodMatches = $requestedMethod === null
                ? (in_array('GET', $routeMethods, true) || in_array('HEAD', $routeMethods, true))
                : in_array($requestedMethod, $routeMethods, true);

            if ($routeUri === $requestedPath && $methodMatches) {
                return [
                    'matched_by' => 'request_path',
                    'name' => $route->getName(),
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'middleware' => $route->gatherMiddleware(),
                    'action' => $route->getActionName(),
                ];
            }
        }

        return null;
    }

    /**
     * @param array{
     *   message: string,
     *   exception_class: ?string,
     *   first_app_frame: ?string
     * } $entry
     */
    private function fingerprintForEntry(array $entry): string
    {
        $normalizedMessage = Str::lower($entry['message']);
        $normalizedMessage = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i', ':uuid', $normalizedMessage) ?? $normalizedMessage;
        $normalizedMessage = preg_replace('/\b\d+\b/', ':num', $normalizedMessage) ?? $normalizedMessage;

        $signature = implode('|', [
            $entry['exception_class'] ?? 'unknown-exception',
            $normalizedMessage,
            $entry['first_app_frame'] ?? 'unknown-frame',
        ]);

        return sha1($signature);
    }
}
