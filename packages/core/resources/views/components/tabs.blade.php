@props(['tabs' => [], 'active' => null, 'label' => null])
@php
    // $tabs: [['key' => 'login', 'label' => 'Prijava', 'href' => url], ...]. One named slot per key holds its panel.
    // Without JS the tabs are plain links to the page of that tab; Alpine switches them in place.
    $active = $active ?? ($tabs[0]['key'] ?? null);
@endphp
<div x-data="{ tab: @js($active) }">
    @if (count($tabs) > 1)
        <div role="tablist" @if ($label) aria-label="{{ $label }}" @endif class="mb-8 flex border-b border-border">
            @foreach ($tabs as $item)
                <a role="tab" id="tab-{{ $item['key'] }}" href="{{ $item['href'] }}" aria-controls="panel-{{ $item['key'] }}"
                    aria-selected="{{ $item['key'] === $active ? 'true' : 'false' }}"
                    x-on:click.prevent="tab = @js($item['key'])" x-bind:aria-selected="tab === @js($item['key']) ? 'true' : 'false'"
                    class="-mb-px flex-1 border-b-[3px] border-transparent px-2 pb-3 text-center text-base font-medium text-text-muted hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus aria-selected:border-primary aria-selected:font-bold aria-selected:text-text">{{ $item['label'] }}</a>
            @endforeach
        </div>
    @endif
    @foreach ($tabs as $item)
        <div id="panel-{{ $item['key'] }}" @if (count($tabs) > 1) role="tabpanel" aria-labelledby="tab-{{ $item['key'] }}" @endif
            @if ($item['key'] !== $active) hidden @endif x-bind:hidden="tab !== @js($item['key'])">
            {{ ${$item['key']} }}
        </div>
    @endforeach
</div>
