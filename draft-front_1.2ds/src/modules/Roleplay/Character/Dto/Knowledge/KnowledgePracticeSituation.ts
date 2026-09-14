import type { CharacterKnowledgeSlot } from '@/modules/Roleplay/Character/Dto/CharacterKnowledgeSlot';

/** Якоря ворот практики: вид цели и регион места. */
export interface KnowledgePracticeSituation {
  targetSpecies?: CharacterKnowledgeSlot;
  placeRegion?: CharacterKnowledgeSlot;
}
