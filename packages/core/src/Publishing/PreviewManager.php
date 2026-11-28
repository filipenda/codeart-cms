<?php

namespace Codeart\Core\Publishing;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The public API for client sites (facade: `Preview`) (docs/publishing-and-preview.md):
 *
 *   $page = Preview::model($request, Page::class) ?? Page::published()->whereTranslatedSlug($slug)->firstOrFail();
 *   $content = Preview::resolve($request, $page);   // draft when the link is valid, else the published text
 *   {{ $content->title }}
 *
 * A link is valid only when it exists, has not expired, belongs to this model, and the request is signed in on the
 * admin guard as the very user it was issued for, who must still hold `<prefix>.update`. Everything else gets published content.
 */
class PreviewManager
{
    private const string ATTRIBUTE = 'codeart.preview.token';

    public function __construct(private readonly PreviewTokens $tokens, private readonly Locales $locales) {}

    public static function parameter(): string
    {
        return (string) config('codeart.preview.parameter', 'codeart_preview');
    }

    /** Did the request carry a preview parameter at all (valid or not)? Such responses are never cached or indexed. */
    public function requested(Request $request): bool
    {
        return $request->query(self::parameter()) !== null;
    }

    /** The valid token of this request, or null. Checked once per request. */
    public function token(Request $request): ?PreviewToken
    {
        $cached = $request->attributes->get(self::ATTRIBUTE);
        if ($cached !== null) {
            return $cached instanceof PreviewToken ? $cached : null;
        }

        $token = $this->check($request);
        $request->attributes->set(self::ATTRIBUTE, $token ?? false);
        $request->attributes->set('codeart.preview.active', $token !== null);

        return $token;
    }

    /** Id of the model the request may preview, when it is of `$class`. */
    public function modelId(Request $request, string $class): ?int
    {
        $token = $this->token($request);

        return $token !== null && $token->model_type === (new $class)->getMorphClass() ? (int) $token->model_id : null;
    }

    /**
     * The model a valid link points to (draft or not), else null.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function model(Request $request, string $class): ?Model
    {
        $id = $this->modelId($request, $class);

        return $id === null ? null : $class::query()->find($id);
    }

    public function resolve(Request $request, Model $model, ?string $locale = null): PreviewContent
    {
        /** @var Model&PublishableModel $model */
        $locale ??= $this->locales->current();
        $token = $this->token($request);
        $draft = $token !== null && $token->model_type === $model->getMorphClass() && (int) $token->model_id === (int) $model->getKey()
            ? Revision::query()->find($token->revision_id)
            : null;
        if ($draft !== null && ($draft->revisionable_type !== $model->getMorphClass() || (int) $draft->revisionable_id !== (int) $model->getKey())) {
            $draft = null; // the link must belong to this very record
        }

        $out = [];
        foreach ($model->translatedAttributes() as $attribute) {
            $value = $draft !== null && $attribute !== 'slug' ? $this->draftValue($draft->translations(), $locale, $attribute) : null;
            $out[$attribute] = $value ?? $model->translated($attribute, $locale);
        }

        return new PreviewContent($model, $out, $draft !== null);
    }

    /** A signed-in editor's link to the real page: the public URL plus the token. Null when the model has no public URL pattern. */
    public function url(Model $model, Revision $revision, Authenticatable $user, ?string $locale = null): ?string
    {
        /** @var Model&PublishableModel $model */
        $base = $model->publicUrl($locale);
        if ($base === null) {
            return null;
        }

        return $this->link($base, $this->tokens->issue($model, $revision, $user, $locale));
    }

    public function link(string $url, string $token): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([self::parameter() => $token]);
    }

    private function check(Request $request): ?PreviewToken
    {
        $plain = $request->query(self::parameter());
        $row = is_string($plain) ? $this->tokens->find($plain) : null;
        $user = $request->user(Admin::guardName());
        if ($row === null || $user === null || (int) $user->getAuthIdentifier() !== (int) $row->user_id) {
            return null;
        }
        $class = Model::getActualClassNameForMorph($row->model_type);
        if (! class_exists($class) || ! is_subclass_of($class, PublishableModel::class)) {
            return null;
        }
        $permission = (new $class)->permissionPrefix().'.update';
        if (! Gate::forUser($user)->allows($permission) || ! Revision::query()->whereKey($row->revision_id)->exists()) {
            return null;
        }

        return $row;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function draftValue(array $translations, string $locale, string $attribute): ?string
    {
        foreach ($this->locales->chain($locale) as $candidate) {
            $source = $this->locales->derivedFrom($candidate);
            $value = $source !== null ? ($translations[$source][$attribute] ?? null) : ($translations[$candidate][$attribute] ?? null);
            if (is_string($value) && $value !== '') {
                return $source !== null ? $this->locales->transliterate($candidate, $value) : $value;
            }
        }

        return null;
    }
}
