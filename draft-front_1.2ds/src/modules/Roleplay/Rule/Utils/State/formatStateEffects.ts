import type { StateEffect } from '@/modules/Roleplay/Rule/Dto/State/StateEffect';
import type { StatePeriodicity } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';
import { formatPeriodicity } from '@/modules/Roleplay/Rule/Utils/State/formatPeriodicity';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

export function periodicityLabel(value: StatePeriodicity | undefined): string {
  if (!value) return 'каждый ход';

  return formatPeriodicity(value.value, value.step);
}

export function decayLabel(value: StateDecay | undefined, nameByCode: (code: string) => string): string {
  if (!value) return '0';
  switch (value.kind) {
    case 'fixed':
      return String(value.value);
    case 'dimensional':
      return new DimensionalNumber({ base: value.base, size: value.size }).toString();
    case 'characteristic':
      return `${nameByCode(value.characteristic_code)}${value.modifier ? ` ${value.modifier >= 0 ? '+' : ''}${value.modifier}` : ''}`;
    case 'check':
      return `проверка по ${nameByCode(value.characteristic_code)}`;
  }
}

export function damageSourceLabel(damage: Extract<StateEffect, { type: 'damage_over_time' }>['damage']): string {
  return damage.kind === 'fixed' ? String(damage.amount) : 'значение состояния';
}

/** Профиль урона со временем одной строкой: «Урон: 3, каждые 2 хода, затухание 1». */
export function dotEffectLabel(
  effect: Extract<StateEffect, { type: 'damage_over_time' }>,
  nameByCode: (code: string) => string,
): string {
  const parts = [`Урон: ${damageSourceLabel(effect.damage)}`];
  parts.push(periodicityLabel(effect.periodicity));
  if (effect.decay) parts.push(`затухание ${decayLabel(effect.decay, nameByCode)}`);

  return parts.join(', ');
}
