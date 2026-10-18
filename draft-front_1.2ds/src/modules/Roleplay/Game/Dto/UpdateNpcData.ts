import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';

/** Редактирование НПС ведущим: имя, описания, теги, видимость, полный лист. */
export interface UpdateNpcData {
  gameId?: number;
  expectedNpcActualVersion: number;
  name: string;
  shortDescription: string | null;
  fullDescription: string | null;
  tags: string[];
  visibility: SheetVisibility;
  /** Полный лист персонажа (null — только минимум: имя/описания). */
  version: CharacterVersion | null;
  /** Выборы редактора; PHP update шлёт их как choices, не CharacterVersion. */
  choices?: CharacterBuild;
  creationConfig?: CharacterCreationConfig;
  spaceId?: number;
  spaceCode?: string;
  rulesRevision?: number;
}
