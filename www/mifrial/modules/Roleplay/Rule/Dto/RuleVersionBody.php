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

    /**
     * Создаёт тело снимка.
     *
     * @param string $type Код типа.
     * @param string $name Подпись.
     * @param string $description Текст.
     * @param array<string|int, mixed> $spec Type-specific JSON.
     * @param array<int, int> $keywordIds Id признаков.
     * @param int|null $mechanicId Поставка механики.
     * @param array<string|int, mixed> $mechanicPayload Payload механики.
     * @param string $contentStatus Редакционный статус.
     *
     * @return void
     *
     * @throws RuleInvalidException Если поля пусты, дубль id или payload без механики.
     */
    public function __construct(
        string $type,
        string $name,
        private readonly string $description,
        private readonly array $spec,
        array $keywordIds,
        private readonly ?int $mechanicId,
        private readonly array $mechanicPayload,
        string $contentStatus,
    ) {
        $this->keywordIds = $this->uniqueKeywordIds($keywordIds);
        $this->type = trim($type);
        $this->name = trim($name);
        $this->contentStatus = trim($contentStatus);
        if ($this->type === '' || $this->name === '' || $this->contentStatus === '') {
            throw new RuleInvalidException('Rule body fields must not be empty');
        }

        if ($this->mechanicId === null && $this->mechanicPayload !== []) {
            throw new RuleInvalidException('Mechanic payload requires mechanic id');
        }
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
     * Поставка механики.
     *
     * @return int|null Id или null.
     */
    public function getMechanicId(): ?int
    {
        return $this->mechanicId;
    }

    /**
     * Payload механики.
     *
     * @return array<string|int, mixed> JSON.
     */
    public function getMechanicPayload(): array
    {
        return $this->mechanicPayload;
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
}
