import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacteristicPurchase } from '@/modules/Roleplay/Character/Dto/Editor/CharacteristicPurchase';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';

/**
 * Рабочие «выборы» редактора персонажа — источник истины, из них выводится CharacterVersion
 * (фронт — активные расчёты, ТР §7). В отличие от версии, хранит выборы (расу, покупки
 * характеристик, способности с уровнями), а не вычисленные итоги.
 */
export interface CharacterBuild {
  name: string;
  shortDescription: string | null;
  fullDescription: string | null;
  spaceId: number;
  spaceCode: string;
  rulesRevision: number;
  raceRuleId: string | null;
  /** Закупки характеристик (режим purchased расы). */
  characteristicPurchases: CharacteristicPurchase[];
  abilities: CharacterAbility[];
  resources: ResourceValue[];
  inventory: InventoryItem[];
  states: CharacterStateValue[];
  money: number;
  /** Годы персонажа (тип правила 'age'); null — возраст не выбран. */
  ageYears: number | null;
  /** Итог ОЛ (из возраста; сохраняется при копировании как фолбэк для старых версий). */
  olTotal: number;
}
