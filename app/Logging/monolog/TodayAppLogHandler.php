<?php

declare(strict_types=1);

namespace App\Logging\monolog;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Utils;

/**
 * Writes the current day to a fixed file (e.g. app.log); on day change, renames
 * the previous file to {basename}-Y-m-d.{ext} and prunes old archives.
 */
class TodayAppLogHandler extends StreamHandler
{
    private string $basePath;

    private string $fileStem;

    private string $fileExt;

    private int $maxFiles;

    private ?string $activeDay = null;

    /**
     * @param  int  $maxFiles  Max archive files to keep (0 = no pruning).
     */
    public function __construct(
        string $path,
        int $maxFiles = 0,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
    ) {
        $this->basePath = Utils::canonicalizePath($path);
        $this->fileStem = pathinfo($this->basePath, PATHINFO_FILENAME);
        $this->fileExt = pathinfo($this->basePath, PATHINFO_EXTENSION) ?: '';
        $this->maxFiles = $maxFiles;

        parent::__construct(
            $this->basePath,
            $level,
            $bubble,
            $filePermission,
            $useLocking
        );
    }

    protected function write(LogRecord $record): void
    {
        $this->syncActiveFileFor($record);
        parent::write($record);
    }

    private function syncActiveFileFor(LogRecord $record): void
    {
        $day = $record->datetime->format('Y-m-d');
        $tz = $record->datetime->getTimezone();

        if ($this->activeDay === $day) {
            return;
        }

        $this->close();

        if ($this->activeDay !== null) {
            if ($this->activeDay < $day) {
                $this->moveToArchive($this->activeDay);
            }
        } else {
            $this->archiveStaleFileIfAny($day, $tz);
        }

        $this->activeDay = $day;
    }

    private function archiveStaleFileIfAny(string $day, \DateTimeZone $tz): void
    {
        if (! is_file($this->basePath)) {
            return;
        }

        $fileDay = (new \DateTimeImmutable('@'.(string) filemtime($this->basePath)))
            ->setTimezone($tz)
            ->format('Y-m-d');

        if ($fileDay < $day) {
            $this->moveToArchive($fileDay);
        }
    }

    private function moveToArchive(string $date): void
    {
        if (! is_file($this->basePath) || ! is_writable($this->basePath)) {
            if (! is_file($this->basePath)) {
                $this->pruneOldArchives();
            }

            return;
        }

        if (filesize($this->basePath) === 0) {
            unlink($this->basePath);
            $this->pruneOldArchives();

            return;
        }

        $dest = $this->archivePathFor($date);
        if (! file_exists($dest)) {
            rename($this->basePath, $dest);
        } else {
            file_put_contents(
                $dest,
                (string) file_get_contents($this->basePath),
                FILE_APPEND
            );
            unlink($this->basePath);
        }

        $this->pruneOldArchives();
    }

    private function archivePathFor(string $date): string
    {
        $dir = dirname($this->basePath);
        if ($this->fileExt !== '') {
            return $dir.'/'.$this->fileStem.'-'.$date.'.'.$this->fileExt;
        }

        return $dir.'/'.$this->fileStem.'-'.$date;
    }

    private function pruneOldArchives(): void
    {
        if ($this->maxFiles <= 0) {
            return;
        }

        $dir = dirname($this->basePath);
        $extPart = $this->fileExt !== '' ? ('\.'.preg_quote($this->fileExt, '/')) : '';
        $re = '/^'
            .preg_quote($this->fileStem, '/')
            .'-(\d{4}-\d{2}-\d{2})'
            .$extPart
            .'$/';

        $candidates = [];
        $g = $this->fileExt !== ''
            ? $dir.'/'.$this->fileStem.'-*.'.$this->fileExt
            : $dir.'/'.$this->fileStem.'-*';
        foreach (glob($g) ?: [] as $path) {
            if (! is_file($path) || $path === $this->basePath) {
                continue;
            }
            $base = basename($path);
            if (preg_match($re, $base, $m)) {
                $candidates[] = $path;
            }
        }

        if (count($candidates) <= $this->maxFiles) {
            return;
        }

        usort($candidates, static fn (string $a, string $b) => strcmp($b, $a));
        $drop = array_slice($candidates, $this->maxFiles);
        foreach ($drop as $p) {
            if (is_file($p) && is_writable($p)) {
                @unlink($p);
            }
        }
    }
}
