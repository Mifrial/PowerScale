import type { RevisionFileKeyword } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileKeyword';
import type { RevisionFileMechanic } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileMechanic';

/** План HTTP upsert used-снимков, без Pinia. */
export interface RevisionFileCatalogPlan {
  keywordCreates: RevisionFileKeyword[];
  keywordUpdates: { id: number; name: string; description: string }[];
  keywordDeactivates: number[];
  keywordUnchangedCount: number;
  cannotReactivate: string[];
  mechanicCreates: RevisionFileMechanic[];
  mechanicUpdates: { id: number; name: string; description: string }[];
  mechanicUnchangedCount: number;
}
