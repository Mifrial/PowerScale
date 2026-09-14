import type { CharacterKnowledgeSlot } from '@/modules/Roleplay/Character/Dto/CharacterKnowledgeSlot';

/** Доп. поля при добавлении экземпляра знания. */
export interface AbilityInstanceAddPayload {
  fieldCode?: string | null;
  slots?: Record<string, CharacterKnowledgeSlot>;
}
