export const ADVANTAGE_SOURCE_MANUAL = 'manual';
export const ADVANTAGE_SOURCE_ROLL = 'roll';
export const ADVANTAGE_SOURCE_TOOL = 'tool';
export const ADVANTAGE_SOURCE_STATE = 'state';
export const ADVANTAGE_SOURCE_APPEARANCE = 'appearance';
export const ADVANTAGE_SOURCE_CIRCUMSTANCES = 'circumstances';
export const ADVANTAGE_SOURCE_ACTION = 'action';
export const ADVANTAGE_SOURCE_MULTI_ATTACK = 'multi_attack';
export const ADVANTAGE_SOURCE_TRAINING = 'training';
export const ADVANTAGE_SOURCE_PREPARATION = 'preparation';

/**
 * Подписи встроенных источников для превью. Источник истины — серверный
 * `source_label`; живому правилу подпись даёт имя правила, а не эта карта.
 */
export const ADVANTAGE_SOURCE_LABELS: Readonly<Record<string, string>> = {
  [ADVANTAGE_SOURCE_MANUAL]: 'вручную',
  [ADVANTAGE_SOURCE_ROLL]: 'Бросок',
  [ADVANTAGE_SOURCE_TOOL]: 'инструмент',
  [ADVANTAGE_SOURCE_CIRCUMSTANCES]: 'Обстоятельства',
  [ADVANTAGE_SOURCE_ACTION]: 'Действие',
  [ADVANTAGE_SOURCE_MULTI_ATTACK]: 'множественная атака',
  [ADVANTAGE_SOURCE_TRAINING]: 'тренировки',
  [ADVANTAGE_SOURCE_PREPARATION]: 'Подготовка',
  distance: 'Штраф дистанции',
  flank: 'Фланговая атака',
  item: 'предмет',
  'damage-type-injury': 'тип урона',
};
