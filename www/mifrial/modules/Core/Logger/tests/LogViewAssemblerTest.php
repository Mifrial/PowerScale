<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Logger\Dto\LogRecord;
use Mifrial\Core\Logger\Service\LogViewAssembler;
use PHPUnit\Framework\TestCase;

final class LogViewAssemblerTest extends TestCase
{
    /**
     * Allowlist скаляров; чужие ключи и вложенность отбрасываются.
     *
     * @return void
     */
    public function testAssemblesSafeContext(): void
    {
        $view = (new LogViewAssembler())->assemble(LogRecord::fromNormalized([
            'id' => 42,
            'created_at' => DateTime::fromUnix(1_757_419_200),
            'level' => 'error',
            'message' => 'Unhandled kernel error',
            'source' => 'character.create',
            'user_id' => 7,
            'exception_class' => 'Mifrial\\Core\\Kernel\\Exception\\KernelException',
            'error_code' => 'INTERNAL',
            'context' => [
                'file' => 'Application.php',
                'line' => 123,
                'message' => 'inner',
                'jobId' => 'j1',
                'attempts' => 2,
                'sql' => 'select 1',
                'nested' => ['x' => 1],
                'trace' => ['a'],
            ],
        ]));
        self::assertSame(42, $view['id']);
        self::assertSame(1_757_419_200, $view['createdAt']);
        self::assertSame('error', $view['level']);
        self::assertSame('Unhandled kernel error', $view['message']);
        self::assertSame('character.create', $view['source']);
        self::assertSame(7, $view['userId']);
        self::assertSame('INTERNAL', $view['errorCode']);
        self::assertSame(
            [
                'file' => 'Application.php',
                'line' => 123,
                'message' => 'inner',
                'jobId' => 'j1',
                'attempts' => 2,
            ],
            $view['context'],
        );
        self::assertArrayNotHasKey('sql', $view['context']);
    }

    /**
     * Пустой/чужой context → null.
     *
     * @return void
     */
    public function testEmptyContextIsNull(): void
    {
        $view = (new LogViewAssembler())->assemble(LogRecord::fromNormalized([
            'id' => 1,
            'created_at' => DateTime::fromUnix(1),
            'level' => 'info',
            'message' => 'note',
            'source' => null,
            'user_id' => null,
            'exception_class' => null,
            'error_code' => null,
            'context' => ['sql' => 'x'],
        ]));
        self::assertNull($view['context']);
        self::assertNull($view['source']);
        self::assertNull($view['userId']);
    }
}
