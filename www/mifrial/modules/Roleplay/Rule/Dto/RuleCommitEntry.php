<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto;

use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;

/**
 * Пункт commit состава правил.
 */
final class RuleCommitEntry
{
    /**
     * Создаёт пункт.
     *
     * @param int|null $versionId Keep.
     * @param string|null $code Put.
     * @param RuleVersionBody|null $body Put.
     * @param bool $active Marker снимка.
     *
     * @return void
     */
    private function __construct(
        private readonly ?int $versionId,
        private readonly ?string $code,
        private readonly ?RuleVersionBody $body,
        private readonly bool $active,
    ) {
    }

    /**
     * Существующий экземпляр в новом составе.
     *
     * @param int $versionId Id версии.
     *
     * @return self Keep.
     *
     * @throws RuleInvalidException Если id меньше 1.
     */
    public static function keep(int $versionId): self
    {
        if ($versionId < 1) {
            throw new RuleInvalidException('Keep version id is invalid');
        }

        return new self($versionId, null, null, true);
    }

    /**
     * Identity по code: create или change.
     *
     * @param string $code Семантический ключ.
     * @param RuleVersionBody $body Полный снимок.
     * @param bool $active Marker.
     *
     * @return self Put.
     *
     * @throws RuleInvalidException Если code пуст.
     */
    public static function put(string $code, RuleVersionBody $body, bool $active = true): self
    {
        $trimmedCode = trim($code);
        if ($trimmedCode === '') {
            throw new RuleInvalidException('Rule code must not be empty');
        }

        return new self(null, $trimmedCode, $body, $active);
    }

    /**
     * Keep-версия или null.
     *
     * @return int|null Id.
     */
    public function getVersionId(): ?int
    {
        return $this->versionId;
    }

    /**
     * Code put или null.
     *
     * @return string|null Код.
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * Тело put или null.
     *
     * @return RuleVersionBody|null Снимок.
     */
    public function getBody(): ?RuleVersionBody
    {
        return $this->body;
    }

    /**
     * Маркер активности снимка.
     *
     * @return bool Активен.
     */
    public function isActive(): bool
    {
        return $this->active;
    }
}
