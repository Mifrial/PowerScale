import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';
import type { SpellDamage } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDamage';
import type { SpellChargeSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChargeSpec';

export interface SpellSpec {
  power: SpellValue;
  control: SpellValue;
  duration: SpellDuration;
  damage?: SpellDamage;
  charge?: SpellChargeSpec;
}
