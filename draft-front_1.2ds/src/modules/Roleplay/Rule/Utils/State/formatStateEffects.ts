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

export function stateEffectLabel(effect: StateEffect, nameByCode: (code: string) => string): string {
  if (effect.type === 'characteristic_modify') {
    const amount = `${effect.amount >= 0 ? '+' : ''}${effect.amount}`;
    const per = effect.per_unit ? ' за каждую единицу состояния' : '';

    return `Модификатор: ${nameByCode(effect.characteristic_code)} ${amount}${per}`;
  }
  if (effect.type === 'resource_limit_modify') {
    const amount = `${effect.amount >= 0 ? '+' : ''}${effect.amount}`;
    const per = effect.per_unit ? ' за единицу' : '';

    return `Лимит ресурса ${nameByCode(effect.resource_code)} ${amount}${per}`;
  }
  if (effect.type === 'resource_limit_set') {
    return `Лимит ресурса ${nameByCode(effect.resource_code)} = ${effect.value}`;
  }
  if (effect.type === 'check_advantage') {
    const amount = `${effect.amount >= 0 ? '+' : ''}${effect.amount}`;
    const per = effect.per_unit ? ' за единицу' : '';
    const scoped = Boolean(effect.includes_hit) || (effect.characteristic_codes?.length ?? 0) > 0;
    if (!scoped) return `Помехи/преимущества на все проверки ${amount}${per}`;
    const parts: string[] = [];
    if (effect.includes_hit) parts.push('попадание');
    for (const code of effect.characteristic_codes ?? []) parts.push(nameByCode(code));
    if (effect.characteristic_codes?.length) parts.push('производные');

    return `Помехи/преимущества на ${parts.join(', ')} ${amount}${per}`;
  }

  return dotEffectLabel(effect, nameByCode);
}
