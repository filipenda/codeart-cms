@props(['title', 'description' => null, 'breadcrumbs' => []])
{{-- Breadcrumb, H1, optional description, actions (named slot) aligned to the end. Breadcrumbs: [['label' => 'Pages', 'url' => '...'], ['label' => 'Edit']] --}}
<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4') }}>
    <div class="min-w-0">
        @if ($breadcrumbs !== [])
            <nav aria-label="{{ __('codeart::admin.breadcrumb') }}" class="mb-2">
                <ol class="flex flex-wrap items-center gap-1.5 text-sm text-text-muted">
                    @foreach ($breadcrumbs as $crumb)
                        <li class="flex items-center gap-1.5">
                            @if (! $loop->first) <x-codeart::icon name="chevron-right" class="size-3.5 rtl:rotate-180" /> @endif
                            @if (! $loop->last && ! empty($crumb['url']))
                                <a href="{{ $crumb['url'] }}" class="rounded-sm hover:text-text hover:underline focus-visible:outline-2 focus-visible:outline-focus">{{ $crumb['label'] }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" @endif class="{{ $loop->last ? 'text-text' : '' }}">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <h1 class="truncate text-2xl font-bold text-text sm:text-3xl">{{ $title }}</h1>
        @if ($description) <p class="mt-1 text-text-muted">{{ $description }}</p> @endif
    </div>
    @if (isset($actions) && $actions->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
