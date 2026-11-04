<?php

declare(strict_types=1);

namespace Mifrial\Core\File\Tests;

use Mifrial\Core\File\Dto\FileSettings;
use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use PHPUnit\Framework\TestCase;

final class FileSettingsTest extends TestCase
{
    /**
     * Нет ключа — дефолтный путь и общая политика картинок.
     *
     * @return void
     */
    public function testDefaults(): void
    {
        $settings = FileSettings::fromSection(null, '/var/file');
        self::assertSame('/var/file', $settings->path());
        self::assertSame(33554432, $settings->maxSize());
        self::assertSame(['image/png', 'image/jpeg', 'image/webp'], $settings->mime());
        self::assertTrue($settings->allowsMime('image/png'));
        self::assertFalse($settings->allowsMime('application/pdf'));
    }

    /**
     * Явный срез и отсутствие дублей в mime.
     *
     * @return void
     */
    public function testExplicitSection(): void
    {
        $settings = FileSettings::fromSection([
            'path' => ' /srv/file ',
            'max_size' => 1024,
            'mime' => ['application/pdf', 'application/pdf'],
        ], '/var/file');
        self::assertSame('/srv/file', $settings->path());
        self::assertSame(1024, $settings->maxSize());
        self::assertSame(['application/pdf'], $settings->mime());
    }

    /**
     * Недопустимые значения среза.
     *
     * @return void
     */
    public function testInvalidSection(): void
    {
        foreach (
            [
                ['path' => ''],
                ['max_size' => 0],
                ['max_size' => 33554433],
                ['max_size' => '10'],
                ['mime' => []],
                ['mime' => ['png']],
                ['mime' => ['IMAGE/PNG']],
            ] as $section
        ) {
            try {
                FileSettings::fromSection($section, '/var/file');
                self::fail('invalid section must fail: ' . json_encode($section));
            } catch (SetupException $exception) {
                self::assertSame('SETUP_INVALID', $exception->getErrorCode());
            }
        }

        try {
            FileSettings::fromSection('nope', '/var/file');
            self::fail('non-array section must fail');
        } catch (SetupException $exception) {
            self::assertSame('SETUP_INVALID', $exception->getErrorCode());
        }
    }
}
