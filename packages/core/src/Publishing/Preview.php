<?php

namespace Codeart\Core\Publishing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for client sites: `Preview::resolve($request, $page)`, `Preview::model($request, Page::class)`.
 *
 * @method static string parameter()
 * @method static bool requested(Request $request)
 * @method static PreviewToken|null token(Request $request)
 * @method static int|null modelId(Request $request, string $class)
 * @method static Model|null model(Request $request, string $class)
 * @method static PreviewContent resolve(Request $request, Model $model, ?string $locale = null)
 * @method static string|null url(Model $model, Revision $revision, Authenticatable $user, ?string $locale = null)
 * @method static string link(string $url, string $token)
 *
 * @see PreviewManager
 */
final class Preview extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PreviewManager::class;
    }
}
