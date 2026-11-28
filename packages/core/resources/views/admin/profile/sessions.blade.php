<x-codeart::admin.profile-shell tab="sessions" :me="$me" :role="$role" :avatar-url="$avatarUrl">
    <x-codeart::admin.card :title="__('codeart::profile.ses_title')" :description="__('codeart::profile.ses_desc')" icon="monitor" data-sessions-card>
        <ul class="divide-y divide-border" data-session-list>
            @foreach ($sessions as $s)
                @php
                    $ua = $s['ua'];
                    $label = $ua['known'] ? trim(($ua['browser'] ?? '').($ua['browser'] && $ua['os'] ? ' · ' : '').($ua['os'] ?? '')) : __('codeart::profile.ses_unknown');
                    $icon = ['desktop' => 'monitor', 'laptop' => 'laptop', 'phone' => 'smartphone', 'tablet' => 'tablet'][$ua['device']];
                @endphp
                <li class="flex flex-wrap items-center gap-4 py-4 first:pt-0 last:pb-0" data-session @if ($s['current']) data-session-current aria-current="true" @endif data-device="{{ $ua['device'] }}">
                    <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-control bg-surface-alt text-text-muted" aria-hidden="true"><x-codeart::icon :name="$icon" class="size-5" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium text-text">
                            <span data-session-label>{{ $label }}</span>
                            @if ($s['current'])
                                <span class="inline-flex items-center gap-1.5 text-sm font-medium text-success-text" data-current-marker><span class="size-2 rounded-full bg-success" aria-hidden="true"></span>{{ __('codeart::profile.ses_current') }}</span>
                            @endif
                        </p>
                        <p class="text-sm text-text-muted">
                            @if (app(\Codeart\Core\Privacy\IpPrivacy::class)->enabled())<span data-session-ip>{{ $s['ip_address'] ?: '—' }}</span> · @endif<time datetime="{{ $s['last_active']->toIso8601String() }}" title="{{ $s['exact'] }}" data-session-time>{{ $s['relative'] }}</time>
                        </p>
                    </div>
                    @unless ($s['current'])
                        <x-codeart::confirm :action="route('admin.profile.sessions.destroy', $s['handle'])" method="DELETE" :title="__('codeart::profile.ses_end_title')" :text="__('codeart::profile.ses_end_text')" :confirm="__('codeart::profile.ses_end')" :button="__('codeart::profile.ses_end')" variant="secondary" size="sm" data-end-session />
                    @endunless
                </li>
            @endforeach
        </ul>

        @if ($others > 0)
            <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-control border border-border bg-surface-alt p-4" data-end-others>
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <x-codeart::icon name="shield" class="mt-0.5 size-5 shrink-0 text-text-muted" />
                    <div class="min-w-0">
                        <p class="font-medium text-text">{{ __('codeart::profile.ses_all') }}</p>
                        <p class="text-sm text-text-muted">{{ __('codeart::profile.ses_tip') }}</p>
                    </div>
                </div>
                <x-codeart::confirm :action="route('admin.profile.sessions.destroy-others')" method="DELETE" :title="__('codeart::profile.ses_all_title')" :text="__('codeart::profile.ses_all_text')" :confirm="__('codeart::profile.ses_all')" :button="__('codeart::profile.ses_all')" variant="danger" size="md" icon="log-out" />
            </div>
        @endif
    </x-codeart::admin.card>
</x-codeart::admin.profile-shell>
