import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import { SPELL_SUSTAIN_STABILITY_BASE } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_SUSTAIN_STABILITY_BASE';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/** Занятость источника и тик поддержания без Vue. */
export class ActiveSpellService {
  isSourceOccupied(spells: ActiveSpell[], casterKey: CombatEntityKey, sourceKey: string): boolean {
    if (!sourceKey) {
      return false;
    }

    return spells.some((spell) => spell.casterKey === casterKey && spell.sourceKey === sourceKey);
  }

  shouldPromptSustain(spell: ActiveSpell, round: number, participantId: string): boolean {
    if (spell.casterKey !== participantId) {
      return false;
    }

    return spell.startedRound !== round || spell.startedParticipantId !== participantId;
  }

  isSourceAvailable(sourceKey: string, version: CharacterVersion | null): boolean {
    if (!sourceKey || !version) {
      return false;
    }
    if (sourceKey.startsWith('inventory:')) {
      const itemId = Number(sourceKey.slice('inventory:'.length));
      const item = version.inventory.find((entry) => entry.id === itemId);

      return Boolean(item?.equipped);
    }
    if (sourceKey.startsWith('grant:')) {
      const ruleCode = sourceKey.slice('grant:'.length);

      return version.abilities.some((ability) => ability.ruleCode === ruleCode);
    }

    return false;
  }

  stabilityOf(usedPower: DimensionalNumberValue, requiredPower: DimensionalNumberValue): DimensionalNumberValue {
    const excess = CharacteristicNumber.from(usedPower).modifyDiffTo(CharacteristicNumber.from(requiredPower));

    return CharacteristicNumber.from(SPELL_SUSTAIN_STABILITY_BASE).modifyWith(Math.max(0, excess)).value;
  }

  shouldDropFromDisruption(spell: ActiveSpell, disruptionStrength: number): boolean {
    return disruptionStrength >= DimensionalNumber.from(spell.stability).toNumber();
  }

  withSustainPower(
    spell: ActiveSpell,
    sustainPower: DimensionalNumberValue,
    requiredPower: DimensionalNumberValue,
  ): ActiveSpell {
    return {
      ...spell,
      sustainPower,
      stability: this.stabilityOf(sustainPower, requiredPower),
    };
  }

  createSustained(input: {
    gameId: number;
    casterKey: CombatEntityKey;
    spellCode: string;
    sourceKey: string;
    pathCode: string | null;
    usedPower: DimensionalNumberValue;
    requiredPower: DimensionalNumberValue;
    parameterValues: Record<string, DimensionalNumberValue>;
    appliedUpgradeCodes: string[];
    startedRound: number;
    startedParticipantId: string;
  }): ActiveSpell {
    return {
      id: `${input.gameId}:${input.casterKey}:${input.spellCode}:${input.sourceKey}`,
      gameId: input.gameId,
      casterKey: input.casterKey,
      spellCode: input.spellCode,
      sourceKey: input.sourceKey,
      pathCode: input.pathCode,
      durationType: 'sustained',
      usedPower: input.usedPower,
      sustainPower: input.usedPower,
      stability: this.stabilityOf(input.usedPower, input.requiredPower),
      parameterValues: input.parameterValues,
      appliedUpgradeCodes: input.appliedUpgradeCodes,
      startedRound: input.startedRound,
      startedParticipantId: input.startedParticipantId,
    };
  }
}
