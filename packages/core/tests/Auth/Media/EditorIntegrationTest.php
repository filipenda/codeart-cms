<?php

require_once __DIR__.'/../../Support/media.php';
require_once __DIR__.'/../../Support/publishing.php';

use Codeart\Core\Editor\HtmlSanitizer;
use Codeart\Core\Media\Actions\DeleteMedia;
use Codeart\Core\Media\Actions\StoreMedia;
use Codeart\Core\Media\MediaInUse;
use Codeart\Core\Media\MediaUsage;
use Codeart\Core\Publishing\Actions\PublishContent;
use Codeart\Core\Publishing\Actions\SaveDraft;
use Codeart\Core\Tests\Support\PubItem;
use Codeart\Core\Tests\Support\PubItemTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PubRichItem extends PubItem
{
    public function formFields(): array
    {
        return ['title' => 'text', 'body' => 'editor'];
    }

    public function translationModel(): string
    {
        return PubItemTranslation::class;
    }

    public function getForeignKey(): string
    {
        return 'pub_item_id';
    }

    public function mediaFields(): array
    {
        return ['cover' => ['multiple' => false]];
    }

    public function publishableColumns(): array
    {
        return ['cover'];
    }

    protected function casts(): array
    {
        return ['cover' => 'array'];
    }
}

beforeEach(function () {
    fake_disk('public');
    makePubTables();
    Schema::table('pub_items', fn ($t) => $t->json('cover')->nullable());
});

it('rebuilds editor images from the media id: renamed or replaced files keep working', function () {
    $media = app(StoreMedia::class)(upload('hero.jpg', imageBytes('jpeg', 1700, 900)), null, null, 'keep');
    $media->setTranslation('sr', ['alt' => 'Pogled na reku']);

    $clean = HtmlSanitizer::clean('<p>x</p><img data-media-id="'.$media->id.'" src="/old/renamed.jpg" alt="evil" onerror="alert(1)">');
    expect($clean)->toContain('data-media-id="'.$media->id.'"')->toContain($media->path)->toContain('srcset=')->not->toContain('/old/renamed.jpg')->not->toContain('onerror')
        ->and(HtmlSanitizer::mediaIds($clean))->toBe([$media->id]);

    // the file is renamed later: the stored HTML still points to the old path, the next sanitize rewrites from the id
    $oldPath = $media->path;
    $media->forceFill(['path' => 'uploads/2027/01/moved.jpg'])->save();
    expect(HtmlSanitizer::clean($clean))->toContain('uploads/2027/01/moved.jpg')->not->toContain($oldPath);

    // an id that does not exist loses the reference
    expect(HtmlSanitizer::clean('<img data-media-id="999999" src="/storage/x.jpg" alt="a">'))->not->toContain('data-media-id');
});

it('records the media used by published content (media field + images in rich text) and blocks deleting them', function () {
    $cover = app(StoreMedia::class)(upload('cover.jpg', imageBytes('jpeg', 400, 300)), null, null, 'keep');
    $inline = app(StoreMedia::class)(upload('inline.jpg', imageBytes('jpeg', 401, 300)), null, null, 'keep');
    $item = PubRichItem::query()->create(['status' => 'draft']);

    app(SaveDraft::class)($item, ['translations' => ['sr' => ['title' => 'T', 'body' => HtmlSanitizer::clean('<img data-media-id="'.$inline->id.'" src="x" alt="a">')]], 'fields' => ['cover' => [$cover->id]]]);
    expect(DB::table('media_usages')->count())->toBe(0); // a draft uses nothing yet

    app(PublishContent::class)($item->fresh());
    $fields = DB::table('media_usages')->orderBy('media_id')->pluck('field', 'media_id')->all();
    expect($fields)->toBe([$cover->id => 'cover', $inline->id => 'editor:body'])
        ->and(fn () => app(DeleteMedia::class)($inline))->toThrow(MediaInUse::class)
        ->and(MediaUsage::places($cover)[0]['label'])->toBe('T');
});
