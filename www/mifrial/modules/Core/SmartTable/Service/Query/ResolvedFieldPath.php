<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Query;

use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Field\ReferenceField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Разобранный путь: hops и лист.
 */
final class ResolvedFieldPath
{
    /**
     * Сохраняет цепочку hop и лист.
     *
     * @param string $pathKey Ключ с точками.
     * @param SmartTableDefinition $rootTable Стартовая карта.
     * @param array<int, ReferenceField> $hopFields Reference-сегменты.
     * @param array<int, SmartTableDefinition> $hopTables Карты после каждого hop.
     * @param BaseField $leafField Лист.
     * @param SmartTableDefinition $leafTable Карта листа.
     *
     * @return void
     */
    public function __construct(
        private readonly string $pathKey,
        private readonly SmartTableDefinition $rootTable,
        private readonly array $hopFields,
        private readonly array $hopTables,
        private readonly BaseField $leafField,
        private readonly SmartTableDefinition $leafTable,
    ) {
    }

    /**
     * Возвращает ключ пути.
     *
     * @return string Ключ.
     */
    public function pathKey(): string
    {
        return $this->pathKey;
    }

    /**
     * Стартовая таблица запроса.
     *
     * @return SmartTableDefinition Карта.
     */
    public function rootTable(): SmartTableDefinition
    {
        return $this->rootTable;
    }

    /**
     * Reference-поля hop.
     *
     * @return array<int, ReferenceField> Hops.
     */
    public function hopFields(): array
    {
        return $this->hopFields;
    }

    /**
     * Карты после hop.
     *
     * @return array<int, SmartTableDefinition> Цели.
     */
    public function hopTables(): array
    {
        return $this->hopTables;
    }

    /**
     * Поле-лист.
     *
     * @return BaseField Лист.
     */
    public function leafField(): BaseField
    {
        return $this->leafField;
    }

    /**
     * Карта листа.
     *
     * @return SmartTableDefinition Карта.
     */
    public function leafTable(): SmartTableDefinition
    {
        return $this->leafTable;
    }

    /**
     * Сегменты кэша: стол + поле.
     *
     * @return array<int, array{table: string, field: string}> Теги.
     */
    public function cacheSegments(): array
    {
        $segments = [];
        $currentTable = $this->rootTable;
        foreach ($this->hopFields as $hopIndex => $hopField) {
            $segments[] = [
                'table' => $currentTable->getName(),
                'field' => $hopField->name(),
            ];
            $currentTable = $this->hopTables[$hopIndex];
        }

        $segments[] = [
            'table' => $this->leafTable->getName(),
            'field' => $this->leafField->name(),
        ];

        return $segments;
    }
}
