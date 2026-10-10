<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля одного снимка версии, не порты сервисов.

namespace Mifrial\Roleplay\Rule\Dto;

use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Полный снимок тела экземпляра правила.
 */
final class RuleVersionBody
{
    /**
     * @var array<int, int>
     */
    private readonly array $keywordIds;

    private readonly string $type;

    private readonly string $name;

    private readonly string $contentStatus;

    private readonly string $contentNote;

    /**
     * Создаёт тело снимка.
     *
     * @param string $type Код типа.
     * @param string $name Подпись.
     * @param string $description Текст.
     * @param array<string|int, mixed> $spec Type-specific JSON.
     * @param array<int, int> $keywordIds Id признаков.
     * @param array<int, array<string, mixed>> $mechanics Список механик.
     * @param string $contentStatus Редакционный статус.
     * @param string $contentNote Комментарий разработки.
     *
     * @return void
     *
     * @throws RuleInvalidException Если поля пусты или строка механики без id.
     */
    public function __construct(
        string $type,
        string $name,
        private readonly string $description,
        private readonly array $spec,
        array $keywordIds,
        private readonly array $mechanics,
        string $contentStatus,
        string $contentNote = '',
    ) {
        $this->keywordIds = $this->uniqueKeywordIds($keywordIds);
        $this->type = trim($type);
        $this->name = trim($name);
        $this->contentStatus = trim($contentStatus);
        $this->contentNote = trim($contentNote);
        if ($this->type === '' || $this->name === '' || $this->contentStatus === '') {
            throw new RuleInvalidException('Rule body fields must not be empty');
        }

        $this->assertMechanics($this->mechanics);
    }

    /**
     * Код типа.
     *
     * @return string Тип.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Подпись.
     *
     * @return string Имя.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Описание.
     *
     * @return string Текст.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * JSON spec типа правила.
     *
     * @return array<string|int, mixed> JSON.
     */
    public function getSpec(): array
    {
        return $this->spec;
    }

    /**
     * Id признаков.
     *
     * @return array<int, int> Множество.
     */
    public function getKeywordIds(): array
    {
        return $this->keywordIds;
    }

    /**
     * Список механик снимка.
     *
     * @return array<int, array<string, mixed>> Строки mechanic_id и mechanic_payload.
     */
    public function getMechanics(): array
    {
        return $this->mechanics;
    }

    /**
     * Редакционный статус.
     *
     * @return string Статус.
     */
    public function getContentStatus(): string
    {
        return $this->contentStatus;
    }

    /**
     * Комментарий разработки.
     *
     * @return string Текст.
     */
    public function getContentNote(): string
    {
        return $this->contentNote;
    }

    /**
     * Проверяет list id без дублей.
     *
     * @param array<int, int> $keywordIds Вход.
     *
     * @return array<int, int> Множество.
     *
     * @throws RuleInvalidException Если форма или дубль.
     */
    private function uniqueKeywordIds(array $keywordIds): array
    {
        if (!array_is_list($keywordIds)) {
            throw new RuleInvalidException('Keyword ids must be a list');
        }

        $seenIds = [];
        foreach ($keywordIds as $keywordId) {
            if (!is_int($keywordId) || $keywordId < 1 || isset($seenIds[$keywordId])) {
                throw new RuleInvalidException('Keyword ids are invalid');
            }

            $seenIds[$keywordId] = true;
        }

        return $keywordIds;
    }

    /**
     * Проверяет список пар механика и payload.
     *
     * @param array<int, mixed> $mechanics Вход.
     *
     * @return void
     *
     * @throws RuleInvalidException Если форма строки неверна.
     */
    public static function assertMechanics(array $mechanics): void
    {
        if (!array_is_list($mechanics)) {
            throw new RuleInvalidException('Mechanics must be a list');
        }

        foreach ($mechanics as $row) {
            if (
                !is_array($row)
                || !isset($row['mechanic_id'])
                || !is_int($row['mechanic_id'])
                || $row['mechanic_id'] < 1
            ) {
                throw new RuleInvalidException('Mechanic id is invalid');
            }

            if (!isset($row['mechanic_payload']) || !is_array($row['mechanic_payload'])) {
                throw new RuleInvalidException('Mechanic payload requires mechanic id');
            }
        }
    }
}
