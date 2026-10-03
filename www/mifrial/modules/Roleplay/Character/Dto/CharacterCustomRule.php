<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Своё правило листа: текст без привязки к ревизии.
 */
final class CharacterCustomRule
{
    /**
     * Создаёт запись.
     *
     * @param int|null $id Id записи или null.
     * @param string $kind Тип: item или ability.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param string $status active или deprecated.
     * @param string|null $replacedWithRuleCode Код замены или null.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $id,
        private readonly string $kind,
        private readonly string $name,
        private readonly string $description,
        private readonly string $status,
        private readonly ?string $replacedWithRuleCode,
    ) {
    }

    /**
     * Id.
     *
     * @return int|null Id или null.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Тип.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Имя.
     *
     * @return string Текст.
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
     * Статус.
     *
     * @return string Код.
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Правило, которым запись заменена.
     *
     * @return string|null Код или null.
     */
    public function getReplacedWithRuleCode(): ?string
    {
        return $this->replacedWithRuleCode;
    }
}
