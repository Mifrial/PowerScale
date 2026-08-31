import { ItemCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/ItemCheckAdvantagesService';
import { itemModifierService, aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

export const itemCheckAdvantagesService = new ItemCheckAdvantagesService(
  itemModifierService,
  aggregateSourceDeltasService,
);
