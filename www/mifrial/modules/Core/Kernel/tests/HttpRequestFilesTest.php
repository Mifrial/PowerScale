<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Tests;

use Mifrial\Core\Kernel\Http\HttpRequest;
use PHPUnit\Framework\TestCase;

final class HttpRequestFilesTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $previousFiles = [];

    private mixed $previousContentLength = null;

    /**
     * Запоминает $_FILES и CONTENT_LENGTH.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->previousFiles = $_FILES;
        $this->previousContentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
    }

    /**
     * Возвращает суперглобальные значения.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $_FILES = $this->previousFiles;
        if ($this->previousContentLength === null) {
            unset($_SERVER['CONTENT_LENGTH']);

            return;
        }

        $_SERVER['CONTENT_LENGTH'] = $this->previousContentLength;
    }

    /**
     * Багаж files попадает в снимок запроса.
     *
     * @return void
     */
    public function testExposesUploadedFiles(): void
    {
        $_FILES = [
            'file' => [
                'name' => 'cover.png',
                'type' => 'image/png',
                'tmp_name' => '/tmp/php-upload',
                'error' => UPLOAD_ERR_OK,
                'size' => 123,
            ],
        ];

        $_SERVER['CONTENT_LENGTH'] = '123';

        $httpRequest = HttpRequest::fromGlobals();
        self::assertSame($_FILES, $httpRequest->getFileMap());
        self::assertSame('/tmp/php-upload', $httpRequest->getFileMap()['file']['tmp_name']);
        self::assertSame('123', $httpRequest->getHeader('Content-Length'));
    }

    /**
     * Без upload карта пустая.
     *
     * @return void
     */
    public function testEmptyFiles(): void
    {
        $_FILES = [];

        self::assertSame([], HttpRequest::fromGlobals()->getFileMap());
    }
}
