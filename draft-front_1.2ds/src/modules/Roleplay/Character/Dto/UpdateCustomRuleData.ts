/**
 * Обновление записи кастомного правила («Уникальные правила»): правка текста, пометка
 * «заменено на правило» (`status: 'deprecated'` + `replacedWithRuleId`).
 */
export interface UpdateCustomRuleData {
  name?: string;
  description?: string | null;
  status?: 'active' | 'deprecated';
  replacedWithRuleId?: string;
}
