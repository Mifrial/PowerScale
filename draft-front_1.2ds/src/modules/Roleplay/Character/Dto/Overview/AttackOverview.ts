import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface AttackOverview {
  itemRuleId: string;
  itemName: string;
  itemHref: string;
  profileType: 'strike' | 'throw' | 'shoot';
  profileTypeLabel: string;
  distanceLabel: string;
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
