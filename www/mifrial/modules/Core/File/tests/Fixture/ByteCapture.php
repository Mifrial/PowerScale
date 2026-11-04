<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests\Fixture;

use Mifrial\Core\File\Service\FileByteEmitter;

/**
 * Приёмник заголовков, статуса и тела байтового эмиттера.
 */
final class ByteCapture
{
    /**
     * @var array<int, string>
     */
    public array $headers = [];

    public string $body = '';

    public int $statusCode = 0;

    /**
     * Создаёт эмиттер, пишущий в этот приёмник.
     *
     * @return FileByteEmitter Эмиттер.
     */
    public function emitter(): FileByteEmitter
    {
        return new FileByteEmitter($this->write(...), $this->header(...), $this->status(...));
    }

    /**
     * Дописывает тело.
     *
     * @param string $chunk Байты.
     *
     * @return void
     */
    public function write(string $chunk): void
    {
        $this->body .= $chunk;
    }

    /**
     * Запоминает заголовок.
     *
     * @param string $headerLine Строка.
     *
     * @return void
     */
    public function header(string $headerLine): void
    {
        $this->headers[] = $headerLine;
    }

    /**
     * Запоминает статус.
     *
     * @param int $status Код.
     *
     * @return void
     */
    public function status(int $status): void
    {
        $this->statusCode = $status;
    }
}
