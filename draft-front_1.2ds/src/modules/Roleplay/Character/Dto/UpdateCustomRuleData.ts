/**
 * Обновление записи кастомного правила («Уникальные правила»): правка текста, пометка
 * «заменено на правило» (`status: 'deprecated'` + `replacedWithRuleCode`).
 */
export interface UpdateCustomRuleData {
  name?: string;
  description?: string | null;
  status?: 'active' | 'deprecated';
  replacedWithRuleCode?: string;
}
