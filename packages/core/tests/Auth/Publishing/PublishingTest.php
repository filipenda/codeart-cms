<?php

require_once __DIR__.'/../../Support/publishing.php';

use Carbon\Carbon;
use Codeart\Core\Publishing\Actions\PublishContent;
use Codeart\Core\Publishing\Actions\RestoreRevision;
use Codeart\Core\Publishing\Actions\SaveDraft;
use Codeart\Core\Publishing\Actions\UnpublishContent;
use Codeart\Core\Publishing\PublishStatus;
use Codeart\Core\Publishing\Revision;
use Codeart\Core\Tests\Support\PubItem;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(fn () => makePubTables());

it('keeps a saved draft away from the live content and the public query', function () {
    $item = pubItem();
    app(SaveDraft::class)($item, ['translations' => ['sr' => ['title' => 'Izmenjena', 'body' => 'Novo']]]);

    $fresh = $item->fresh();
    expect($fresh->translated('title', 'sr'))->toBe('Prva')
        ->and($fresh->currentContent()['translations']['sr']['title'])->toBe('Izmenjena')
        ->and($fresh->hasDraft())->toBeTrue()
        ->and(PubItem::published()->count())->toBe(1);

    app(UnpublishContent::class)($fresh);
    expect(PubItem::published()->count())->toBe(0);
});

it('does not publish a model that was only ever saved as a draft', function () {
    $item = pubItem(publish: false);

    expect(PubItem::published()->count())->toBe(0)
        ->and($item->status)->toBe(PublishStatus::Draft)
        ->and($item->translations()->count())->toBe(0);
});

it('publishes the draft: copies it to the live content, generates Cyrillic and clears the draft', function () {
    $item = pubItem(['title' => 'Ljubav i njiva', 'body' => 'Tekst']);

    expect($item->status)->toBe(PublishStatus::Published)
        ->and($item->published_at)->not->toBeNull()
        ->and($item->hasDraft())->toBeFalse()
        ->and($item->translated('title', 'sr'))->toBe('Ljubav i njiva')
        ->and($item->translated('title', 'sr-Cyrl'))->toBe('Љубав и њива')
        ->and(Revision::query()->where('kind', 'published')->count())->toBe(1);
});

it('walks every status transition', function () {
    $item = pubItem(publish: false);
    expect($item->status)->toBe(PublishStatus::Draft);

    app(PublishContent::class)($item, null, now()->addHour());
    expect($item->fresh()->status)->toBe(PublishStatus::Scheduled)->and($item->fresh()->scheduled_at)->not->toBeNull();

    app(UnpublishContent::class)($item->fresh()); // cancels the schedule
    expect($item->fresh()->status)->toBe(PublishStatus::Draft)->and($item->fresh()->scheduled_at)->toBeNull();

    app(PublishContent::class)($item->fresh());
    expect($item->fresh()->status)->toBe(PublishStatus::Published);

    app(UnpublishContent::class)($item->fresh());
    expect($item->fresh()->status)->toBe(PublishStatus::Draft)
        ->and($item->fresh()->translated('title', 'sr'))->toBe('Prva'); // content stays
});

it('publishes scheduled content when its time comes, using a faked clock', function () {
    $item = pubItem(publish: false);
    Carbon::setTestNow('2026-12-01 10:00:00');
    app(PublishContent::class)($item, null, now()->addMinutes(30));

    Carbon::setTestNow('2026-12-01 10:29:00');
    Artisan::call('publishing:run');
    expect($item->fresh()->status)->toBe(PublishStatus::Scheduled)->and(PubItem::published()->count())->toBe(0);

    Carbon::setTestNow('2026-12-01 10:31:00');
    Artisan::call('publishing:run');
    $fresh = $item->fresh();
    expect($fresh->status)->toBe(PublishStatus::Published)
        ->and($fresh->scheduled_at)->toBeNull()
        ->and($fresh->translated('title', 'sr'))->toBe('Prva')
        ->and(PubItem::published()->count())->toBe(1);

    Carbon::setTestNow();
});

it('keeps drafts per locale and publishes only locales that have text', function () {
    $item = pubItem(publish: false);
    app(SaveDraft::class)($item, ['translations' => ['sr' => ['title' => 'Naslov'], 'en' => ['title' => 'Title', 'body' => 'Text']]]);
    app(PublishContent::class)($item->fresh());
    app(SaveDraft::class)($item->fresh(), ['translations' => ['sr' => ['title' => 'Naslov'], 'en' => ['title' => 'New title']]]);

    $fresh = $item->fresh();
    expect($fresh->translated('title', 'en'))->toBe('Title')
        ->and($fresh->currentContent()['translations']['en']['title'])->toBe('New title')
        ->and($fresh->currentContent()['translations']['sr']['title'])->toBe('Naslov');

    // an empty language is not written at all
    $other = pubItem(['title' => 'Samo srpski'], publish: true);
    expect($other->hasTranslation('en'))->toBeFalse();
});

it('keeps only the configured number of revisions and never drops the current draft', function () {
    config()->set('codeart.publishing.max_revisions', 3);
    $item = pubItem(publish: false);
    foreach (range(1, 6) as $n) {
        app(SaveDraft::class)($item->fresh(), ['translations' => ['sr' => ['title' => "Verzija {$n}"]]]);
    }

    $revisions = $item->fresh()->revisions()->get();
    expect($revisions)->toHaveCount(3)
        ->and($item->fresh()->draftRevision->content['translations']['sr']['title'])->toBe('Verzija 6')
        ->and($revisions->pluck('id')->all())->toContain($item->fresh()->draft_revision_id);
});

it('does not add a revision when the same draft is saved again', function () {
    $item = pubItem(publish: false);
    $first = app(SaveDraft::class)($item->fresh(), ['translations' => ['sr' => ['title' => 'Isto']]]);
    $second = app(SaveDraft::class)($item->fresh(), ['translations' => ['sr' => ['title' => 'Isto']]]);

    expect($second->id)->toBe($first->id);
});

it('restores an older revision as the current draft without touching the live content', function () {
    $item = pubItem(['title' => 'Staro', 'body' => 'a']);
    $old = Revision::query()->where('revisionable_id', $item->id)->firstOrFail();
    app(SaveDraft::class)($item->fresh(), ['translations' => ['sr' => ['title' => 'Novo']]]);

    $restored = app(RestoreRevision::class)($item->fresh(), $old, staff('admin'));

    $fresh = $item->fresh();
    expect($restored->kind)->toBe('restored')
        ->and($fresh->draft_revision_id)->toBe($restored->id)
        ->and($fresh->currentContent()['translations']['sr']['title'])->toBe('Staro')
        ->and($fresh->translated('title', 'sr'))->toBe('Staro'); // live was 'Staro' too: restoring never publishes

    $other = pubItem(['title' => 'Druga']);
    expect(fn () => app(RestoreRevision::class)($item->fresh(), Revision::query()->where('revisionable_id', $other->id)->firstOrFail()))->toThrow(InvalidArgumentException::class);
});

it('writes publish, schedule, unpublish and restore to the activity log', function () {
    $admin = staff('admin');
    $item = pubItem(publish: false);
    app(PublishContent::class)($item, $admin, now()->addDay());
    app(PublishContent::class)($item->fresh(), $admin);
    app(UnpublishContent::class)($item->fresh(), $admin);
    app(RestoreRevision::class)($item->fresh(), Revision::query()->where('revisionable_id', $item->id)->firstOrFail(), $admin);

    $events = Activity::query()->where('log_name', 'content')->orderBy('id')->pluck('event')->all();
    expect($events)->toBe(['publishing.scheduled', 'publishing.published', 'publishing.unpublished', 'publishing.restored'])
        ->and(Activity::query()->where('log_name', 'content')->first()->causer_id)->toBe($admin->id);
});

it('keeps publish separate from update in the policy permissions', function () {
    $editor = staff('editor');
    $admin = staff('admin');
    Role::findByName('admin', 'admin')->givePermissionTo('pubitems.update', 'pubitems.publish');

    expect($editor->can('pubitems.update'))->toBeTrue()
        ->and($editor->can('pubitems.publish'))->toBeFalse()
        ->and($admin->fresh()->can('pubitems.publish'))->toBeTrue();
});
