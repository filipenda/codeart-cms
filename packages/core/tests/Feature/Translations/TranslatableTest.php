<?php

use Codeart\Core\Tests\Support\Article;
use Codeart\Core\Translations\Locales;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('article_translations');
    Schema::dropIfExists('articles');
    Schema::create('articles', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });
    Schema::create('article_translations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('article_id')->constrained()->cascadeOnDelete();
        $table->string('locale', 12);
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('slug');
        $table->json('manual_fields')->nullable();
        $table->unique(['article_id', 'locale']);
        $table->unique(['locale', 'slug']);
    });
    app()->setLocale('sr');
});

function article(array $sr = ['title' => 'Ljubav i njiva', 'description' => 'Opis']): Article
{
    $article = Article::create();
    $article->setTranslation('sr', $sr);

    return $article->fresh();
}

it('stores and reads Cyrillic correctly', function () {
    $article = Article::create();
    $article->setTranslation('sr-Cyrl', ['title' => 'Љубав и њива ђак', 'description' => 'Ћирилица ŽĐ']);

    $row = DB::table('article_translations')->where('locale', 'sr-Cyrl')->first();
    expect($row->title)->toBe('Љубав и њива ђак')
        ->and($row->description)->toBe('Ћирилица ŽĐ')
        ->and(Article::first()->translated('title', 'sr-Cyrl'))->toBe('Љубав и њива ђак');

    $collation = DB::scalar("select collation_name from information_schema.columns where table_schema = database() and table_name = 'article_translations' and column_name = 'title'");
    expect($collation)->toStartWith('utf8mb4');
});

it('generates sr-Cyrl when sr is saved', function () {
    $article = article();

    $cyr = $article->getTranslation('sr-Cyrl', false);
    expect($cyr->title)->toBe('Љубав и њива')
        ->and($cyr->description)->toBe('Опис')
        ->and($cyr->manual_fields)->toBe([])
        ->and($cyr->slug)->toBe('ljubav-i-njiva');
});

it('does not generate sr-Cyrl when sr does not exist', function () {
    $article = Article::create();
    $article->setTranslation('en', ['title' => 'Love']);

    expect($article->hasTranslation('sr-Cyrl'))->toBeFalse()
        ->and($article->translations()->count())->toBe(1);
});

it('never overwrites attributes in manual_fields when sr is saved again', function () {
    $article = article(['title' => 'Injekcija', 'description' => 'Opis']);
    $article->setTranslation('sr-Cyrl', ['title' => 'Инјекција']);
    expect($article->fresh()->getTranslation('sr-Cyrl', false)->manual_fields)->toBe(['title']);

    $article->setTranslation('sr', ['title' => 'Injekcija nova', 'description' => 'Novi opis']);

    $cyr = $article->fresh()->getTranslation('sr-Cyrl', false);
    expect($cyr->title)->toBe('Инјекција')          // hand-edited, kept
        ->and($cyr->description)->toBe('Нови опис') // automatic, regenerated
        ->and($cyr->manual_fields)->toBe(['title']);
});

it('marks a differing Cyrillic value as manual and unmarks one equal to the generated value', function () {
    $article = article(['title' => 'Ljubav']);

    $article->setTranslation('sr-Cyrl', ['title' => 'Љубав!']);
    expect($article->fresh()->getTranslation('sr-Cyrl', false)->manual_fields)->toBe(['title']);

    $article->setTranslation('sr-Cyrl', ['title' => 'Љубав']);
    $cyr = $article->fresh()->getTranslation('sr-Cyrl', false);
    expect($cyr->manual_fields)->toBe([])->and($cyr->title)->toBe('Љубав');
});

it('resets an attribute to automatic', function () {
    $article = article(['title' => 'Injekcija']);
    $article->setTranslation('sr-Cyrl', ['title' => 'Инјекција']);

    $article->resetToAutomatic('sr-Cyrl', 'title');

    $cyr = $article->fresh()->getTranslation('sr-Cyrl', false);
    expect($cyr->manual_fields)->toBe([])->and($cyr->title)->toBe('Ињекција');
});

it('copies the slug to sr-Cyrl and keeps it ASCII lowercase', function () {
    $article = article(['title' => 'Đurđevdan ŠUMA']);
    $article->setTranslation('sr-Cyrl', ['title' => 'Нешто друго', 'slug' => 'Ћирилични-Слаг']);

    expect($article->fresh()->getTranslation('sr', false)->slug)->toBe('djurdjevdan-suma')
        ->and($article->fresh()->getTranslation('sr-Cyrl', false)->slug)->toBe('djurdjevdan-suma');
});

it('keeps slugs unique per locale but lets locales repeat them', function () {
    $a = article(['title' => 'Njiva']);
    $b = Article::create();
    $b->setTranslation('sr', ['title' => 'Njiva']);
    $b->setTranslation('en', ['title' => 'Field', 'slug' => 'njiva']);
    $a->setTranslation('en', ['title' => 'Field two', 'slug' => 'field']);

    expect($a->fresh()->getTranslation('sr', false)->slug)->toBe('njiva')
        ->and($b->fresh()->getTranslation('sr', false)->slug)->toBe('njiva-2')
        ->and($b->fresh()->getTranslation('en', false)->slug)->toBe('njiva')
        ->and(Article::uniqueSlug('Njiva', 'sr'))->toBe('njiva-3');

    // Re-saving keeps the slug of the row itself.
    $a->setTranslation('sr', ['title' => 'Njiva promenjena']);
    expect($a->fresh()->getTranslation('sr', false)->slug)->toBe('njiva');

    expect(fn () => DB::table('article_translations')->insert(['article_id' => $a->id, 'locale' => 'xx', 'title' => 't', 'slug' => 'njiva']))
        ->not->toThrow(Exception::class);
    expect(fn () => DB::table('article_translations')->insert(['article_id' => $b->id, 'locale' => 'xx', 'title' => 't', 'slug' => 'njiva']))
        ->toThrow(QueryException::class);
});

it('follows the fallback chain', function () {
    $article = article(['title' => 'Ljubav']);
    // sr-Cyrl exists (generated); remove it to prove the chain sr-Cyrl -> sr.
    $article->translations()->where('locale', 'sr-Cyrl')->delete();
    $article = $article->fresh();

    app()->setLocale('sr-Cyrl');
    expect($article->title)->toBe('Ljubav');
    app()->setLocale('en');
    expect($article->title)->toBe('Ljubav')
        ->and($article->getTranslation('en', false))->toBeNull()
        ->and($article->hasTranslation('en'))->toBeFalse();

    $article->setTranslation('en', ['title' => 'Love']);
    expect($article->fresh()->title)->toBe('Love');
    expect(app(Locales::class)->chain('en'))->toBe(['en', 'sr'])
        ->and(app(Locales::class)->chain('sr-Cyrl'))->toBe(['sr-Cyrl', 'sr']);
});

it('finds a model by slug in a locale', function () {
    $article = article(['title' => 'Ljubav']);
    $article->setTranslation('en', ['title' => 'Love']);

    expect(Article::whereTranslatedSlug('love', 'en')->first()?->is($article))->toBeTrue()
        ->and(Article::whereTranslatedSlug('love', 'sr')->exists())->toBeFalse()
        ->and(Article::whereTranslatedSlug('ljubav', 'sr-Cyrl')->exists())->toBeTrue();

    app()->setLocale('en');
    expect(Article::whereTranslatedSlug('love')->exists())->toBeTrue();
});

it('rejects disabled locales and unsaved models', function () {
    expect(fn () => article()->setTranslation('de', ['title' => 'x']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Article)->setTranslation('sr', ['title' => 'x']))->toThrow(LogicException::class);
});
