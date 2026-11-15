import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';
import type { SceneSaveElement } from '@/modules/Roleplay/Scene/Dto/SceneSaveElement';

/**
 * Элемент черновика: снимок плюс внешний id ассета для панели свойств.
 *
 * `assetContentBox` — видимая рамка картинки, подсказка отрисовки. Она не
 * сохраняется в локации: файл неизменяем, поэтому рамка приходит с сервера при
 * загрузке и обновляется при выборе ассета.
 */
export interface SceneDraftElement extends SceneSaveElement {
  assetPublicId: string | null;
  assetContentBox: ImageContentBox | null;
}
