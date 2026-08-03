import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';

export interface InheritedAbilityRef extends RaceAbilityRef {
  /** Название вида/подвида, из которого способность наследуется. */
  fromName: string;
}
