import type { RevisionFileProblemStage } from '@/modules/Roleplay/RuleSpace/Enum/RevisionFileProblemStage';

export interface RevisionFileProblem {
  code: string;
  path: string;
  stage: RevisionFileProblemStage;
  message: string;
}
