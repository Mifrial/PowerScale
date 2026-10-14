import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot';

export interface HitBlockProfile {
  inventoryItemId: number;
  profileIndex: number;
  itemRuleCode: string;
  itemName: string;
  efficiency: DimensionalNumberValue;
  defense: DimensionalNumberValue;
  resistances: ResistanceSlot[];
}
