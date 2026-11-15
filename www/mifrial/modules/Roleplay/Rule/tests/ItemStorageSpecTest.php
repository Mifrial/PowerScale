<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Tests;

use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Spec\ItemSpecs;
use PHPUnit\Framework\TestCase;

/**
 * План M10: блок storage предмета-хранилища.
 */
final class ItemStorageSpecTest extends TestCase
{
    /**
     * Все поля блока читаются.
     *
     * @return void
     */
    public function testStorageIsRead(): void
    {
        $item = ItemSpecs::read([
            'category' => 'equipment',
            'storage' => [
                'power_parameter' => 'max_power',
                'volume_parameter' => 'volume',
                'recorded_parameter' => 'recorded_spells',
                'volume_base' => 20,
                'volume_increment' => 10,
                'min_volume_power_delta' => 3,
                'recorded_keywords' => ['electromancy'],
            ],
        ]);
        $storage = $item->getStorage();

        self::assertNotNull($storage);
        self::assertSame('max_power', $storage->getPowerParameter());
        self::assertSame('volume', $storage->getVolumeParameter());
        self::assertSame('recorded_spells', $storage->getRecordedParameter());
        self::assertSame(20, $storage->getVolumeBase());
        self::assertSame(10, $storage->getVolumeIncrement());
        self::assertSame(3, $storage->getMinVolumePowerDelta());
        self::assertSame(['electromancy'], $storage->getRecordedKeywords());
    }

    /**
     * Нет ключа — предмет не хранилище.
     *
     * @return void
     */
    public function testMissingStorageIsNull(): void
    {
        $item = ItemSpecs::read(['category' => 'equipment']);

        self::assertNull($item->getStorage());
    }

    /**
     * Ограничение записей по умолчанию пустое.
     *
     * @return void
     */
    public function testRecordedKeywordsDefaultToEmpty(): void
    {
        $item = ItemSpecs::read([
            'category' => 'equipment',
            'storage' => [
                'power_parameter' => 'max_power',
                'volume_parameter' => 'volume',
                'volume_base' => 20,
                'volume_increment' => 10,
                'min_volume_power_delta' => 3,
            ],
        ]);
        $storage = $item->getStorage();

        self::assertNotNull($storage);
        self::assertSame([], $storage->getRecordedKeywords());
    }

    /**
     * Блок без обязательного поля не разбирается.
     *
     * @return void
     */
    public function testStorageWithoutVolumeParameterIsRejected(): void
    {
        $this->expectException(RuleSpecShapeException::class);

        ItemSpecs::read([
            'category' => 'equipment',
            'storage' => [
                'power_parameter' => 'max_power',
                'volume_parameter' => null,
                'volume_base' => 20,
                'volume_increment' => 10,
                'min_volume_power_delta' => 3,
            ],
        ]);
    }
}
