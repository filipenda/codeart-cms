@props(['filter', 'value' => null])
@php
    // One filter control. `filter[key]` (and `filter[key][from|to]` for ranges) is what TableState reads. Labels are the filter's own.
    $field = 'filter['.$filter->key.']';
    $input = 'h-11 w-full rounded-control border border-border bg-surface px-3 text-sm text-text placeholder:text-text-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus';
    $range = is_array($value) ? $value : [];
@endphp
@switch($filter->type)
    @case('select')
        <x-codeart::table.select :name="$field" :label="$filter->label" :options="$filter->options" :value="is_string($value) ? $value : ''" :placeholder="$filter->label" data-filter-auto />
        @break
    @case('boolean')
        <x-codeart::table.select :name="$field" :label="$filter->label" :options="['1' => __('codeart::table.yes'), '0' => __('codeart::table.no')]" :value="is_bool($value) ? ($value ? '1' : '0') : ''" :placeholder="$filter->label" data-filter-auto />
        @break
    @case('date-range')
        <fieldset class="flex items-center gap-2" data-filter-range>
            <legend class="sr-only">{{ $filter->label }}</legend>
            <input type="date" name="{{ $field }}[from]" value="{{ $range['from'] ?? '' }}" aria-label="{{ $filter->label }}: {{ __('codeart::table.from') }}" class="{{ $input }}" data-filter-auto>
            <span class="text-text-muted" aria-hidden="true">–</span>
            <input type="date" name="{{ $field }}[to]" value="{{ $range['to'] ?? '' }}" aria-label="{{ $filter->label }}: {{ __('codeart::table.to') }}" class="{{ $input }}" data-filter-auto>
        </fieldset>
        @break
    @case('number-range')
        <fieldset class="flex items-center gap-2" data-filter-range>
            <legend class="sr-only">{{ $filter->label }}</legend>
            <input type="number" step="any" name="{{ $field }}[from]" value="{{ $range['from'] ?? '' }}" placeholder="{{ $filter->label }}: {{ __('codeart::table.from') }}" aria-label="{{ $filter->label }}: {{ __('codeart::table.from') }}" class="{{ $input }}" data-filter-auto>
            <span class="text-text-muted" aria-hidden="true">–</span>
            <input type="number" step="any" name="{{ $field }}[to]" value="{{ $range['to'] ?? '' }}" placeholder="{{ $filter->label }}: {{ __('codeart::table.to') }}" aria-label="{{ $filter->label }}: {{ __('codeart::table.to') }}" class="{{ $input }}" data-filter-auto>
        </fieldset>
        @break
    @default
        <input type="search" name="{{ $field }}" value="{{ is_string($value) ? $value : '' }}" placeholder="{{ $filter->label }}" aria-label="{{ $filter->label }}" class="{{ $input }}" data-filter-search>
@endswitch
