import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';

/** Редактирование НПС ведущим: имя, описания, теги, видимость, полный лист (version). */
export interface UpdateNpcData {
  name: string;
  shortDescription: string | null;
  fullDescription: string | null;
  tags: string[];
  visibility: SheetVisibility;
  /** Полный лист персонажа (null — только минимум: имя/описания). */
  version: CharacterVersion | null;
}
