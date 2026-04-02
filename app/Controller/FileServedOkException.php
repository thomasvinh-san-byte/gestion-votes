<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * Thrown by file-serving controllers instead of readfile()+exit() in test mode.
 *
 * Used instead of readfile()/exit() to keep serve() methods testable
 * without sending binary output or terminating the test process.
 */
final class FileServedOkException extends \RuntimeException {
    public function __construct(
        private readonly string $path,
        private readonly string $contentType,
        private readonly string $filename,
        private readonly int $fileSize,
    ) {
        parent::__construct("File served: {$path}");
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getContentType(): string {
        return $this->contentType;
    }

    public function getFilename(): string {
        return $this->filename;
    }

    public function getFileSize(): int {
        return $this->fileSize;
    }
}
