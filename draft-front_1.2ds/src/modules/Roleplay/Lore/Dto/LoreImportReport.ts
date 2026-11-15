import type { LoreImportCounts } from '@/modules/Roleplay/Lore/Dto/LoreImportCounts';
import type { LoreImportWarning } from '@/modules/Roleplay/Lore/Dto/LoreImportWarning';

/** Отчёт импорта кодекса. */
export interface LoreImportReport {
  worldCode: string;
  dryRun: boolean;
  created: LoreImportCounts;
  updated: LoreImportCounts;
  skipped: LoreImportCounts;
  warnings: LoreImportWarning[];
}
