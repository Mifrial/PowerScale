<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

/**
 * Опции набора активных механик: срез по кодам семейства и доп. коды правил.
 */
final class ResolveActiveOptions
{
    /**
     * Собирает опции. Оба списка null — без фильтра и без доп. кодов.
     *
     * @param array<int, string>|null $includeCodes Коды семейства механики; null — все.
     * @param array<int, string>|null $extraRuleCodes Коды правил вне фильтра includeCodes.
     *
     * @return void
     */
    public function __construct(
        private readonly ?array $includeCodes = null,
        private readonly ?array $extraRuleCodes = null,
    ) {
    }

    /**
     * Коды семейства, которые проходят фильтр.
     *
     * @return array<int, string>|null Коды или null, если фильтра нет.
     */
    public function getIncludeCodes(): ?array
    {
        return $this->includeCodes;
    }

    /**
     * Коды правил, добавляемые мимо фильтра.
     *
     * @return array<int, string>|null Коды или null.
     */
    public function getExtraRuleCodes(): ?array
    {
        return $this->extraRuleCodes;
    }
}
