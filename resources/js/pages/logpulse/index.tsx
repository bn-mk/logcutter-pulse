import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { index as logpulseIndex, issues as issuesRoute } from '@/routes/logpulse';

type LogIssue = {
    fingerprint: string;
    exception_class: string | null;
    message: string;
    level: string;
    occurrences: number;
    first_seen_at: string;
    last_seen_at: string;
    first_app_frame: string | null;
    trace: string[];
    enriched_trace?: EnrichedFrame[];
    source_file: string;
    source_files: string[];
    route_context?: RouteContext | null;
    blame?: BlameTarget | null;
};

type EnrichedFrame = {
    raw: string;
    file: string | null;
    line: number | null;
    class: string | null;
    method: string | null;
    frame_type: string;
    component: string;
};

type RouteContext = {
    matched_by: string;
    name: string | null;
    uri: string;
    methods: string[];
    middleware: string[];
    action: string;
};

type BlameTarget = {
    file: string | null;
    line: number | null;
    class: string | null;
    method: string | null;
    component: string;
};

type Filters = {
    level: string;
    search: string | null;
    file: string | null;
    hours: number;
    limit: number;
};

type UiSettings = {
    liveUpdates: {
        enabled: boolean;
        pollIntervalSeconds: number;
    };
    notifications: {
        enabled: boolean;
        threshold: number;
        level: string;
    };
};

type UiToast = {
    id: string;
    title: string;
    body: string;
};

const LEVEL_RANK: Record<string, number> = {
    emergency: 0,
    alert: 1,
    critical: 2,
    error: 3,
    warning: 4,
    notice: 5,
    info: 6,
    debug: 7,
};

const LEVEL_BADGE_CLASS: Record<string, string> = {
    emergency: 'bg-rose-500/20 text-rose-200 border border-rose-400/40',
    alert: 'bg-rose-500/20 text-rose-200 border border-rose-400/40',
    critical: 'bg-red-500/20 text-red-200 border border-red-400/40',
    error: 'bg-orange-500/20 text-orange-200 border border-orange-400/40',
    warning: 'bg-amber-500/20 text-amber-200 border border-amber-400/40',
    notice: 'bg-cyan-500/20 text-cyan-200 border border-cyan-400/40',
    info: 'bg-blue-500/20 text-blue-200 border border-blue-400/40',
    debug: 'bg-zinc-500/20 text-zinc-200 border border-zinc-400/40',
};

const LEVEL_ACCENT_CLASS: Record<string, string> = {
    emergency: 'border-l-rose-400',
    alert: 'border-l-rose-400',
    critical: 'border-l-red-400',
    error: 'border-l-orange-400',
    warning: 'border-l-amber-400',
    notice: 'border-l-cyan-400',
    info: 'border-l-blue-400',
    debug: 'border-l-zinc-400',
};

const formatLevelLabel = (level: string): string => `${level.charAt(0).toUpperCase()}${level.slice(1)}`;

const formatRelativeTime = (isoTimestamp: string): string => {
    const now = Date.now();
    const value = new Date(isoTimestamp).getTime();
    const seconds = Math.max(0, Math.floor((now - value) / 1000));

    if (seconds < 60) {
        return `${seconds}s ago`;
    }

    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);
    return `${days}d ago`;
};

const classifyTraceFrame = (line: string): 'app' | 'vendor' | 'core' | 'other' => {
    const normalized = line.toLowerCase();

    if (normalized.includes('/app/') || normalized.includes('\\app\\')) {
        return 'app';
    }

    if (normalized.includes('/vendor/') || normalized.includes('\\vendor\\')) {
        return 'vendor';
    }

    if (
        normalized.includes('/bootstrap/') ||
        normalized.includes('\\bootstrap\\') ||
        normalized.includes('/public/index.php') ||
        normalized.includes('\\public\\index.php') ||
        normalized.includes('laravel/framework')
    ) {
        return 'core';
    }

    return 'other';
};

export default function LogPulseIndex({
    issues,
    filters,
    logFiles,
    uiSettings,
}: {
    issues: LogIssue[];
    filters: Filters;
    logFiles: string[];
    uiSettings: UiSettings;
}) {
    const [level, setLevel] = useState(filters.level);
    const [hours, setHours] = useState(String(filters.hours));
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedFile, setSelectedFile] = useState(filters.file ?? 'all');
    const [availableFiles, setAvailableFiles] = useState<string[]>(logFiles);
    const [groupedIssues, setGroupedIssues] = useState<LogIssue[]>(issues);
    const [selectedFingerprint, setSelectedFingerprint] = useState<string | null>(issues[0]?.fingerprint ?? null);
    const [liveEnabled, setLiveEnabled] = useState(uiSettings.liveUpdates.enabled);
    const [notificationsEnabled, setNotificationsEnabled] = useState(uiSettings.notifications.enabled);
    const [notificationPermission, setNotificationPermission] = useState<NotificationPermission | 'unsupported'>(
        typeof window !== 'undefined' && 'Notification' in window ? window.Notification.permission : 'unsupported',
    );
    const [toasts, setToasts] = useState<UiToast[]>([]);
    const [loading, setLoading] = useState(false);
    const [showSecondaryFrames, setShowSecondaryFrames] = useState(false);
    const previousOccurrencesRef = useRef<Record<string, number>>(
        issues.reduce<Record<string, number>>((carry, issue) => {
            carry[issue.fingerprint] = issue.occurrences;
            return carry;
        }, {}),
    );

    const pushToast = (title: string, body: string) => {
        const toast: UiToast = {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
            title,
            body,
        };

        setToasts((current) => [toast, ...current].slice(0, 4));

        window.setTimeout(() => {
            setToasts((current) => current.filter((item) => item.id !== toast.id));
        }, 5000);
    };

    const maybeSendBrowserNotification = (title: string, body: string) => {
        if (
            typeof window === 'undefined' ||
            !('Notification' in window) ||
            !notificationsEnabled ||
            window.Notification.permission !== 'granted'
        ) {
            return;
        }

        new window.Notification(title, { body });
    };

    const applyIncomingIssues = (incomingIssues: LogIssue[], shouldNotify: boolean) => {
        if (shouldNotify && notificationsEnabled) {
            const previousOccurrences = previousOccurrencesRef.current;
            const threshold = Math.max(1, uiSettings.notifications.threshold);
            const thresholdRank = LEVEL_RANK[uiSettings.notifications.level] ?? LEVEL_RANK.error;

            incomingIssues.forEach((issue) => {
                const previous = previousOccurrences[issue.fingerprint] ?? 0;
                const increase = issue.occurrences - previous;
                const issueRank = LEVEL_RANK[issue.level] ?? LEVEL_RANK.debug;

                if (increase >= threshold && issueRank <= thresholdRank) {
                    const label = issue.exception_class ?? issue.level.toUpperCase();
                    const body = `+${increase} events (${issue.occurrences} total)`;
                    pushToast(label, body);
                    maybeSendBrowserNotification(label, body);
                }
            });
        }

        previousOccurrencesRef.current = incomingIssues.reduce<Record<string, number>>((carry, issue) => {
            carry[issue.fingerprint] = issue.occurrences;
            return carry;
        }, {});

        setGroupedIssues(incomingIssues);
    };

    const fetchIssues = async (shouldNotify: boolean) => {
        setLoading(true);

        try {
            const response = await fetch(
                issuesRoute.url({
                    query: {
                        level,
                        hours,
                        file: selectedFile,
                        search: search.trim() === '' ? undefined : search.trim(),
                    },
                }),
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            if (!response.ok) {
                return;
            }

            const payload: { data: LogIssue[]; meta?: { available_files?: string[] } } = await response.json();
            applyIncomingIssues(payload.data, shouldNotify);

            if (Array.isArray(payload.meta?.available_files)) {
                setAvailableFiles(payload.meta.available_files);
            }
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (groupedIssues.length > 0 && !groupedIssues.some((issue) => issue.fingerprint === selectedFingerprint)) {
            setSelectedFingerprint(groupedIssues[0].fingerprint);
        }
    }, [groupedIssues, selectedFingerprint]);

    useEffect(() => {
        setShowSecondaryFrames(false);
    }, [selectedFingerprint]);

    useEffect(() => {
        const timeout = setTimeout(() => {
            void fetchIssues(false);
        }, 300);

        return () => clearTimeout(timeout);
    }, [hours, level, search, selectedFile]);

    useEffect(() => {
        if (!liveEnabled) {
            return;
        }

        const intervalMs = Math.max(2, uiSettings.liveUpdates.pollIntervalSeconds) * 1000;
        const intervalId = window.setInterval(() => {
            void fetchIssues(true);
        }, intervalMs);

        return () => window.clearInterval(intervalId);
    }, [liveEnabled, hours, level, search, selectedFile]);

    const selectedIssue = useMemo(
        () => groupedIssues.find((issue) => issue.fingerprint === selectedFingerprint) ?? groupedIssues[0] ?? null,
        [groupedIssues, selectedFingerprint],
    );

    const selectedIssueFrames = useMemo(() => {
        if (selectedIssue === null) {
            return {
                appFrames: [] as EnrichedFrame[],
                secondaryFrames: [] as EnrichedFrame[],
            };
        }

        const frames =
            selectedIssue.enriched_trace && selectedIssue.enriched_trace.length > 0
                ? selectedIssue.enriched_trace
                : selectedIssue.trace.map((line) => ({
                      raw: line,
                      file: null,
                      line: null,
                      class: null,
                      method: null,
                      frame_type: classifyTraceFrame(line),
                      component: 'other',
                  }));

        const appFrames: EnrichedFrame[] = [];
        const secondaryFrames: EnrichedFrame[] = [];

        frames.forEach((frame) => {
            if (frame.frame_type === 'app') {
                appFrames.push(frame);
                return;
            }

            secondaryFrames.push(frame);
        });

        return {
            appFrames,
            secondaryFrames,
        };
    }, [selectedIssue]);

    const stats = useMemo(() => {
        const totalOccurrences = groupedIssues.reduce((sum, issue) => sum + issue.occurrences, 0);
        const criticalGroups = groupedIssues.filter((issue) => {
            const rank = LEVEL_RANK[issue.level] ?? LEVEL_RANK.debug;
            return rank <= LEVEL_RANK.error;
        }).length;
        const latestSeenAt = groupedIssues[0]?.last_seen_at ?? null;

        return {
            totalGroups: groupedIssues.length,
            totalOccurrences,
            criticalGroups,
            latestSeenAt,
        };
    }, [groupedIssues]);

    return (
        <>
            <Head title="Logcutter" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden p-4">
                <div className="pointer-events-none fixed top-4 right-4 z-30 flex w-[min(26rem,90vw)] flex-col gap-2">
                    {toasts.map((toast) => (
                        <div
                            key={toast.id}
                            className="pointer-events-auto rounded-lg border border-amber-300/40 bg-amber-500/10 px-3 py-2 text-sm shadow-lg backdrop-blur"
                        >
                            <p className="font-semibold text-amber-200">{toast.title}</p>
                            <p className="text-amber-100/80">{toast.body}</p>
                        </div>
                    ))}
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 p-4 text-slate-100">
                    <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">Logcutter</h1>
                            <p className="mt-1 text-sm text-slate-300">Realtime grouped Laravel log diagnostics.</p>
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                            <select
                                className="rounded-md border border-slate-500/40 bg-slate-900/70 px-3 py-2 text-sm"
                                value={level}
                                onChange={(event) => setLevel(event.target.value)}
                            >
                                <option value="all">All levels</option>
                                <option value="emergency">Emergency</option>
                                <option value="alert">Alert</option>
                                <option value="critical">Critical</option>
                                <option value="error">Error</option>
                                <option value="warning">Warning</option>
                                <option value="notice">Notice</option>
                                <option value="info">Info</option>
                                <option value="debug">Debug</option>
                            </select>

                            <select
                                className="rounded-md border border-slate-500/40 bg-slate-900/70 px-3 py-2 text-sm"
                                value={hours}
                                onChange={(event) => setHours(event.target.value)}
                            >
                                <option value="1">Last hour</option>
                                <option value="6">Last 6 hours</option>
                                <option value="24">Last 24 hours</option>
                                <option value="72">Last 3 days</option>
                                <option value="168">Last 7 days</option>
                            </select>

                            <input
                                className="rounded-md border border-slate-500/40 bg-slate-900/70 px-3 py-2 text-sm placeholder:text-slate-400"
                                placeholder="Search message or exception"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />

                            <select
                                className="rounded-md border border-slate-500/40 bg-slate-900/70 px-3 py-2 text-sm"
                                value={selectedFile}
                                onChange={(event) => setSelectedFile(event.target.value)}
                            >
                                <option value="all">All files</option>
                                {availableFiles.map((file) => (
                                    <option key={file} value={file}>
                                        {file}
                                    </option>
                                ))}
                            </select>

                            <label className="inline-flex items-center gap-2 rounded-md border border-slate-500/40 bg-slate-900/70 px-3 py-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={liveEnabled}
                                    onChange={(event) => setLiveEnabled(event.target.checked)}
                                />
                                Live
                            </label>

                            <label className="inline-flex items-center gap-2 rounded-md border border-slate-500/40 bg-slate-900/70 px-3 py-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={notificationsEnabled}
                                    onChange={(event) => setNotificationsEnabled(event.target.checked)}
                                />
                                Alerts
                            </label>

                            {notificationPermission !== 'granted' ? (
                                <button
                                    type="button"
                                    className="rounded-md border border-slate-500/40 bg-slate-900/70 px-3 py-2 text-sm"
                                    onClick={async () => {
                                        if (typeof window === 'undefined' || !('Notification' in window)) {
                                            return;
                                        }

                                        const permission = await window.Notification.requestPermission();
                                        setNotificationPermission(permission);
                                    }}
                                >
                                    {notificationPermission === 'unsupported' ? 'Notifications unavailable' : 'Enable notifications'}
                                </button>
                            ) : null}
                        </div>
                    </div>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="rounded-lg border border-slate-500/30 bg-slate-900/50 p-3">
                            <p className="text-xs uppercase tracking-wide text-slate-300">Issue Groups</p>
                            <p className="mt-1 text-2xl font-semibold">{stats.totalGroups}</p>
                        </div>
                        <div className="rounded-lg border border-slate-500/30 bg-slate-900/50 p-3">
                            <p className="text-xs uppercase tracking-wide text-slate-300">Occurrences</p>
                            <p className="mt-1 text-2xl font-semibold">{stats.totalOccurrences}</p>
                        </div>
                        <div className="rounded-lg border border-slate-500/30 bg-slate-900/50 p-3">
                            <p className="text-xs uppercase tracking-wide text-slate-300">Errors & Above</p>
                            <p className="mt-1 text-2xl font-semibold">{stats.criticalGroups}</p>
                        </div>
                        <div className="rounded-lg border border-slate-500/30 bg-slate-900/50 p-3">
                            <p className="text-xs uppercase tracking-wide text-slate-300">Last Activity</p>
                            <p className="mt-1 text-lg font-semibold">{stats.latestSeenAt ? formatRelativeTime(stats.latestSeenAt) : 'N/A'}</p>
                        </div>
                    </div>
                </div>

                <div className="grid min-h-0 flex-1 gap-4 lg:grid-cols-2">
                    <section className="min-h-0 overflow-hidden rounded-xl border border-sidebar-border/70 bg-card">
                        <div className="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3">
                            <h2 className="font-semibold">Grouped issues</h2>
                            <span className="text-xs text-muted-foreground">{loading ? 'Refreshing...' : `${groupedIssues.length} groups`}</span>
                        </div>

                        <div className="h-full overflow-auto">
                            {groupedIssues.length === 0 ? (
                                <p className="p-4 text-sm text-muted-foreground">No log groups match the current filters.</p>
                            ) : (
                                <ul>
                                    {groupedIssues.map((issue) => (
                                        <li key={issue.fingerprint}>
                                            <button
                                                type="button"
                                                className={`w-full border-b border-l-4 border-sidebar-border/40 px-3 py-1.5 text-left transition hover:bg-muted/30 ${
                                                    LEVEL_ACCENT_CLASS[issue.level] ?? 'border-l-zinc-400'
                                                } ${
                                                    selectedIssue?.fingerprint === issue.fingerprint ? 'bg-muted/40' : ''
                                                }`}
                                                onClick={() => setSelectedFingerprint(issue.fingerprint)}
                                            >
                                                <div className="flex items-center justify-between gap-3">
                                                    <p className="truncate text-xs font-semibold">{issue.exception_class ?? issue.level.toUpperCase()}</p>
                                                    <span className={`rounded px-1.5 py-0.5 text-[10px] ${LEVEL_BADGE_CLASS[issue.level] ?? LEVEL_BADGE_CLASS.debug}`}>
                                                        {formatLevelLabel(issue.level)}
                                                    </span>
                                                </div>
                                                <p className="mt-0.5 line-clamp-1 text-xs text-muted-foreground">{issue.message}</p>
                                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                                    {issue.occurrences} occurrences • last seen {formatRelativeTime(issue.last_seen_at)}
                                                </p>
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    {issue.source_files.map((sourceFile) => (
                                                        <span
                                                            key={`${issue.fingerprint}-${sourceFile}`}
                                                            className="rounded border border-sidebar-border/70 bg-background px-1 py-0 text-[10px] text-muted-foreground"
                                                        >
                                                            {sourceFile}
                                                        </span>
                                                    ))}
                                                </div>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>

                    <section className="min-h-0 overflow-hidden rounded-xl border border-sidebar-border/70 bg-card">
                        <div className="border-b border-sidebar-border/70 px-4 py-3">
                            <h2 className="font-semibold">Issue detail</h2>
                        </div>

                        <div className="h-full overflow-auto p-4">
                            {selectedIssue === null ? (
                                <p className="text-sm text-muted-foreground">Select a group to inspect stack trace details.</p>
                            ) : (
                                <div className="space-y-4">
                                    <div>
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-lg font-semibold">{selectedIssue.exception_class ?? selectedIssue.level.toUpperCase()}</p>
                                            <span
                                                className={`rounded px-2 py-0.5 text-xs ${LEVEL_BADGE_CLASS[selectedIssue.level] ?? LEVEL_BADGE_CLASS.debug}`}
                                            >
                                                {formatLevelLabel(selectedIssue.level)}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">{selectedIssue.message}</p>
                                    </div>

                                    <div className="grid gap-2 rounded-lg border border-sidebar-border/70 bg-background/40 p-3 text-sm text-muted-foreground sm:grid-cols-2">
                                        <p>Occurrences: {selectedIssue.occurrences}</p>
                                        <p>First seen: {formatRelativeTime(selectedIssue.first_seen_at)}</p>
                                        <p>Last seen: {formatRelativeTime(selectedIssue.last_seen_at)}</p>
                                        <p>First app frame: {selectedIssue.first_app_frame ?? 'Not detected'}</p>
                                        <p>Source file(s): {selectedIssue.source_files.join(', ')}</p>
                                    </div>

                                    {selectedIssue.route_context ? (
                                        <div className="rounded-lg border border-sky-400/30 bg-sky-500/10 p-3 text-xs text-sky-100">
                                            <p className="font-semibold uppercase tracking-wide">Route Insight</p>
                                            <p className="mt-1">Matched by: {selectedIssue.route_context.matched_by}</p>
                                            <p>Route: {selectedIssue.route_context.name ?? '(unnamed)'} [{selectedIssue.route_context.uri}]</p>
                                            <p>Methods: {selectedIssue.route_context.methods.join(', ')}</p>
                                            <p>Action: {selectedIssue.route_context.action}</p>
                                            <p className="truncate">Middleware: {selectedIssue.route_context.middleware.join(', ') || 'none'}</p>
                                        </div>
                                    ) : null}

                                    {selectedIssue.blame ? (
                                        <div className="rounded-lg border border-emerald-400/30 bg-emerald-500/10 p-3 text-xs text-emerald-100">
                                            <p className="font-semibold uppercase tracking-wide">Blame Target</p>
                                            <p className="mt-1">Component: {selectedIssue.blame.component}</p>
                                            <p>Class: {selectedIssue.blame.class ?? 'N/A'}</p>
                                            <p>Method: {selectedIssue.blame.method ?? 'N/A'}</p>
                                            <p>
                                                File: {selectedIssue.blame.file ?? 'N/A'}
                                                {selectedIssue.blame.line ? `:${selectedIssue.blame.line}` : ''}
                                            </p>
                                        </div>
                                    ) : null}

                                    <div>
                                        <h3 className="mb-2 text-sm font-semibold">Stack trace</h3>
                                        <div className="rounded-lg border border-sidebar-border/70 bg-background/40 p-3">
                                            {selectedIssue.trace.length === 0 ? (
                                                <p className="text-xs text-muted-foreground">No stack trace lines found in the parsed log entry.</p>
                                            ) : (
                                                <div className="space-y-3">
                                                    <div>
                                                        <p className="mb-2 text-xs font-medium uppercase tracking-wide text-emerald-300">
                                                            App Frames ({selectedIssueFrames.appFrames.length})
                                                        </p>
                                                        {selectedIssueFrames.appFrames.length === 0 ? (
                                                            <p className="text-xs text-muted-foreground">No application frames detected in this trace.</p>
                                                        ) : (
                                                            <ol className="space-y-2">
                                                                {selectedIssueFrames.appFrames.slice(0, 12).map((frame, index) => (
                                                                    <li
                                                                        key={`${selectedIssue.fingerprint}-app-${index}`}
                                                                        className="rounded border border-emerald-400/40 bg-emerald-500/10 px-2 py-1 font-mono text-xs text-emerald-100"
                                                                    >
                                                                        <p>{frame.raw}</p>
                                                                        {frame.class && frame.method ? (
                                                                            <p className="mt-1 text-[10px] text-emerald-200/90">
                                                                                {frame.class}@{frame.method} • {frame.component}
                                                                            </p>
                                                                        ) : null}
                                                                    </li>
                                                                ))}
                                                            </ol>
                                                        )}
                                                    </div>

                                                    {selectedIssueFrames.secondaryFrames.length > 0 ? (
                                                        <div>
                                                            <button
                                                                type="button"
                                                                className="mb-2 rounded border border-sidebar-border/70 px-2 py-1 text-xs text-muted-foreground hover:bg-muted/40"
                                                                onClick={() => setShowSecondaryFrames((current) => !current)}
                                                            >
                                                                {showSecondaryFrames
                                                                    ? `Hide vendor/core frames (${selectedIssueFrames.secondaryFrames.length})`
                                                                    : `Show vendor/core frames (${selectedIssueFrames.secondaryFrames.length})`}
                                                            </button>

                                                            {showSecondaryFrames ? (
                                                                <ol className="space-y-2">
                                                                    {selectedIssueFrames.secondaryFrames.slice(0, 20).map((frame, index) => {
                                                                        const frameType = frame.frame_type;
                                                                        const frameClass =
                                                                            frameType === 'vendor'
                                                                                ? 'border-sky-400/30 bg-sky-500/10 text-sky-100/90'
                                                                                : frameType === 'core'
                                                                                  ? 'border-violet-400/30 bg-violet-500/10 text-violet-100/90'
                                                                                  : 'border-sidebar-border/60 text-muted-foreground';

                                                                        return (
                                                                            <li
                                                                                key={`${selectedIssue.fingerprint}-secondary-${index}`}
                                                                                className={`rounded border px-2 py-1 font-mono text-xs ${frameClass}`}
                                                                            >
                                                                                <p>{frame.raw}</p>
                                                                                {frame.class && frame.method ? (
                                                                                    <p className="mt-1 text-[10px] opacity-80">
                                                                                        {frame.class}@{frame.method} • {frame.component}
                                                                                    </p>
                                                                                ) : null}
                                                                            </li>
                                                                        );
                                                                    })}
                                                                </ol>
                                                            ) : null}
                                                        </div>
                                                    ) : null}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

LogPulseIndex.layout = {
    breadcrumbs: [
        {
            title: 'Logcutter',
            href: logpulseIndex(),
        },
    ],
};
