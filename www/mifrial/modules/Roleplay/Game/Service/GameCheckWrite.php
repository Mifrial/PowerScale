<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacterActualMutations;

/**
 * Запись листа, чат и доставка проверок.
 */
final class GameCheckWrite
{
    /**
     * Создаёт порты записи.
     *
     * @param ICharacterActualMutations $mutations Порт листа.
     * @param GameCheckChat $chat Сообщения проверок.
     * @param GameConcentration $concentration Жетоны.
     * @param GameConcentrationCycle $concentrationCycle Цикл жетонов.
     * @param GameDeliverySignal $delivery Outbox события check.
     *
     * @return void
     */
    public function __construct(
        public readonly ICharacterActualMutations $mutations,
        public readonly GameCheckChat $chat,
        public readonly GameConcentration $concentration,
        public readonly GameConcentrationCycle $concentrationCycle,
        public readonly GameDeliverySignal $delivery,
    ) {
    }
}
