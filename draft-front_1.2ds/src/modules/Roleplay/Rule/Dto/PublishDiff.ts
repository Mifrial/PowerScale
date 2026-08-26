import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Классификация правил черновика относительно последней опубликованной ревизии. */
export interface PublishDiff {
  /** Новые: есть в черновике, нет в published. */
  added: Rule[];
  /** Изменённые: есть в published и контент отличается. */
  changed: Rule[];
  /** Удаляемые: маркер-версия active=false в новой ревизии. */
  removed: Rule[];
}
