<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;

/**
 * Сборка сервисов поддержания заклинаний.
 */
final class GameActiveSpellWiring
{
    public readonly GameActiveSpellRepository $spells;

    public readonly GameActiveSpellFacts $facts;

    public readonly GameActiveSpellChat $chat;

    public readonly GameActiveSpellView $view;

    public readonly GameActiveSpellSustain $sustain;

    public readonly GameActiveSpellDrop $drop;

    public readonly GameActiveSpellTurnStart $turnStart;

    /**
     * Создаёт сервисы.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeShared $shared Общие сервисы удара.
     * @param GameTurnAccess $access Право хода.
     *
     * @return void
     */
    public function __construct(
        IServiceLocator $serviceLocator,
        GameStrikeRules $rules,
        GameStrikeShared $shared,
        GameTurnAccess $access,
    ) {
        $ports = new GameStrikePorts($serviceLocator);
        $write = new GameStrikeWriteWiring($serviceLocator);
        $characters = new GameStrikeCharacterPorts($serviceLocator);
        $deviation = new GameSpellDeviation((new GameStrikeMechanicPorts($serviceLocator))->mechanics());
        $durations = new GameSpellDurationRules();
        $castFacts = new GameSpellCastFacts(
            $rules,
            $shared->sheets,
            $characters->characterSheets(),
            $write->availability($rules),
            new GameActionResourceResolver(),
            new GameActionHands($rules, $shared->repos->characters, $shared->repos->npcs),
            $deviation,
            $shared->repos->activeSpells,
        );
        $source = new GameSpellSource(new CharacterAbilityGrantReader(
            new CharacterDonorGrants(),
            new CharacterRaceAbilityCodes(),
        ));

        $this->spells = $shared->repos->activeSpells;
        $this->facts = new GameActiveSpellFacts(
            $this->spells,
            $rules,
            $shared->sheets,
            $castFacts,
            $source,
            $durations,
        );
        $this->chat = new GameActiveSpellChat($ports->chats(), $shared->chat);
        $this->view = new GameActiveSpellView();
        $charge = new GameSpellCharge();
        $activeCharge = new GameActiveSpellCharge($charge, $shared->sheetWrites, $this->chat);
        $stability = new GameSpellStability();
        $this->sustain = new GameActiveSpellSustain(
            $this->facts,
            $this->spells,
            $write->perform($rules),
            $this->chat,
            $access,
            new GameSpellPowerCap(new GameSpellSourcePower(
                $deviation,
                new GameSpellSourceBonus(new FormulaEvaluations()),
            )),
            $durations,
            $this->view,
            $charge,
            $shared->sheetWrites,
            $activeCharge,
            new GameActionResourceResolver(),
            new GameSpellMana(),
            $stability,
        );
        $this->drop = new GameActiveSpellDrop($this->facts, $this->spells, $this->chat, $access, $activeCharge);
        $this->turnStart = new GameActiveSpellTurnStart(
            $this->spells,
            $this->facts,
            $this->chat,
            $this->view,
            $activeCharge,
            $stability,
        );
    }
}
