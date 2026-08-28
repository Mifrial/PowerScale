import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';

export interface ProcessActionContext {
  session: ProcessSession;
  stepCode: string;
}
