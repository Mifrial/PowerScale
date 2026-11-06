<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Map\Service;

use Mifrial\Roleplay\Map\Dto\MapLineRecord;
use Mifrial\Roleplay\Map\Exception\MapInvalidException;

/**
 * Запись лент снимка карты.
 */
final class MapLineSaveWriter
{
    /**
     * Создаёт писатель.
     *
     * @param MapLineStore $lineStore Ленты.
     *
     * @return void
     */
    public function __construct(
        private readonly MapLineStore $lineStore,
    ) {
    }

    /**
     * Пишет ленты по вариантам.
     *
     * @param array<int, int> $variantMap Id снимка → реальный id варианта.
     * @param array<int, array<string, mixed>> $lines Ленты снимка.
     *
     * @return void
     *
     * @throws MapInvalidException Если ссылка не разрешилась.
     */
    public function write(array $variantMap, array $lines): void
    {
        $byVariant = [];
        foreach ($lines as $line) {
            $byVariant[$this->mapped($variantMap, $line['variantId'])][] = $line;
        }

        foreach ($variantMap as $realVariantId) {
            $this->writeLinesOfVariant($realVariantId, $byVariant[$realVariantId] ?? []);
        }
    }

    /**
     * Ленты одного варианта.
     *
     * @param int $variantId Реальный вариант.
     * @param array<int, array<string, mixed>> $payload Ленты.
     *
     * @return void
     *
     * @throws MapInvalidException Если лента неизвестна.
     */
    private function writeLinesOfVariant(int $variantId, array $payload): void
    {
        $existing = $this->lineStore->findLines($variantId);
        $existingById = $this->index($existing);
        $kept = [];
        foreach ($payload as $line) {
            $kept[$this->saveLine($variantId, $line, $existingById)] = true;
        }

        foreach ($existing as $lineRecord) {
            if (!isset($kept[$lineRecord->getId()])) {
                $this->lineStore->deleteLine($lineRecord->getId());
            }
        }
    }

    /**
     * Одна лента.
     *
     * @param int $variantId Вариант.
     * @param array<string, mixed> $line Лента.
     * @param array<int, true> $existingById Известные id.
     *
     * @return int Реальный id.
     *
     * @throws MapInvalidException Если лента неизвестна.
     */
    private function saveLine(int $variantId, array $line, array $existingById): int
    {
        $fields = $this->fields($variantId, $line);
        if ($line['id'] > 0) {
            if (!isset($existingById[$line['id']])) {
                throw new MapInvalidException('Map line is unknown');
            }

            $this->lineStore->updateLine($line['id'], $fields);

            return $line['id'];
        }

        return $this->lineStore->addLine(
            $variantId,
            $fields['color'],
            $fields['speed'],
            $fields['points'],
            $fields['widths'],
            $fields['handles'],
            $fields['segments'],
            $fields['label'],
            $fields['is_present'],
            $fields['sort_order'],
        );
    }

    /**
     * Поля ленты.
     *
     * @param int $variantId Вариант.
     * @param array<string, mixed> $line Лента.
     *
     * @return array<string, mixed> Поля.
     */
    private function fields(int $variantId, array $line): array
    {
        return [
            'variant_id' => $variantId,
            'color' => $line['color'],
            'speed' => $line['speed'],
            'points' => $line['points'],
            'widths' => $line['widths'],
            'handles' => $line['handles'],
            'segments' => $line['segments'],
            'label' => $line['label'],
            'is_present' => $line['isPresent'],
            'sort_order' => $line['sortOrder'],
        ];
    }

    /**
     * Существующие id.
     *
     * @param array<int, MapLineRecord> $records Ленты.
     *
     * @return array<int, true> Id → true.
     */
    private function index(array $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[$record->getId()] = true;
        }

        return $ids;
    }

    /**
     * Разрешает id снимка в реальный.
     *
     * @param array<int, int> $idMap Карта.
     * @param int $id Id снимка.
     *
     * @return int Реальный id.
     *
     * @throws MapInvalidException Если ссылки нет.
     */
    private function mapped(array $idMap, int $id): int
    {
        if (!isset($idMap[$id])) {
            throw new MapInvalidException('Map snapshot has an unresolved reference');
        }

        return $idMap[$id];
    }
}
