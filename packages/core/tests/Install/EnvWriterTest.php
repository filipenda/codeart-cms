<?php

use Codeart\Core\Install\EnvWriter;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

it('keeps other lines, replaces given keys and writes with 0600', function () {
    file_put_contents($this->dir.'/.env', "# mine\nKEEP=1\nAPP_NAME=old\n");
    chmod($this->dir.'/.env', 0644);

    app(EnvWriter::class)->write(['APP_NAME' => 'New name', 'ADDED' => true, 'EMPTY' => null]);

    $text = file_get_contents($this->dir.'/.env');
    expect($text)->toContain("# mine\nKEEP=1\n")->toContain('APP_NAME="New name"')->toContain('ADDED=true')->toContain("EMPTY=\n")
        ->and(substr_count($text, 'APP_NAME='))->toBe(1)
        ->and(substr(sprintf('%o', fileperms($this->dir.'/.env')), -4))->toBe('0600')
        ->and(glob($this->dir.'/.env.tmp*'))->toBe([]);
});

it('round-trips awkward values', function (string $value) {
    $writer = app(EnvWriter::class);
    $writer->write(['SECRET' => $value]);

    expect($writer->read()['SECRET'])->toBe($value);
})->with(['plain', 'with space', 'quote " and \\ backslash', 'dollar $HOME and #hash', '']);

it('never logs the values it writes', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
        $logged[] = $e->message.json_encode($e->context);
    });

    app(EnvWriter::class)->write(['DB_PASSWORD' => 'super-secret-value-123']);

    expect(implode(' ', $logged))->not->toContain('super-secret-value-123');
});
