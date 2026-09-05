import type { RevisionKind } from '@/modules/Roleplay/RuleSpace/Enum/RevisionKind';

export interface RevisionContext {
  spaceId: number | null;
  revision: number | null;
  kind: RevisionKind;
}
