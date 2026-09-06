import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';

/** Отложенный импорт в новый мир: upsert каталога при createSpace. */
export interface PendingRevisionImport {
  file: RevisionFile;
  label: string;
}
