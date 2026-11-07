<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;

/**
 * Подготовка одиночного удара к расчёту.
 */
final class GameStrikeSinglePrepare
{
    /**
     * Создаёт подготовку удара.
     *
     * @param GameStrikeSingleLoad $load Загрузка строки.
     * @param GameStrikeSingleStart $start Защита и манёвр.
     * @param GameStrikeSingleDefender $defender Защитник и траты.
     * @param GameStrikeSingleAdvantages $advantages Преимущества и бросок.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSingleLoad $load,
        private readonly GameStrikeSingleStart $start,
        private readonly GameStrikeSingleDefender $defender,
        private readonly GameStrikeSingleAdvantages $advantages,
    ) {
    }

    /**
     * Готовит удар или возвращает ранний итог окна.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array<string, mixed>|null $resume Решение окна цели.
     *
     * @return array<string, mixed>|null Ранний итог.
     */
    public function prepare(GameStrikeSingleRun $run, ?array $resume): ?array
    {
        $this->load->load($run, $resume);
        $early = $this->start->start($run);
        if ($early !== null) {
            return $early;
        }

        $this->defender->prepare($run);
        $this->advantages->prepare($run);

        return null;
    }
}
