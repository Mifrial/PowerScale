import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Переопределение текущего значения ресурса в бою (по ruleId версии листа). */
export interface CombatResourceOverride {
  ruleId: string;
  current: DimensionalNumberValue;
}

/**
 * Сессионные изменения листа (игра `playing`): оверлей; иначе — actual.
 * Применение к actual на stop: `resolve(approved, overlay)`.
 */
export interface GameCombatOverlay {
  gameId: number;
  entityKey: CombatEntityKey;
  kind: 'character' | 'npc';
  resources: CombatResourceOverride[];
  states: CharacterStateValue[];
  updatedAt: string;
  /** Полная рабочая копия листа из in-game редактора (см. writeOverlaySheet). */
  sheet?: CharacterVersion | null;
}
