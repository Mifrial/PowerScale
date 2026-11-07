<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Service;

use Closure;
use Mifrial\Core\File\Dto\FileRecord;
use Mifrial\Core\File\Interface\Service\IFileStorage;

/**
 * Байты файла: заголовки, ETag, 304 и ошибочные статусы.
 */
final class FileByteEmitter
{
    /**
     * Создаёт отправитель байтов.
     *
     * @param Closure|null $write Приёмник чанка; null — echo.
     * @param Closure|null $header Приёмник строки заголовка; null — header().
     * @param Closure|null $status Приёмник HTTP-кода; null — http_response_code().
     * @param int $chunkSize Размер порции.
     *
     * @return void
     */
    public function __construct(
        private readonly ?Closure $write = null,
        private readonly ?Closure $header = null,
        private readonly ?Closure $status = null,
        private readonly int $chunkSize = 65536,
    ) {
    }

    /**
     * Отдаёт файл актору или 304.
     *
     * @param FileRecord $fileRecord Строка.
     * @param IFileStorage $fileStorage Диск.
     * @param string|null $ifNoneMatch Заголовок клиента.
     *
     * @return void
     */
    public function emit(FileRecord $fileRecord, IFileStorage $fileStorage, ?string $ifNoneMatch): void
    {
        $etag = '"' . $fileRecord->getSha256() . '"';
        $this->sendHeader('Cache-Control: private, max-age=31536000, immutable');
        $this->sendHeader('ETag: ' . $etag);
        $this->sendHeader('X-Content-Type-Options: nosniff');
        $this->sendHeader("Content-Security-Policy: default-src 'none'");
        if ($this->matchesEtag($ifNoneMatch, $etag)) {
            $this->sendStatus(304);

            return;
        }

        $this->sendHeader('Content-Type: ' . $fileRecord->getMime());
        $this->sendHeader('Content-Length: ' . $fileRecord->getSize());
        $this->sendHeader(
            "Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($fileRecord->getOriginalName()),
        );
        $this->sendStatus(200);
        foreach ($fileStorage->chunks($fileRecord->getStoragePath(), $this->chunkSize) as $chunk) {
            $this->output($chunk);
        }
    }

    /**
     * Голый статус без JSON-конверта и без кеша.
     *
     * @param int $httpStatus Код HTTP.
     *
     * @return void
     */
    public function emitError(int $httpStatus): void
    {
        $this->sendHeader('Content-Type: text/plain; charset=utf-8');
        $this->sendHeader('Cache-Control: no-store');
        $this->sendHeader('X-Content-Type-Options: nosniff');
        $this->sendStatus($httpStatus);
        $this->output('');
    }

    /**
     * Совпал ли If-None-Match с ETag.
     *
     * @param string|null $ifNoneMatch Заголовок.
     * @param string $etag Наш ETag в кавычках.
     *
     * @return bool true, если 304.
     */
    private function matchesEtag(?string $ifNoneMatch, string $etag): bool
    {
        if ($ifNoneMatch === null || $ifNoneMatch === '') {
            return false;
        }

        foreach (explode(',', $ifNoneMatch) as $candidate) {
            if ($this->etagCandidateMatches(trim($candidate), $etag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Совпал ли один кандидат If-None-Match с ETag.
     *
     * @param string $candidate Кандидат без пробелов.
     * @param string $etag Наш ETag в кавычках.
     *
     * @return bool true, если совпал.
     */
    private function etagCandidateMatches(string $candidate, string $etag): bool
    {
        if ($candidate === '*') {
            return true;
        }

        if (str_starts_with($candidate, 'W/')) {
            $candidate = substr($candidate, 2);
        }

        return $candidate === $etag;
    }

    /**
     * Отдаёт строку заголовка в SAPI или тест.
     *
     * @param string $headerLine Полная строка.
     *
     * @return void
     */
    private function sendHeader(string $headerLine): void
    {
        if ($this->header instanceof Closure) {
            ($this->header)($headerLine);

            return;
        }

        header($headerLine);
    }

    /**
     * Ставит HTTP-код.
     *
     * @param int $httpStatus Код.
     *
     * @return void
     */
    private function sendStatus(int $httpStatus): void
    {
        if ($this->status instanceof Closure) {
            ($this->status)($httpStatus);

            return;
        }

        http_response_code($httpStatus);
    }

    /**
     * Отдаёт чанк в SAPI или тест.
     *
     * @param string $chunk Байты.
     *
     * @return void
     */
    private function output(string $chunk): void
    {
        if ($this->write instanceof Closure) {
            ($this->write)($chunk);

            return;
        }

        echo $chunk;
    }
}
