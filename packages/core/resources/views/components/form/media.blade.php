@props(['name', 'label', 'value' => [], 'multiple' => false, 'type' => 'image', 'hint' => null, 'bag' => null, 'id' => null])
@php
    // `value` = media id (single) or list of ids (multiple), already as the form sends them; the field stores IDS only.
    $id ??= 'media-'.preg_replace('/[^a-z0-9]+/i', '-', $name);
    $ids = array_values(array_filter(array_map('intval', is_array($value) ? $value : [$value])));
    $found = $ids === [] ? collect() : \Codeart\Core\Media\Media::query()->with('translations')->whereIn('id', $ids)->get()->keyBy('id');
    $items = collect($ids)->map(fn ($i) => $found->get($i)?->toPicker())->filter()->values()->all();
    $messages = $bag ?? ($errors ?? new \Illuminate\Support\MessageBag);
    $error = $messages->first($name) ?: $messages->first($name.'.0');
    $field = $multiple ? $name.'[]' : $name;
@endphp
<div {{ $attributes }} x-data="codeartMediaField(@js(['items' => $items, 'multiple' => (bool) $multiple, 'type' => $type]))" x-on:codeart:media-selected.window="onSelected($event)" data-media-field="{{ $name }}">
    <span id="{{ $id }}-label" class="mb-1.5 block text-sm font-medium text-text">{{ $label }}</span>
    <ul class="mb-2 flex flex-wrap gap-3" aria-labelledby="{{ $id }}-label">
        <template x-for="(item, index) in items" :key="item.id">
            <li class="w-32 overflow-hidden rounded-card border border-border bg-surface">
                <input type="hidden" name="{{ $field }}" x-bind:value="item.id">
                <span class="flex aspect-square items-center justify-center bg-surface-alt">
                    <img x-show="item.thumb" x-bind:src="item.thumb" x-bind:alt="item.alt" class="size-full object-cover">
                    <x-codeart::icon x-show="!item.thumb" name="file-text" class="size-8 text-text-muted" />
                </span>
                <span class="block truncate px-2 pt-1 text-xs" x-text="item.name"></span>
                <span class="flex justify-between px-1 pb-1">
                    <button type="button" class="rounded-control p-1 text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-focus" x-on:click="remove(index)" aria-label="{{ __('codeart::media.field.remove') }}"><x-codeart::icon name="trash-2" class="size-4" /></button>
                    @if ($multiple)
                        <span>
                            <button type="button" class="rounded-control p-1 text-text-muted hover:text-text disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-focus" x-bind:disabled="index === 0" x-on:click="move(index, -1)" aria-label="{{ __('codeart::media.field.earlier') }}"><x-codeart::icon name="chevron-left" class="size-4 rtl:rotate-180" /></button>
                            <button type="button" class="rounded-control p-1 text-text-muted hover:text-text disabled:opacity-40 focus-visible:outline-2 focus-visible:outline-focus" x-bind:disabled="index === items.length - 1" x-on:click="move(index, 1)" aria-label="{{ __('codeart::media.field.later') }}"><x-codeart::icon name="chevron-right" class="size-4 rtl:rotate-180" /></button>
                        </span>
                    @endif
                </span>
            </li>
        </template>
    </ul>
    {{-- Nothing chosen: an empty value is still sent, so clearing the field works. --}}
    <input type="hidden" name="{{ $field }}" value="" x-bind:disabled="items.length > 0">
    <x-codeart::button type="button" variant="secondary" size="md" :full="false" icon-left="images" x-on:click="choose()" data-media-choose>{{ $multiple ? __('codeart::media.field.add') : __('codeart::media.field.choose') }}</x-codeart::button>
    @if ($hint) <p class="mt-1 text-sm text-text-muted">{{ $hint }}</p> @endif
    @if ($error) <p class="mt-1 text-sm text-danger" role="alert">{{ $error }}</p> @endif
    <x-codeart::media.picker />
</div>
