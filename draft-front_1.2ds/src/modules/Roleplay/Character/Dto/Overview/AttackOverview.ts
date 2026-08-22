export interface AttackOverview {
  itemRuleId: string;
  itemName: string;
  itemHref: string;
  profileType: 'strike' | 'throw' | 'shoot';
  profileTypeLabel: string;
  distanceLabel: string;
  accuracyLabel: string;
  damageLabel: string;
  penetrationLabel: string;
  /** Человекочитаемая формула урона (напр. «Сила» для урона от характеристики). */
  damageFormula: string;
  /** Человекочитаемая формула пробития. */
  penetrationFormula: string;
  isResolved: boolean;
}
