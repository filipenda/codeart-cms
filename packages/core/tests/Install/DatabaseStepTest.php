<?php

use Codeart\Core\Install\DatabaseTester;
use Codeart\Core\Install\EnvWriter;

beforeEach(function () {
    $this->fakeServer();
    $this->withSession(['installer.key_ok' => true]);
});

it('maps a wrong password to a human message', function () {
    $result = app(DatabaseTester::class)->test(...array_values($this->dbInput(['password' => 'definitely-wrong'])));

    expect($result->ok)->toBeFalse()->and($result->error)->toBe('credentials');
    $this->post('/install/database', $this->dbInput(['password' => 'definitely-wrong']))
        ->assertSessionHasErrors(['connection' => __('codeart::install.db_error.credentials')]);
});

it('maps an unknown database', function () {
    $input = $this->dbInput(['database' => 'no_such_db_'.bin2hex(random_bytes(3))]);

    expect(app(DatabaseTester::class)->test(...array_values($input))->error)->toBe('unknown_database');
    $this->post('/install/database', $input)->assertSessionHasErrors(['connection' => __('codeart::install.db_error.unknown_database')]);
});

it('maps an unreachable server', function () {
    $input = $this->dbInput(['host' => '127.0.0.1', 'port' => 1]);

    expect(app(DatabaseTester::class)->test(...array_values($input))->error)->toBe('unreachable');
    $this->post('/install/database', $input)->assertSessionHasErrors(['connection' => __('codeart::install.db_error.unreachable')]);
    expect(file_exists($this->dir.'/.env'))->toBeFalse();
});

it('maps missing privileges', function () {
    $user = 'cai_'.bin2hex(random_bytes(4));
    $pdo = $this->rootPdo();
    $pdo->exec("CREATE USER '{$user}'@'%' IDENTIFIED BY 'pw-Test-1234'");
    try {
        $input = $this->dbInput(['username' => $user, 'password' => 'pw-Test-1234']);
        expect(app(DatabaseTester::class)->test(...array_values($input))->error)->toBe('privileges');

        // Can look, cannot create: still "privileges".
        $pdo->exec("GRANT SELECT ON `{$this->database}`.* TO '{$user}'@'%'");
        expect(app(DatabaseTester::class)->test(...array_values($input))->error)->toBe('privileges');
    } finally {
        $pdo->exec("DROP USER '{$user}'@'%'");
    }
});

it('writes nothing and warns when the database is not empty, until confirmed', function () {
    $this->rootPdo()->exec("CREATE TABLE `{$this->database}`.`other_site` (id INT)");

    $this->post('/install/database', $this->dbInput())->assertSessionHasErrors('confirm_existing');
    expect(file_exists($this->dir.'/.env'))->toBeFalse();

    $this->post('/install/database', $this->dbInput() + ['confirm_existing' => 1])->assertRedirect(route('install.site'));
    expect(file_exists($this->dir.'/.env'))->toBeTrue();
});

it('writes the production .env with safe values and private permissions', function () {
    $user = 'cai_'.bin2hex(random_bytes(4));
    $pdo = $this->rootPdo();
    $pdo->exec("CREATE USER '{$user}'@'%' IDENTIFIED BY 'p\"a\$s s#word'");
    $pdo->exec("GRANT ALL ON `{$this->database}`.* TO '{$user}'@'%'");
    try {
        $this->post('/install/database', $this->dbInput(['username' => $user, 'password' => 'p"a$s s#word']))->assertRedirect(route('install.site'));
    } finally {
        $pdo->exec("DROP USER '{$user}'@'%'");
    }

    $env = $this->dir.'/.env';
    $text = file_get_contents($env);
    expect(substr(sprintf('%o', fileperms($env)), -4))->toBe('0600')
        ->and($text)->toContain('APP_ENV=production', 'APP_DEBUG=false', 'SESSION_DRIVER=file', 'CACHE_STORE=file', 'QUEUE_CONNECTION=database', 'DB_DATABASE='.$this->database)
        ->and($text)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/=]{43,}$/m')
        ->and($text)->toMatch('/^APP_URL=http/m');
    // The awkward password comes back unchanged.
    $values = app(EnvWriter::class)->read();
    expect($values['DB_PASSWORD'])->toBe('p"a$s s#word');
});
