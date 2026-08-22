import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';

/** Зона способности в модели редактора: предсчитанные стоимости по уровням. */
export interface EditorAbilityZone {
  zoneCode: string;
  /** Вид цены (automatic — авто-получение, не покупается). */
  kind: AbilityCost['kind'];
  /** Максимальный уровень в зоне (из AbilityCost). */
  maxLevel: number;
  /** Стоимость каждого уровня: levelCosts[i] = стоимость (i+1)-го уровня. */
  levelCosts: number[];
}
