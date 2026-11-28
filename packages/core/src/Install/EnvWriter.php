<?php

namespace Codeart\Core\Install;

use RuntimeException;

/** Writes the .env file: only the keys it is given, everything else stays. Values are never logged. */
class EnvWriter
{
    public function __construct(private readonly InstallPaths $paths) {}

    /** @param array<string, scalar|null> $values */
    public function write(array $values): void
    {
        $lines = is_file($this->paths->env) ? preg_split('/\R/', rtrim((string) file_get_contents($this->paths->env))) : [];
        $lines = $lines === false || $lines === [''] ? [] : $lines;

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $found = false;
            foreach ($lines as $i => $existing) {
                if (preg_match('/^'.preg_quote($key, '/').'=/', $existing) === 1) {
                    $lines[$i] = $line;
                    $found = true;
                }
            }
            $found || $lines[] = $line;
        }

        $temporary = $this->paths->env.'.tmp'.bin2hex(random_bytes(3));
        // 0600 from the first byte: nobody else on the server reads the database password.
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException('The .env file cannot be written.');
        }
        chmod($temporary, 0600);
        fwrite($handle, implode("\n", $lines)."\n");
        fclose($handle);
        if (! rename($temporary, $this->paths->env)) {
            @unlink($temporary);
            throw new RuntimeException('The .env file cannot be written.');
        }
        chmod($this->paths->env, 0600);
    }

    /** @return array<string, string> */
    public function read(): array
    {
        $values = [];
        if (! is_file($this->paths->env)) {
            return $values;
        }
        foreach (preg_split('/\R/', (string) file_get_contents($this->paths->env)) ?: [] as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m) !== 1) {
                continue;
            }
            $raw = $m[2];
            $values[$m[1]] = str_starts_with($raw, '"') && str_ends_with($raw, '"') && strlen($raw) >= 2
                ? stripcslashes(substr($raw, 1, -1))
                : $raw;
        }

        return $values;
    }

    private function format(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $text = (string) $value;
        if ($text === '' || preg_match('/^[A-Za-z0-9_.:\/@+,=-]+$/', $text) === 1) {
            return $text;
        }

        return '"'.addcslashes($text, "\"\\\$\n\r").'"';
    }
}
