import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** План наложения файла на последнюю опубликованную ревизию цели. */
export interface RevisionFileImportDiff {
  added: Rule[];
  changed: Rule[];
  unchangedCount: number;
  removedCodes: string[];
}
