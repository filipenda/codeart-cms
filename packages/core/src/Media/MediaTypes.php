<?php

namespace Codeart\Core\Media;

use Codeart\Core\Settings\Settings;
use Throwable;

/**
 * What may be uploaded. The decision uses the real content (libmagic through finfo), never the name or the browser's MIME.
 * Only types listed here can ever be enabled: SVG, HTML, scripts and executables are not on the list and cannot be added by a setting.
 */
final class MediaTypes
{
    /** extension (as stored) => accepted detected MIME types */
    public const array TYPES = [
        'jpg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** MIME that is stored for an extension. */
    public const array STORED_MIME = [
        'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf',
        'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel', 'ppt' => 'application/vnd.ms-powerpoint',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'txt' => 'text/plain', 'csv' => 'text/csv', 'zip' => 'application/zip',
    ];

    /** Extensions that make a file name dangerous when they appear ANYWHERE in it (`shell.php.jpg`). */
    public const array BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc', 'html', 'htm', 'xhtml', 'shtml', 'js', 'mjs', 'svg', 'svgz',
        'exe', 'dll', 'bat', 'cmd', 'com', 'sh', 'bash', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'jspx', 'htaccess', 'htpasswd', 'ini', 'swf', 'jar', 'xml', 'xsl', 'hta',
    ];

    public const array DEFAULT_ALLOWED = ['jpg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'csv'];

    public function __construct(private readonly Settings $settings) {}

    /** @return list<string> */
    public function allowed(): array
    {
        $configured = (array) $this->settings->get('media.allowed_types');

        return array_values(array_filter($configured, fn (mixed $e): bool => is_string($e) && isset(self::TYPES[$e])));
    }

    /** True when any dot separated part of the name is a blocked extension (also hides `.php` behind a harmless last extension). */
    public static function hasDangerousName(string $name): bool
    {
        $parts = array_map('strtolower', explode('.', str_replace(['\\', "\0"], '/', basename(str_replace('\\', '/', $name)))));
        array_shift($parts); // the base name itself is not an extension (`php.jpg` is only a name)

        foreach ($parts as $part) {
            if (in_array(trim($part), self::BLOCKED_EXTENSIONS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sniffs the bytes. Null when the content is not an allowed type.
     *
     * @return array{ext: string, mime: string}|null
     */
    public function detect(string $bytes, string $clientName, ?string $path = null): ?array
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->buffer($bytes));
        $clientExt = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        $allowed = $this->allowed();

        foreach (self::TYPES as $ext => $mimes) {
            if (! in_array($ext, $allowed, true) || ! in_array($mime, array_map('strtolower', $mimes), true)) {
                continue;
            }
            // Containers share a MIME type (docx/xlsx/pptx/odt/ods/zip are all zip): the extension picks the candidate, the content must prove it.
            if (in_array($ext, ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'zip'], true) && in_array($mime, ['application/zip', 'application/octet-stream', 'application/x-zip-compressed'], true)) {
                if ($ext === 'zip' ? ! in_array($clientExt, ['zip', ''], true) && ! in_array($clientExt, ['docx', 'xlsx', 'pptx', 'odt', 'ods'], true) : $clientExt !== $ext) {
                    continue;
                }
                if (! $this->zipLooksLike($bytes, $ext)) {
                    continue;
                }
            } elseif (in_array($ext, ['doc', 'xls', 'ppt'], true) && in_array($mime, ['application/x-ole-storage', 'application/cdfv2'], true) && $clientExt !== $ext) {
                continue;
            } elseif (in_array($ext, ['csv', 'txt'], true) && $mime === 'text/plain' && $clientExt !== $ext) {
                continue;
            } elseif ($ext === 'jpg' && ! in_array($clientExt, ['jpg', 'jpeg', 'jpe', ''], true) && $clientExt !== 'jfif') {
                continue;
            }

            return ['ext' => $ext, 'mime' => self::STORED_MIME[$ext]];
        }

        return null;
    }

    /** Office/ODF packages must carry their marker entry; a plain zip must simply be a readable zip. */
    private function zipLooksLike(string $bytes, string $ext): bool
    {
        if (! class_exists(\ZipArchive::class)) {
            return $ext === 'zip';
        }
        $tmp = tempnam(sys_get_temp_dir(), 'cazip');
        if ($tmp === false) {
            return false;
        }
        try {
            file_put_contents($tmp, $bytes);
            $zip = new \ZipArchive;
            if ($zip->open($tmp) !== true) {
                return false;
            }
            $has = fn (string $entry): bool => $zip->locateName($entry) !== false;
            $ok = match ($ext) {
                'docx' => $has('[Content_Types].xml') && $has('word/document.xml'),
                'xlsx' => $has('[Content_Types].xml') && $has('xl/workbook.xml'),
                'pptx' => $has('[Content_Types].xml') && $has('ppt/presentation.xml'),
                'odt', 'ods' => $has('mimetype'),
                default => true,
            };
            $zip->close();

            return $ok;
        } catch (Throwable) {
            return false;
        } finally {
            @unlink($tmp);
        }
    }
}
