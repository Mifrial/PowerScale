import { CharacterBuildService } from '@/modules/Roleplay/Character/Service/CharacterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { itemModifierService } from '@/modules/Roleplay/Rule/init';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';

export const characterBuildService = new CharacterBuildService(
  characterEditorService,
  itemModifierService,
  weaponProficiencyService,
  racialInnateGearService,
);
