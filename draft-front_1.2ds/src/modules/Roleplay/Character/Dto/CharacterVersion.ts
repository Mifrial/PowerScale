import type { CharacteristicValue } from '@/modules/Roleplay/Character/Dto/CharacteristicValue';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterPointsState } from '@/modules/Roleplay/Character/Dto/CharacterPointsState';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { CustomRuleEntry } from '@/modules/Roleplay/Character/Dto/CustomRuleEntry';

export interface CharacterVersion {
  name: string;
  shortDescription: string | null;
  fullDescription: string | null;
  spaceCode: string;
  rulesRevision: number;
  raceRuleId: string | null;
  characteristics: CharacteristicValue[];
  resources: ResourceValue[];
  abilities: CharacterAbility[];
  points: CharacterPointsState;
  money: number;
  /** Годы персонажа (ступень возраста → ОЛ/лимит); null — возраст не выбран. */
  ageYears: number | null;
  inventory: InventoryItem[];
  states: CharacterStateValue[];
  senses: CharacterSenseValue[];
  /**
   * Кастомные правила («Уникальные правила»): текстовые записи, выданные ведущим «на ходу».
   * Не привязаны к правилу ревизии, поэтому переносятся миграцией как есть (спред версии)
   * и не резолвятся в листе/чатах/бюджетах. Старые версии — без поля.
   */
  customRules?: CustomRuleEntry[];
  /**
   * Лимиты создания (null — лимит не задан). Сохраняются, чтобы edit восстанавливал
   * бюджеты ОС/денег (ОЛ — из возраста, ОР — points.orTotal). Старые версии — без поля.
   */
  budgets?: { osTotal: number | null; moneyBudget: number | null };
}
