import type { KnowledgeFieldSlot } from '@/modules/Roleplay/Rule/Dto/Knowledge/KnowledgeFieldSlot';

/** Тип знания: схема слотов для экземпляра `znanie`. */
export interface KnowledgeField {
  code: string;
  name: string;
  slots: KnowledgeFieldSlot[];
}
