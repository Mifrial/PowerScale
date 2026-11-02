import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { ActionOperation } from '@/modules/Roleplay/Rule/Dto/Ability/ActionOperation';
import type { CounterOp } from '@/modules/Roleplay/Rule/Dto/Ability/CounterOp';
import type { CounterThreshold } from '@/modules/Roleplay/Rule/Dto/Ability/CounterThreshold';
import type { StrikeModifier } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeModifier';

export interface ProcessStep {
  code: string;
  name: string;
  description: string;
  costs: { resource_code: string; amount: DimensionalNumberValue | number }[];
  interruption: {
    mode: 'normal' | 'emergency';
    effects?: ActionEffect[];
  };
  operations?: ActionOperation[];
  /** Режим шага; нет ключа — шаг-удар, если нет ключа operations. */
  execution?: {
    type: 'strike' | 'action' | 'none';
    action_code?: string | null;
  };
  /** Пакет удара шага; только для шага-удара. */
  strike?: StrikeModifier;
  /** Порог счётчиков: ниже — шаг недоступен. */
  requires_counters?: CounterThreshold[];
  /** Счётчики на успехе шага, применяются по порядку. */
  counter_on_success?: CounterOp[];
  /** Счётчики на провале; действуют только при failure restart_from_first. */
  counter_on_failure?: CounterOp[];
}
