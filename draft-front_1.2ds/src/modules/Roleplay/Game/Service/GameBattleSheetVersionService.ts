import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameBattleSheet } from '@/modules/Roleplay/Game/Dto/GameBattleSheet';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterHttpViewService } from '@/modules/Roleplay/Character/init';
import { gamePhpMode } from '@/modules/Roleplay/Game/Service/Instance/gamePhpMode';

/**
 * Собирает CharacterVersion из PHP sheets[] для карточки P4.
 */
export class GameBattleSheetVersionService {
  toVersion(row: GameBattleSheet, context?: { spaceId: number; spaceCode: string; rules: Rule[] }): CharacterVersion {
    const mapped = characterHttpViewService.versionOf({
      name: row.name,
      spaceCode: context?.spaceCode ?? '',
      rulesRevision: row.rulesRevision ?? 0,
      choices: row.choices,
      sheet: row.sheet,
    });
    const built =
      context && context.rules.length > 0 && !gamePhpMode.isEnabled()
        ? this.withCombatRuntime(this.materialized(mapped, context.spaceId, context.rules), mapped)
        : mapped;

    return built;
  }

  /** Повторная материализация innate и закупок, когда ревизия доехала позже листа. */
  rematerialize(
    version: CharacterVersion,
    context: { spaceId: number; spaceCode: string; rules: Rule[] },
  ): CharacterVersion {
    if (context.rules.length === 0 || gamePhpMode.isEnabled()) return version;

    return this.withCombatRuntime(this.materialized(version, context.spaceId, context.rules), version);
  }

  private materialized(version: CharacterVersion, spaceId: number, rules: Rule[]): CharacterVersion {
    try {
      return characterHttpViewService.materializeKeepingRuntime(version, spaceId, rules);
    } catch {
      return version;
    }
  }

  private withCombatRuntime(built: CharacterVersion, document: CharacterVersion): CharacterVersion {
    return {
      ...built,
      states: document.states,
    };
  }
}
