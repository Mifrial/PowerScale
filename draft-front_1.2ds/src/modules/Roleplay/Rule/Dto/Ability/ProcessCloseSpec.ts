import type { CounterThreshold } from '@/modules/Roleplay/Rule/Dto/Ability/CounterThreshold';
import type { StrikeModifier } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeModifier';

/** Закрытие процесса-родителя: порог счётчиков и пакет удара закрывающей атаки. */
export interface ProcessCloseSpec {
  requires_counters?: CounterThreshold[];
  strike?: StrikeModifier;
}
