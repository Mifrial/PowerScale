import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface DefenseLineOverview {
  kind: 'defense' | 'resistance';
  value: number;
  /** Размерная запись для UI (как прочность). */
  valueLabel: string;
  durability: number;
  /** Источник защиты (source_code): защиты одного источника не суммируются. */
  sourceCode: string | null;
  /** Имя источника из каталога (напр. «Доспех», «Поддоспешник»). */
  sourceLabel: string | null;
  damageTypeLabel: string | null;
  /** Дательный падеж типа урона для надписи сопротивления (напр. «рубящему урону»). */
  damageTypeDative: string | null;
  /** Код типа урона линии сопротивления; у защиты — null. */
  damageTypeCode: string | null;
}

export interface DefenseArmorOverview {
  itemRuleCode: string;
  itemName: string;
  href: string;
  lines: DefenseLineOverview[];
  /** Ступени защиты только внутри этого предмета (надёжность слоёв самого доспеха). */
  tiers: DefenseTierOverview[];
}

export interface DefenseShieldOverview {
  itemRuleCode: string;
  itemName: string;
  href: string;
  defense: string;
  efficiency: string;
  efficiencyValue: DimensionalNumberValue;
}

/**
 * Ступень защиты по надёжности: совокупная защита, которая уцелеет, пока враг не потратит
 * на неё `durability`. Надёжность = сколько «РУ Атаки» нужно вложить, чтобы проигнорировать
 * слой сверх этой ступени (уцелевшие слои имеют надёжность ≥ threshold).
 */
export interface DefenseTierOverview {
  /** Требуемая надёжность: считаем только слои с надёжностью ≥ threshold. */
  threshold: number;
  /** Совокупная защита на этой ступени (по источникам — максимум, источники суммируются). */
  defense: number;
}

export interface DefenseOverview {
  armor: DefenseArmorOverview[];
  /** Итог защит: из группы одного источника берётся максимум, группы суммируются. */
  constantDefense: number;
  /** Ступени защиты по надёжности, отсортированы по возрастанию threshold. */
  tiers: DefenseTierOverview[];
  shield: DefenseShieldOverview | null;
}
