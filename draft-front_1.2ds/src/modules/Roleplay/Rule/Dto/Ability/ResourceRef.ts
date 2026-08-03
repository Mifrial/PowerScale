import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef';

export interface ResourceRef extends CharacteristicRef {
  isDimensional?: boolean;
}
