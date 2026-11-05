<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Tests;

use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Реестр подписей встроенных источников и его сверка с кодом.
 */
final class AdvantageSourceLabelsTest extends TestCase
{
    /**
     * Известные коды отдают русские подписи.
     *
     * @return void
     */
    public function testKnownLabels(): void
    {
        self::assertSame('Обстоятельства', AdvantageSourceLabels::getLabel('circumstances'));
        self::assertSame('Действие', AdvantageSourceLabels::getLabel('action'));
        self::assertSame('Фланговая атака', AdvantageSourceLabels::getLabel('flank'));
        self::assertSame('множественная атака', AdvantageSourceLabels::getLabel('multi_attack'));
        self::assertSame('Штраф дистанции', AdvantageSourceLabels::getLabel('distance'));
        self::assertSame('Бросок', AdvantageSourceLabels::getLabel('roll'));
        self::assertSame('вручную', AdvantageSourceLabels::getLabel('manual'));
        self::assertSame('инструмент', AdvantageSourceLabels::getLabel('tool'));
        self::assertSame('тренировки', AdvantageSourceLabels::getLabel('training'));
        self::assertSame('Подготовка', AdvantageSourceLabels::getLabel('preparation'));
    }

    /**
     * Неизвестный код не выдумывает подпись.
     *
     * @return void
     */
    public function testUnknownCodeIsNull(): void
    {
        self::assertNull(AdvantageSourceLabels::getLabel('unknown'));
        self::assertNull(AdvantageSourceLabels::getLabel(null));
        self::assertNull(AdvantageSourceLabels::getLabel(''));
    }

    /**
     * Каждый литеральный источник в коде имеет подпись.
     *
     * @return void
     */
    public function testLiteralSourcesHaveLabels(): void
    {
        $missing = [];
        foreach ($this->sourceFiles() as $file) {
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                continue;
            }

            preg_match_all("/new RollAdvantage\\(\\s*'([^']+)'/", $contents, $matches);
            foreach ($matches[1] as $code) {
                if (AdvantageSourceLabels::getLabel($code) === null) {
                    $missing[] = $code . ' (' . $file->getFilename() . ')';
                }
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * PHP-файлы Roleplay без тестов.
     *
     * @return iterable<int, SplFileInfo> Файлы.
     */
    private function sourceFiles(): iterable
    {
        $root = dirname(__DIR__, 2);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            if (str_contains($path, '/tests/')) {
                continue;
            }

            yield $file;
        }
    }
}
