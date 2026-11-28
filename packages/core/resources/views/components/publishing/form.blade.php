@props(['form'])
@php
    use Codeart\Core\Publishing\PublishStatus;

    /** @var array<string, mixed> $form */
    $module = $form['module'];
    $model = $form['model'];
    $isNew = $form['isNew'];
    $locales = $form['locales'];
    $title = $isNew ? __($module.'::module.new') : ($model->displayTitle() ?: __($module.'::module.untitled'));
    $workflow = $form['workflow'];
    $status = $isNew ? PublishStatus::Draft : $model->status;
    // Field errors come back as `translations.sr.title`; the field components look them up by the HTML name `translations[sr][title]`.
    $bag = new \Illuminate\Support\MessageBag(collect($errors->getMessages())->mapWithKeys(function ($messages, $key) {
        $parts = explode('.', (string) $key);

        return [array_shift($parts).implode('', array_map(fn ($p) => '['.$p.']', $parts)) => $messages];
    })->all());
    $label = fn (string $field) => \Illuminate\Support\Facades\Lang::has($module.'::module.fields.'.$field) ? __($module.'::module.fields.'.$field) : \Illuminate\Support\Str::headline($field);
@endphp
<x-codeart::admin.layout :title="$title">
    <x-codeart::admin.page-header :title="$title" :breadcrumbs="[['label' => __($module.'::module.title'), 'url' => route($module.'.index')], ['label' => $isNew ? __($module.'::module.new') : $title]]" />

    <form method="POST" action="{{ $isNew ? route($module.'.store') : route($module.'.update', $model->getKey()) }}" data-publishing-form
          x-data="{ locale: @js($form['defaultLocale']) }" class="grid gap-6 lg:grid-cols-3">
        @csrf
        @unless ($isNew) @method('PUT') @endunless
        <input type="hidden" name="preview_locale" x-bind:value="locale" value="{{ $form['defaultLocale'] }}">

        <div class="space-y-4 lg:col-span-2">
            @if (count($locales) > 1)
                {{-- Without JS every language is shown one below the other; Alpine turns them into tabs. --}}
                <div role="tablist" class="hidden flex-wrap gap-1" x-bind:class="{ 'hidden': false, 'flex': true }" aria-label="{{ __('codeart::publishing.languages') }}" data-locale-tabs>
                    @foreach ($locales as $code => $name)
                        <button type="button" role="tab" data-locale-tab="{{ $code }}" x-on:click="locale = @js($code)" x-bind:aria-selected="locale === @js($code)"
                                class="rounded-control border border-border px-4 py-2 text-sm font-semibold text-text-muted aria-selected:bg-primary aria-selected:text-on-primary focus-visible:outline-2 focus-visible:outline-focus">{{ $name }}</button>
                    @endforeach
                </div>
            @endif

            @foreach ($locales as $code => $name)
                <x-codeart::admin.card :title="$name" icon="languages" x-show="locale === @js($code)" data-locale-panel="{{ $code }}">
                    <div class="space-y-4">
                        @foreach ($form['fields'] as $field => $type)
                            @php($value = old('translations.'.$code.'.'.$field, $form['content']['translations'][$code][$field] ?? ''))
                            @php($required = $code === $form['defaultLocale'] && $loop->first)
                            @php($name = 'translations['.$code.']['.$field.']')
                            @if ($type === 'textarea')
                                @if ($required)
                                    <x-codeart::textarea :name="$name" :id="'f-'.$code.'-'.$field" :label="$label($field)" :value="$value" :bag="$bag" rows="8" required />
                                @else
                                    <x-codeart::textarea :name="$name" :id="'f-'.$code.'-'.$field" :label="$label($field)" :value="$value" :bag="$bag" rows="8" />
                                @endif
                            @elseif ($required)
                                <x-codeart::field :name="$name" :id="'f-'.$code.'-'.$field" :label="$label($field)" :value="$value" :bag="$bag" maxlength="255" required />
                            @else
                                <x-codeart::field :name="$name" :id="'f-'.$code.'-'.$field" :label="$label($field)" :value="$value" :bag="$bag" maxlength="255" />
                            @endif
                        @endforeach
                    </div>
                </x-codeart::admin.card>
            @endforeach
        </div>

        <div class="space-y-6">
            <x-codeart::admin.card :title="__('codeart::publishing.status_label')" icon="send" data-status-card>
                <div class="space-y-4">
                    @if ($workflow)
                    <p class="flex items-center gap-2 text-sm text-text-muted">
                        <x-codeart::badge :tone="$status->tone()" data-current-status>{{ $status->label() }}</x-codeart::badge>
                        @if ($model->hasDraft() && $status === PublishStatus::Published) <span data-has-draft>{{ __('codeart::publishing.has_draft') }}</span> @endif
                    </p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <x-codeart::button type="submit" name="intent" value="draft" size="md" :full="false" :variant="$workflow ? 'secondary' : 'primary'" icon-left="save" data-save-draft>{{ $workflow ? __('codeart::publishing.save_draft') : __('codeart::publishing.save') }}</x-codeart::button>
                        @if ($form['canPreview'])
                            <x-codeart::button type="submit" name="intent" value="preview" formtarget="_blank" size="md" :full="false" variant="ghost" icon-left="external-link" data-preview>{{ __('codeart::publishing.preview') }}</x-codeart::button>
                            <x-codeart::button type="submit" name="intent" value="frame" formtarget="_blank" size="md" :full="false" variant="ghost" icon-left="monitor-smartphone" data-preview-frame>{{ __('codeart::publishing.preview_frame') }}</x-codeart::button>
                        @endif
                    </div>

                    @if ($form['canPublish'])
                        <div class="space-y-3 border-t border-border pt-4" x-data="{ status: @js(old('status', $status->value)) }" data-publish-controls>
                            <x-codeart::select name="status" :label="__('codeart::publishing.status_label')" :value="old('status', $status->value)" x-model="status"
                                :options="['draft' => __('codeart::publishing.status.draft'), 'published' => __('codeart::publishing.status.published'), 'scheduled' => __('codeart::publishing.status.scheduled')]" />
                            <div x-show="status === 'scheduled'" data-schedule>
                                <x-codeart::field name="scheduled_at" type="datetime-local" :label="__('codeart::publishing.scheduled_at')" :value="old('scheduled_at', $form['scheduledAt'])" :hint="__('codeart::publishing.scheduled_hint')" />
                            </div>
                            <x-codeart::button type="submit" name="intent" value="status" size="md" :full="false" icon-left="upload" data-apply-status>{{ __('codeart::publishing.apply_status') }}</x-codeart::button>
                        </div>
                    @endif
                </div>
            </x-codeart::admin.card>

            @if ($workflow && ! $isNew)
                <x-codeart::admin.card :title="__('codeart::publishing.history')" icon="history" data-revisions>
                    @forelse ($form['revisions'] as $revision)
                        <div class="flex items-center justify-between gap-3 border-b border-border py-2 text-sm last:border-0" data-revision="{{ $revision->id }}">
                            <div class="min-w-0">
                                <p class="font-semibold text-text">{{ __('codeart::publishing.kind.'.$revision->kind) }}@if ($revision->id === $model->draft_revision_id) · {{ __('codeart::publishing.current_draft') }} @endif</p>
                                <p class="text-text-muted">{{ \Codeart\Core\Support\DisplayTime::current()->format($revision->created_at) }}</p>
                            </div>
                            @if ($revision->id !== $model->draft_revision_id)
                                <button type="submit" form="restore-{{ $revision->id }}" class="shrink-0 text-sm font-semibold text-text underline focus-visible:outline-2 focus-visible:outline-focus" data-restore>{{ __('codeart::publishing.restore') }}</button>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-text-muted">{{ __('codeart::publishing.no_history') }}</p>
                    @endforelse
                </x-codeart::admin.card>

                @if ($form['canDelete'])
                    <x-codeart::admin.card :title="__('codeart::publishing.danger')" icon="shield">
                        <x-codeart::confirm :action="route($module.'.destroy', $model->getKey())" method="DELETE" :button="__('codeart::table.delete')" :text="__('codeart::publishing.confirm_delete')" />
                    </x-codeart::admin.card>
                @endif
            @endif
            @if (! $workflow && ! $isNew && $form['canDelete'])
                <x-codeart::admin.card :title="__('codeart::publishing.danger')" icon="shield">
                    <x-codeart::confirm :action="route($module.'.destroy', $model->getKey())" method="DELETE" :button="__('codeart::table.delete')" :text="__('codeart::publishing.confirm_delete')" />
                </x-codeart::admin.card>
            @endif
        </div>
    </form>

    {{-- Separate forms (a form may not nest in another): one per revision, tied to its button with the form attribute. --}}
    @unless ($isNew)
        @foreach ($form['revisions'] as $revision)
            @if ($revision->id !== $model->draft_revision_id)
                <form id="restore-{{ $revision->id }}" method="POST" action="{{ route($module.'.restore', [$model->getKey(), $revision->id]) }}">@csrf</form>
            @endif
        @endforeach
    @endunless
</x-codeart::admin.layout>
