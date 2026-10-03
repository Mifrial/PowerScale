<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Один отказ валидатора листа. Форма C0: код, текст, путь, стадия.
 */
final class CharacterProblem
{
    /**
     * Создаёт отказ.
     *
     * @param string $code Стабильный код шага.
     * @param string $message Текст.
     * @param string $stage Стадия C0.
     * @param string|null $path Путь поля или null.
     *
     * @return void
     */
    public function __construct(
        private readonly string $code,
        private readonly string $message,
        private readonly string $stage,
        private readonly ?string $path,
    ) {
    }

    /**
     * Код отказа.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Текст отказа.
     *
     * @return string Сообщение.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Стадия.
     *
     * @return string Стадия C0.
     */
    public function getStage(): string
    {
        return $this->stage;
    }

    /**
     * Путь поля.
     *
     * @return string|null Путь или null.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }
}
