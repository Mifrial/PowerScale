/** Данные выдачи кастомного правила («Уникальные правила») персонажу ведущим «на ходу». */
export interface AddCustomRuleData {
  kind: 'item' | 'ability';
  name: string;
  description: string | null;
}
