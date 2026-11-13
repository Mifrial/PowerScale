<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Value;

/**
 * Коллекции библиотеки ассетов, которыми владеет Roleplay/Scene.
 */
final class SceneAssetCollections
{
    public const DECOR = 'decor';

    public const TERRAIN = 'terrain';

    /**
     * Запрещает экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Коды и подписи коллекций сцены.
     *
     * @return array<string, string> Код → подпись.
     */
    public static function getTitles(): array
    {
        return [
            self::DECOR => 'Декор',
            self::TERRAIN => 'Текстуры террейна',
        ];
    }
}
