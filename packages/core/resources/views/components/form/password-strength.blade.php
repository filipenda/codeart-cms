@props(['for', 'rules' => [], 'breached' => false])
@php
    // $rules: list of ['key' => min_length|mixed_case|number|symbol, 'label' => text, 'value' => ?int] from PasswordRules::requirements().
    $js = array_map(fn (array $r): array => ['key' => $r['key'], 'value' => $r['value'] ?? null], $rules);
@endphp
{{-- Strength meter (5 segments + label) and the requirements checklist. Without JS only the static list shows (neutral
     bullets, no meter); with Alpine the ticks and the meter follow the typed password. The breached-password check is
     server-side only and only mentioned here. --}}
<div x-data="codeartPasswordStrength(@js($js), @js($for))" class="space-y-3" data-password-strength>
    <div hidden x-bind:hidden="false" data-strength-meter>
        <div class="flex gap-1.5" aria-hidden="true">
            @foreach (range(1, 5) as $i)
                <span data-segment class="h-1.5 flex-1 rounded-full bg-surface-sunken"
                    x-bind:class="score >= {{ $i }} ? (level() === 'weak' ? 'bg-danger' : (level() === 'medium' ? 'bg-warning' : 'bg-success')) : 'bg-surface-sunken'"></span>
            @endforeach
        </div>
        <p class="mt-1.5 text-sm font-medium text-text-muted" aria-live="polite" data-strength-label
            x-text="score === 0 ? '' : (level() === 'weak' ? @js(__('codeart::profile.pw_weak')) : (level() === 'medium' ? @js(__('codeart::profile.pw_medium')) : @js(__('codeart::profile.pw_strong'))))"></p>
    </div>
    @if ($rules !== [])
        <div>
            <p class="mb-1.5 text-sm font-medium text-text" id="{{ $for }}-requirements">{{ __('codeart::profile.pw_requirements') }}</p>
            <ul class="space-y-1 text-sm" aria-labelledby="{{ $for }}-requirements" data-requirements>
                @foreach ($rules as $rule)
                    <li data-rule="{{ $rule['key'] }}" class="flex items-center gap-2 text-text-muted" x-bind:class="met['{{ $rule['key'] }}'] ? 'text-success-text' : 'text-text-muted'">
                        <span class="inline-flex size-4 shrink-0 items-center justify-center" aria-hidden="true">
                            <span x-show="!met['{{ $rule['key'] }}']" class="size-1.5 rounded-full bg-text-muted"></span>
                            <span x-show="met['{{ $rule['key'] }}']" x-cloak><x-codeart::icon name="check" class="size-4" /></span>
                        </span>
                        <span>{{ $rule['label'] }}</span>
                        <span class="sr-only" x-text="met['{{ $rule['key'] }}'] ? @js(__('codeart::profile.pw_met')) : ''"></span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
    @if ($breached)
        <p class="flex items-start gap-2 text-sm text-text-muted" data-breached-hint><x-codeart::icon name="shield" class="mt-0.5 size-4 shrink-0" /><span>{{ __('codeart::profile.pw_breached_hint') }}</span></p>
    @endif
</div>
