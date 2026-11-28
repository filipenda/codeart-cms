<?php

namespace Codeart\Core\Publishing\Http;

use Carbon\CarbonImmutable;
use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Editor\HtmlSanitizer;
use Codeart\Core\Media\Media;
use Codeart\Core\Publishing\Actions\PublishContent;
use Codeart\Core\Publishing\Actions\RestoreRevision;
use Codeart\Core\Publishing\Actions\SaveDraft;
use Codeart\Core\Publishing\Actions\UnpublishContent;
use Codeart\Core\Publishing\Preview;
use Codeart\Core\Publishing\PreviewManager;
use Codeart\Core\Publishing\PublishableModel;
use Codeart\Core\Publishing\PublishStatus;
use Codeart\Core\Publishing\Revision;
use Codeart\Core\Support\DisplayTime;
use Codeart\Core\Tables\TableRegistry;
use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Thin base for the controller of a generated module. Every action: authorize (policy -> permission), validate,
 * delegate to an Action class, redirect. A module controller only names the model, the module and the field rules.
 */
abstract class PublishableController
{
    /** @return class-string<Model&PublishableModel> */
    abstract protected function model(): string;

    /** Module name: route, view, language and permission prefix. */
    abstract protected function module(): string;

    /**
     * Rules of one field (the same for every language; `required` applies to the default language only).
     *
     * @return array<string, list<string>|string>
     */
    abstract protected function fieldRules(): array;

    public function index(Request $request): View
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('viewAny', $this->model());
        $table = app(TableRegistry::class)->get($this->module()) ?? abort(404);

        return view($this->module().'::index', [
            'module' => $this->module(),
            'result' => $table->resolve($request, $user),
            'canCreate' => Gate::forUser($user)->allows('create', $this->model()),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::forUser($this->user($request))->authorize('create', $this->model());
        $class = $this->model();

        return $this->form($request, new $class, true);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('create', $this->model());
        $class = $this->model();
        $model = new $class;

        return $this->apply($request, $model, $user);
    }

    public function edit(Request $request, int $id): View
    {
        Gate::forUser($this->user($request))->authorize('update', $model = $this->find($id));

        return $this->form($request, $model, false);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('update', $model = $this->find($id));

        return $this->apply($request, $model, $user);
    }

    public function publish(Request $request, int $id): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('publish', $model = $this->find($id));
        app(PublishContent::class)($model, $user);

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::publishing.msg.published')]);
    }

    public function unpublish(Request $request, int $id): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('publish', $model = $this->find($id));
        app(UnpublishContent::class)($model, $user);

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::publishing.msg.unpublished')]);
    }

    public function restore(Request $request, int $id, int $revision): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('update', $model = $this->find($id));
        $row = Revision::query()->where('revisionable_type', $model->getMorphClass())->where('revisionable_id', $model->getKey())->findOrFail($revision);
        app(RestoreRevision::class)($model, $row, $user);

        return redirect()->route($this->module().'.edit', $model->getKey())->with('toast', ['type' => 'success', 'message' => __('codeart::publishing.msg.restored')]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = $this->user($request);
        Gate::forUser($user)->authorize('delete', $model = $this->find($id));
        app(ActivityLog::class)->record('publishing.deleted', 'Content deleted', $model, $user instanceof Model ? $user : null, ['id' => $model->getKey()], 'content');
        Revision::query()->where('revisionable_type', $model->getMorphClass())->where('revisionable_id', $model->getKey())->delete();
        $model->delete();

        return redirect()->route($this->module().'.index')->with('toast', ['type' => 'success', 'message' => __('codeart::publishing.msg.deleted')]);
    }

    /** Save the form as the draft, then do what the pressed button says (`intent`). */
    private function apply(Request $request, Model $model, Authenticatable $user): RedirectResponse
    {
        /** @var Model&PublishableModel $model */
        $intent = (string) $request->input('intent', 'draft');
        $data = $request->validate($this->rules($intent));
        if ($intent === 'status' && (! $model->usesWorkflow() || ! Gate::forUser($user)->allows('publish', $model))) {
            abort(403);
        }

        if (! $model->exists) {
            $model->forceFill(['status' => PublishStatus::Draft])->save(); // only now: nothing is created for an invalid form
        }
        // Rich text is sanitized HERE, on the server, before anything is stored (the browser's HTML is never trusted).
        $editorFields = array_keys(array_filter($model->formFields(), fn (string $type): bool => $type === 'editor'));
        $translations = HtmlSanitizer::cleanTranslations((array) ($data['translations'] ?? []), $editorFields);
        $draft = app(SaveDraft::class)($model, ['translations' => $translations, 'fields' => $this->mediaValues($request, $model)], $user);
        $edit = redirect()->route($this->module().'.edit', $model->getKey());

        if ($intent === 'preview' || $intent === 'frame') {
            return $this->preview($request, $model, $draft, $user, $intent === 'frame') ?? $edit->with('toast', ['type' => 'error', 'message' => __('codeart::publishing.msg.no_public_url')]);
        }
        if (! $model->usesWorkflow()) {
            app(PublishContent::class)($model, $user);

            return $edit->with('toast', ['type' => 'success', 'message' => __('codeart::publishing.msg.published')]);
        }
        if ($intent === 'status') {
            $this->changeStatus($model, PublishStatus::from((string) $data['status']), $data['scheduled_at'] ?? null, $user);

            return $edit->with('toast', ['type' => 'success', 'message' => __('codeart::publishing.msg.status_'.$data['status'])]);
        }

        return $edit->with('toast', ['type' => 'success', 'message' => __('codeart::publishing.msg.draft_saved')]);
    }

    private function changeStatus(Model $model, PublishStatus $status, mixed $scheduledAt, Authenticatable $user): void
    {
        match ($status) {
            PublishStatus::Published => app(PublishContent::class)($model, $user),
            PublishStatus::Draft => app(UnpublishContent::class)($model, $user),
            PublishStatus::Scheduled => app(PublishContent::class)($model, $user, $this->scheduleTime($scheduledAt)),
        };
    }

    /** The form time is the editor's local time (display zone); stored in UTC. */
    private function scheduleTime(mixed $input): CarbonImmutable
    {
        $at = CarbonImmutable::parse((string) $input, DisplayTime::current()->zone())->utc();
        if (! $at->isFuture()) {
            throw ValidationException::withMessages(['scheduled_at' => (string) __('codeart::publishing.msg.schedule_in_past')]);
        }

        return $at;
    }

    private function preview(Request $request, Model $model, Revision $draft, Authenticatable $user, bool $frame): ?RedirectResponse
    {
        /** @var Model&PublishableModel $model */
        $locales = app(Locales::class);
        $locale = (string) $request->input('preview_locale', $locales->default());
        $locale = $locales->isEnabled($locale) ? $locale : $locales->default();
        if ($model->publicUrl($locale) === null) {
            return null;
        }
        $url = app(PreviewManager::class)->url($model, $draft, $user, $locale);
        if ($url === null) {
            return null;
        }
        if ($frame) {
            $token = (string) parse_url($url, PHP_URL_QUERY);
            parse_str($token, $query);

            return redirect()->route('codeart.preview.show', ['token' => $query[Preview::parameter()]]);
        }

        return redirect()->away($url);
    }

    /**
     * Ids of the media fields (`mediaFields()`): only files that exist, in the order sent, at most one for a single field.
     *
     * @return array<string, list<int>>
     */
    private function mediaValues(Request $request, Model $model): array
    {
        $out = [];
        foreach (method_exists($model, 'mediaFields') ? $model->mediaFields() : [] as $column => $options) {
            $sent = array_values(array_unique(array_map('intval', array_filter((array) $request->input($column, []), 'is_numeric'))));
            $known = $sent === [] ? [] : array_map('intval', Media::query()->whereIn('id', array_slice($sent, 0, 100))->pluck('id')->all());
            $ids = array_values(array_filter($sent, fn (int $id): bool => in_array($id, $known, true)));
            $out[$column] = ($options['multiple'] ?? false) ? $ids : array_slice($ids, 0, 1);
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function rules(string $intent): array
    {
        $locales = app(Locales::class);
        $rules = ['intent' => ['required', 'in:draft,status,preview,frame'], 'preview_locale' => ['nullable', 'string', 'max:12']];
        $field = $this->fieldRules();
        $model = $this->model();
        if (in_array('slug', (new $model)->translatedAttributes(), true)) {
            $field += ['slug' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']];
        }
        foreach ($this->manualLocales() as $locale) {
            foreach ($field as $attribute => $attributeRules) {
                $list = is_array($attributeRules) ? $attributeRules : explode('|', $attributeRules);
                // Only the default language has to be filled in; the others are optional (the fallback chain shows the default).
                $rules["translations.{$locale}.{$attribute}"] = $locale === $locales->default() ? $list : array_map(fn (string $r): string => $r === 'required' ? 'nullable' : $r, $list);
            }
        }
        if ($intent === 'status') {
            $rules['status'] = ['required', 'in:draft,published,scheduled'];
            $rules['scheduled_at'] = ['required_if:status,scheduled', 'nullable', 'date'];
        }

        return $rules;
    }

    /** @return list<string> enabled locales that are written by hand (derived ones are generated) */
    private function manualLocales(): array
    {
        $locales = app(Locales::class);

        return array_values(array_filter($locales->enabled(), fn (string $l): bool => $locales->derivedFrom($l) === null));
    }

    private function form(Request $request, Model $model, bool $isNew): View
    {
        /** @var Model&PublishableModel $model */
        $user = $this->user($request);
        $locales = app(Locales::class);
        $module = $this->module();

        return view($module.'::form', ['form' => [
            'module' => $module,
            'model' => $model,
            'isNew' => $isNew,
            'content' => $model->currentContent(),
            'fields' => $model->formFields(),
            'mediaFields' => method_exists($model, 'mediaFields') ? $model->mediaFields() : [],
            'locales' => collect($this->manualLocales())->mapWithKeys(fn (string $l): array => [$l => $locales->name($l)])->all(),
            'defaultLocale' => $locales->default(),
            'workflow' => $model->usesWorkflow(),
            'canPublish' => $model->usesWorkflow() && Gate::forUser($user)->allows('publish', $model),
            'canPreview' => $model->usesWorkflow() && ! $isNew && $model->publicUrl() !== null,
            'canDelete' => ! $isNew && Gate::forUser($user)->allows('delete', $model),
            'revisions' => $isNew || ! $model->usesWorkflow() ? collect() : $model->revisions()->limit(max(1, (int) config('codeart.publishing.max_revisions', 20)))->get(),
            'scheduledAt' => $model->getAttribute('scheduled_at') !== null ? DisplayTime::current()->convert($model->getAttribute('scheduled_at'))?->format('Y-m-d\TH:i') : null,
        ]]);
    }

    /** @return Model&PublishableModel */
    private function find(int $id): Model
    {
        $class = $this->model();

        return $class::query()->findOrFail($id);
    }

    private function user(Request $request): Authenticatable
    {
        return $request->user(Admin::guardName()) ?? abort(401);
    }
}
