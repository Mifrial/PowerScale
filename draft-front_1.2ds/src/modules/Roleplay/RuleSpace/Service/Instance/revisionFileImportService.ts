import { RevisionFileImportService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileImportService';
import { revisionFileService } from '@/modules/Roleplay/RuleSpace/Service/Instance/revisionFileService';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';
import { getRuleSpaceApi } from '@/modules/Roleplay/RuleSpace/init';
import { RULE_CONTENT_STATUSES, RULE_TYPE_LABELS } from '@/modules/Roleplay/Rule/init';

const knownStatuses = new Set<string>(RULE_CONTENT_STATUSES);

export const revisionFileImportService = new RevisionFileImportService(
  revisionFileService,
  abilitySectionTreeService,
  (spaceId, signal) => getRuleSpaceApi().getRevisions(spaceId, signal),
  (spaceId, revision, signal) => getRuleSpaceApi().getRevision(spaceId, revision, signal),
  (type) => type in RULE_TYPE_LABELS,
  (status) => knownStatuses.has(status),
);
