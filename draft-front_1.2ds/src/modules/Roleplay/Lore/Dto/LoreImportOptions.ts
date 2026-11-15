import type { LoreImportConflictPolicy } from '@/modules/Roleplay/Lore/Enum/LoreImportConflictPolicy';
import type { LoreImportMode } from '@/modules/Roleplay/Lore/Enum/LoreImportMode';

/** Параметры `lore.importWorld`. */
export interface LoreImportOptions {
  mode: LoreImportMode;
  targetWorldCode?: string;
  conflictPolicy: LoreImportConflictPolicy;
  dryRun?: boolean;
}
