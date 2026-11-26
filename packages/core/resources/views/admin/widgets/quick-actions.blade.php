<x-codeart::admin.card :title="__($widget->label)" :icon="$widget->icon" class="h-full">
    <ul class="space-y-1">
        @foreach ($data['actions'] as $action)
            <li>
                <a href="{{ route($action->route) }}" class="flex items-center gap-3 rounded-control px-3 py-2.5 text-sm font-medium text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">
                    <x-codeart::icon :name="$action->icon" class="size-4 text-text-muted" />
                    <span class="truncate">{{ __($action->label) }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</x-codeart::admin.card>
