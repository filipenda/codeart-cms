<?php

use Codeart\Core\Theme\Assets;

/*
 * Guards the "login page has no styles" bug: the assets component prints URLs under public/vendor/codeart,
 * which is git-ignored and only exists after `vendor:publish --tag=codeart-assets` (run by composer install/update).
 * No publish happens here on purpose: this checks what composer left behind.
 */
it('prints css and js URLs that resolve to published files identical to the built assets', function () {
    $html = $this->get('/admin/login')->assertOk()->getContent();

    preg_match_all('/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/', $html, $css);
    preg_match_all('/<script[^>]+src="([^"]+)"/', $html, $js);

    $urls = [...$css[1], ...$js[1]];
    $core = array_values(array_filter($urls, fn (string $u) => str_contains($u, '/vendor/codeart/')));

    expect($core)->toHaveCount(2);

    foreach ($core as $url) {
        $file = basename(parse_url($url, PHP_URL_PATH));
        $published = public_path('vendor/codeart/'.$file);

        expect(pathinfo($file, PATHINFO_EXTENSION))->toBeIn(['css', 'js'])
            ->and(is_file($published))->toBeTrue("{$file} is not published: run php artisan vendor:publish --tag=codeart-assets --force")
            ->and(hash_file('xxh128', $published))->toBe(hash_file('xxh128', Assets::packagePath($file)), "{$file} is stale: republish the assets");
    }
});

it('publishes the assets automatically on composer install and update', function () {
    $scripts = json_decode((string) file_get_contents(base_path('composer.json')), true)['scripts'];

    expect(implode(' ', (array) $scripts['post-install-cmd']))->toContain('--tag=codeart-assets')
        ->and(implode(' ', (array) $scripts['post-update-cmd']))->toContain('--tag=codeart-assets');
});

it('publishes the self-hosted Inter font files next to the css', function () {
    foreach (['latin', 'latin-ext', 'cyrillic', 'cyrillic-ext'] as $subset) {
        $file = "fonts/inter-{$subset}-wght-normal.woff2";

        expect(is_file(public_path('vendor/codeart/'.$file)))->toBeTrue("{$file} is not published: run php artisan vendor:publish --tag=codeart-assets --force");
    }
    expect((string) file_get_contents(Assets::packagePath('codeart.css')))->toContain('fonts/inter-latin-wght-normal.woff2');
});
