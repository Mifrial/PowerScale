<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Дефолты правила «Бросок». Число кубов и граней поток не читает.
 */
final class RollMechanicPayload implements MechanicPayload
{
    /**
     * Собирает payload. Незаданное поле дефолт не подменяет.
     *
     * @param int|null $diceCount Число кубов. Поток не читает.
     * @param int|null $dieFaces Число граней. Поток не читает.
     * @param int|null $efficiency Эффективность, если у спеки нейтральные 3.
     * @param int|null $adv Преимущество правила «Бросок», если у спеки список пуст.
     * @param int|null $dieSize Размер куба, если у спеки нейтральный 0.
     * @param array<int, string>|null $subMechanics Коды семейства, которые бросок включает всегда.
     *
     * @return void
     */
    public function __construct(
        private readonly ?int $diceCount = null,
        private readonly ?int $dieFaces = null,
        private readonly ?int $efficiency = null,
        private readonly ?int $adv = null,
        private readonly ?int $dieSize = null,
        private readonly ?array $subMechanics = null,
    ) {
    }

    /**
     * Число кубов из payload.
     *
     * @return int|null Число или null.
     */
    public function getDiceCount(): ?int
    {
        return $this->diceCount;
    }

    /**
     * Число граней из payload.
     *
     * @return int|null Число или null.
     */
    public function getDieFaces(): ?int
    {
        return $this->dieFaces;
    }

    /**
     * Эффективность из payload.
     *
     * @return int|null Число или null.
     */
    public function getEfficiency(): ?int
    {
        return $this->efficiency;
    }

    /**
     * Преимущество из payload.
     *
     * @return int|null Дельта или null.
     */
    public function getAdv(): ?int
    {
        return $this->adv;
    }

    /**
     * Размер куба из payload.
     *
     * @return int|null Размер или null.
     */
    public function getDieSize(): ?int
    {
        return $this->dieSize;
    }

    /**
     * Коды семейства, всегда входящие в бросок.
     *
     * @return array<int, string>|null Коды или null, если фильтр не задан.
     */
    public function getSubMechanics(): ?array
    {
        return $this->subMechanics;
    }
}
