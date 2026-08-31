import { EditorCheckBonusesService } from '@/modules/Roleplay/Character/Service/EditorCheckBonusesService';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';
import { abilityCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/abilityCheckAdvantagesService';
import { itemCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/Instance/itemCheckAdvantagesService';

export const editorCheckBonusesService = new EditorCheckBonusesService(
  aggregateSourceDeltasService,
  abilityCheckAdvantagesService,
  itemCheckAdvantagesService,
);
