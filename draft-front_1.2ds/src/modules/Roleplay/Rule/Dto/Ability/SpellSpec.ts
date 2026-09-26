import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { SpellValue } from '@/modules/Roleplay/Rule/Dto/Ability/SpellValue';
import type { SpellDamage } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDamage';
import type { SpellChargeSpec } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChargeSpec';
import type { SpellTargeting } from '@/modules/Roleplay/Rule/Enum/SpellTargeting';

export interface SpellSpec {
  power: SpellValue;
  control: SpellValue;
  duration: SpellDuration;
  /** Кого заклинание выбирает целью; отсутствие поля сохраняет прежнюю infer-модель. */
  targeting?: SpellTargeting;
  damage?: SpellDamage;
  charge?: SpellChargeSpec;
}
