import type { CharacterKnowledgeSlot } from '@/modules/Roleplay/Character/Dto/CharacterKnowledgeSlot';

/** Ситуация проверки знания: тип, слоты якоря, полоса 1–3. */
export interface KnowledgeCheckSituation {
  fieldCode: string;
  slots: Record<string, CharacterKnowledgeSlot>;
  band: number;
}
