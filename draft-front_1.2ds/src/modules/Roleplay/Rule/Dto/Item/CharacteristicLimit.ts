import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';

export interface CharacteristicLimit {
  characteristic_code: string;
  limit: Formula;
}
