<?php

use Codeart\Core\Admin\Version;

it('shows a tagged release as vX.Y.Z and everything else as dev', function (?string $in, string $out) {
    expect(Version::resolve($in))->toBe($out);
})->with([
    ['0.3.0', 'v0.3.0'],
    ['v0.3.0', 'v0.3.0'],
    ['1.2.3-rc.1', 'v1.2.3-rc.1'],
    ['dev-feat/auth-backend', 'dev'],
    ['dev-main', 'dev'],
    ['1.x-dev', 'dev'],
    ['0.3.x-dev', 'dev'],
    ['main', 'dev'],
    ['', 'dev'],
    [null, 'dev'],
]);

it('resolves the installed version without failing', function () {
    expect(Version::cms())->toMatch('/^(dev|v\d.*)$/');
});
