{{-- Developer page (local only). Every component in every state, in the current theme: switch light/dark in the user menu. English on purpose (not shipped UI). --}}
@php
    $variants = ['primary', 'secondary', 'ghost', 'danger'];
    $sizes = ['sm', 'md', 'lg'];
    $errorBag = new \Illuminate\Support\MessageBag(['demo' => ['This value is not valid.']]);
@endphp
<x-codeart::admin.layout title="UI components">
    <x-codeart::admin.page-header title="UI components" description="Every component in every state. Switch the theme in the user menu to review light and dark." />

    <div class="space-y-6" data-showcase>
        <x-codeart::admin.card title="Data table (the activity log)" icon="list">
            <p class="mb-4 text-sm text-text-muted">Read-only: search, sort, filter, page, export CSV. The bulk action only shows a toast. Select every row on a page to try "select all results".</p>
            <x-codeart::table :result="$tableResult" label="Activity log" />
        </x-codeart::admin.card>

        <x-codeart::admin.card title="Buttons" icon="bolt">
            <div class="space-y-4">
                @foreach ($sizes as $size)
                    <div class="flex flex-wrap items-center gap-3" data-row="button-{{ $size }}">
                        @foreach ($variants as $variant)
                            <x-codeart::button type="button" :variant="$variant" :size="$size" :full="false">{{ ucfirst($variant) }} {{ $size }}</x-codeart::button>
                        @endforeach
                    </div>
                @endforeach
                <div class="flex flex-wrap items-center gap-3">
                    <x-codeart::button type="button" size="md" :full="false" icon-left="plus">Icon left</x-codeart::button>
                    <x-codeart::button type="button" size="md" :full="false" icon="arrow-right">Icon right</x-codeart::button>
                    <x-codeart::button type="button" size="md" :full="false" :loading="true">Loading</x-codeart::button>
                    <x-codeart::button type="button" size="md" variant="secondary" :full="false" :disabled="true">Disabled</x-codeart::button>
                    <x-codeart::button href="#" size="md" variant="secondary" :full="false">As a link</x-codeart::button>
                    <x-codeart::button href="#" size="md" variant="secondary" :full="false" :disabled="true">Disabled link</x-codeart::button>
                </div>
            </div>
        </x-codeart::admin.card>

        <x-codeart::admin.card title="Fields" icon="pencil">
            <div class="grid gap-6 md:grid-cols-2">
                <x-codeart::field name="s_default" label="Default" placeholder="Type here" />
                <x-codeart::field name="s_hint" label="With hint" hint="Shown under the field." />
                <x-codeart::field name="s_required" label="Required" required />
                <x-codeart::field name="s_counter" label="With counter" maxlength="40" hint="At most 40 characters." />
                <x-codeart::field name="s_error" label="With error" :bag="$errorBag" value="wrong" />
                <x-codeart::field name="demo" label="With error and hint" hint="Hint stays." :bag="$errorBag" />
                <x-codeart::field name="s_prefix" label="Icon at the start" icon="search" />
                <x-codeart::field name="s_suffix" label="Icon at the end" icon-end="mail" type="email" />
                <x-codeart::field name="s_disabled" label="Disabled" value="Cannot edit" disabled />
                <x-codeart::field name="s_readonly" label="Read only" value="Read only value" readonly />
                <x-codeart::textarea name="t_default" label="Textarea" hint="Plain textarea." />
                <x-codeart::textarea name="t_counter" label="Textarea with counter" maxlength="120" value="Starts with some text" />
                <x-codeart::textarea name="demo" label="Textarea with error" :bag="$errorBag" id="t_error" />
                <x-codeart::textarea name="t_disabled" label="Textarea disabled" disabled />
                <x-codeart::select name="sel_default" label="Select" :options="['a' => 'Option A', 'b' => 'Option B']" placeholder="Choose…" hint="A styled native select." />
                <x-codeart::select name="demo" label="Select with error" id="sel_error" :bag="$errorBag" :options="['a' => 'Option A']" />
                <x-codeart::select name="sel_disabled" label="Select disabled" :options="['a' => 'Option A']" disabled />
            </div>
        </x-codeart::admin.card>

        <x-codeart::admin.card title="Checkbox, radio, switch" icon="check">
            <div class="grid gap-6 md:grid-cols-3">
                <div class="space-y-3">
                    <x-codeart::checkbox id="c1" name="c1" label="Checkbox" />
                    <x-codeart::checkbox id="c2" name="c2" label="Checked with hint" hint="A short explanation." :checked="true" />
                    <x-codeart::checkbox id="c3" name="demo" label="With error" :bag="$errorBag" />
                    <x-codeart::checkbox id="c4" label="Disabled" disabled />
                </div>
                <div class="space-y-3" role="radiogroup" aria-label="Radio demo">
                    <x-codeart::radio id="r1" name="r" value="1" label="Radio one" :checked="true" />
                    <x-codeart::radio id="r2" name="r" value="2" label="Radio two" hint="With hint" />
                    <x-codeart::radio id="r3" name="r" value="3" label="Radio disabled" disabled />
                </div>
                <div class="space-y-3">
                    <x-codeart::switch id="w1" name="w1" label="Switch off" />
                    <x-codeart::switch id="w2" name="w2" label="Switch on" :checked="true" hint="Takes effect at once." />
                    <x-codeart::switch id="w3" name="w3" label="Switch disabled" disabled />
                </div>
            </div>
        </x-codeart::admin.card>

        <x-codeart::admin.card title="Badges and alerts" icon="info">
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (['primary', 'success', 'warning', 'danger', 'neutral'] as $tone)
                        <x-codeart::badge :tone="$tone">{{ ucfirst($tone) }}</x-codeart::badge>
                    @endforeach
                    <x-codeart::badge :dot="false">No dot</x-codeart::badge>
                </div>
                <x-codeart::alert type="info" title="Info">Something to know.</x-codeart::alert>
                <x-codeart::alert type="success" :dismissible="true">Saved. This one can be dismissed.</x-codeart::alert>
                <x-codeart::alert type="warning" title="Warning" :messages="['First thing', 'Second thing']" />
                <x-codeart::alert type="danger" title="Danger" :dismissible="true">Something failed.</x-codeart::alert>
            </div>
        </x-codeart::admin.card>

        <x-codeart::admin.card title="Modal, confirm, dropdown, toast, empty state" icon="layout-list">
            <div class="flex flex-wrap items-start gap-4">
                <x-codeart::button type="button" size="md" variant="secondary" :full="false" data-modal-open="demo-modal">Open modal</x-codeart::button>
                <x-codeart::modal id="demo-modal">
                    <x-slot:title>Modal title</x-slot:title>
                    Body text. Esc, the close button and the backdrop all close it; focus returns to the button.
                    <x-slot:footer>
                        <x-codeart::button type="button" size="md" variant="secondary" :full="false" data-modal-close>Cancel</x-codeart::button>
                        <x-codeart::button type="button" size="md" :full="false" data-modal-close>Done</x-codeart::button>
                    </x-slot:footer>
                </x-codeart::modal>

                <x-codeart::confirm :action="route('codeart.ui-showcase')" title="Delete this item?" text="This cannot be undone." confirm="Yes, delete" icon="trash-2">Delete item</x-codeart::confirm>

                <x-codeart::dropdown label="Actions menu">
                    <x-slot:trigger>Dropdown <x-codeart::icon name="chevron-down" class="size-4" /></x-slot:trigger>
                    <button type="button" data-menu-item class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">First action</button>
                    <button type="button" data-menu-item class="flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-start text-sm text-text hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-focus">Second action</button>
                </x-codeart::dropdown>

                <div class="flex flex-wrap gap-2">
                    @foreach (['info', 'success', 'warning', 'danger'] as $type)
                        <x-codeart::button type="button" size="md" variant="secondary" :full="false" x-data x-on:click="$dispatch('codeart:toast', { message: 'A {{ $type }} toast', type: '{{ $type }}' })">Toast {{ $type }}</x-codeart::button>
                    @endforeach
                </div>
            </div>
            <x-codeart::empty-state class="mt-6" title="Nothing here yet" text="Items you add will show up in this list.">
                <x-codeart::button type="button" size="md" :full="false" icon-left="plus">Add the first one</x-codeart::button>
            </x-codeart::empty-state>
        </x-codeart::admin.card>

        <x-codeart::admin.card title="Form: validation errors and a flash toast" icon="shield-check">
            <form method="POST" action="{{ route('codeart.ui-showcase.submit') }}" class="grid gap-6 md:grid-cols-2" novalidate>
                @csrf
                <x-codeart::field name="name" label="Name" required maxlength="40" hint="Required, 40 characters at most." />
                <x-codeart::select name="plan" label="Plan" required :options="['free' => 'Free', 'pro' => 'Pro', 'team' => 'Team']" placeholder="Choose…" />
                <div class="md:col-span-2"><x-codeart::textarea name="bio" label="Bio" maxlength="80" hint="Optional, 80 characters at most." /></div>
                <div class="md:col-span-2"><x-codeart::checkbox id="terms" name="terms" value="1" label="I accept the terms" /></div>
                <div class="md:col-span-2"><x-codeart::button size="md" :full="false">Submit (fill it wrongly to see the errors)</x-codeart::button></div>
            </form>
        </x-codeart::admin.card>
    </div>
</x-codeart::admin.layout>
