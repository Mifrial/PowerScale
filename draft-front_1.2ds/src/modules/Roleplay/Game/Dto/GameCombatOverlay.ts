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
 * Сессионный оверлей изменений персонажа (модель версий — Баг 1, 2026-08-20). Во время сессии
 * (игра `playing`, членство approved) ЛЮБЫЕ изменения персонажа — боевые ресурсы/состояния и
 * произвольные поля листа (редактор) — пишутся сюда, игра читает `approved + оверлей`.
 * `sheet` — полная рабочая копия листа из in-game редактора; без неё оверлей несёт только боевые
 * правки (`resources`/`states`). `states` — авторитетный список состояний в бою (засевается из
 * версии при первой мутации). `updatedAt === ''` — оверлей ещё не трогали (пустая запись).
 * Применение к latest на approve: per-полевой three-way (см. reconcileVersion).
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
