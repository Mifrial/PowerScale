import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface AttackOverview {
  itemRuleId: string;
  itemName: string;
  itemHref: string;
  profileType: 'strike' | 'throw' | 'shoot';
  /** Индекс профиля в описании оружия; нужен, когда у предмета несколько профилей одного типа. */
  profileIndex?: number;
  profileTypeLabel: string;
  distanceLabel: string;
  /** Верх профиля (range, иначе distance), в ипари. */
  reach: number;
  /** Нижняя граница дистанции профиля, в ипари. */
  minDistance: number;
  /** Дальнобойность (шаг полос и силы действия). */
  falloff: DimensionalNumberValue;
  accuracyLabel: string;
  /** Сырая точность профиля (efficiency атакующего). */
  accuracy: DimensionalNumberValue;
  damageLabel: string;
  penetrationLabel: string;
  /** Человекочитаемая формула урона (напр. «Сила» для урона от характеристики). */
  damageFormula: string;
  /** Человекочитаемая формула пробития. */
  penetrationFormula: string;
  isResolved: boolean;
  /** Код типа урона профиля; null — без типа. */
  damageTypeCode: string | null;
  /** Посчитанный урон профиля. */
  damage: DimensionalNumberValue;
  /** Посчитанное пробитие профиля. */
  penetration: DimensionalNumberValue;
}
