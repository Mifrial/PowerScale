import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ProblemEntry } from '@/modules/Roleplay/Rule/Dto/ProblemEntry';

/** Результат подготовки публикации черновика: классификация и проблемы. */
export interface PublishSummary {
  added: Rule[];
  changed: Rule[];
  problems: ProblemEntry[];
  spaceErrors: string[];
}
