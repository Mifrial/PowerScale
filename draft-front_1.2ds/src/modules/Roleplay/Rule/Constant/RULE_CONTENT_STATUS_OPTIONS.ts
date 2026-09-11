import type { RULE_CONTENT_STATUSES } from '@/modules/Roleplay/Rule/Constant/RULE_CONTENT_STATUSES';

/** Подписи редакционного contentStatus для селекта и чипа. */
export const RULE_CONTENT_STATUS_OPTIONS: { title: string; value: (typeof RULE_CONTENT_STATUSES)[number] }[] = [
  { title: 'Не рабочий', value: 'broken' },
  { title: 'Нуждается в работе', value: 'needs_work' },
  { title: 'Актуальный', value: 'ready' },
];
