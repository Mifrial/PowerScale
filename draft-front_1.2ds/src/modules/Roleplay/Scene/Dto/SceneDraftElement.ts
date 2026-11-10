import type { SceneSaveElement } from '@/modules/Roleplay/Scene/Dto/SceneSaveElement';

/** Элемент черновика: снимок плюс внешний id ассета для панели свойств. */
export interface SceneDraftElement extends SceneSaveElement {
  assetPublicId: string | null;
}
