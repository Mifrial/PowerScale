import { CharacterEditorService } from '@/modules/Roleplay/Character/Service/CharacterEditorService';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import {
  RaceSpecService,
  itemModifierService,
  checkResolutionService,
  derivedCharacteristicService,
} from '@/modules/Roleplay/Rule/init';
import { mechanicEngine } from '@/modules/Roleplay/Mechanic/init';
import { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';

export const characterEditorService = new CharacterEditorService(
  new FormulaEvaluationService(),
  new RaceSpecService(),
  racialInnateGearService,
  derivedCharacteristicService,
  mechanicEngine,
  itemModifierService,
  checkResolutionService,
  weaponProficiencyService,
);
