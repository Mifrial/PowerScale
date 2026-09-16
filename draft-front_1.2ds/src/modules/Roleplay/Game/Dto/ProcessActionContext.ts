import type { ProcessSession } from '@/modules/Roleplay/Game/Dto/ProcessSession';
import type { ActionOperationRequest } from '@/modules/Roleplay/Game/Dto/ActionOperationRequest';

export interface ProcessActionContext {
  session: ProcessSession;
  stepCode: string;
  operationRequests?: ActionOperationRequest[];
  /** Закрытие Комбо чужой атакой (Раскрытие): пакет Завершения, процесс конец. */
  comboClose?: boolean;
}
