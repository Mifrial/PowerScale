import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DamageTypeHook } from '@/modules/Roleplay/Game/Dto/DamageTypeHook';
import { asDamageTypeSpec } from '@/modules/Roleplay/Rule/Utils/damageTypeSpec';
import { damageTypeHookRegistry } from '@/modules/Roleplay/Game/Service/Damage/Instance/damageTypeHookRegistry';
import { DAMAGE_TYPE_HOOK_VERSION_1 } from '@/modules/Roleplay/Rule/Constant/Damage/DAMAGE_TYPE_HOOKS';

/**
 * Хуки типа урона: attached_rule_codes → mechanic code@version из среза.
 * Неизвестная механика пропускается.
 */
export function resolveDamageTypeHooks(
  damageTypeCode: string | null | undefined,
  rules: Rule[],
  mechanics: Mechanic[],
): DamageTypeHook[] {
  if (!damageTypeCode) return [];
  const typeRule = rules.find((rule) => rule.code === damageTypeCode && rule.type === 'damage_type');
  const spec = asDamageTypeSpec(typeRule);
  if (!spec) return [];

  const hooks: DamageTypeHook[] = [];
  for (const code of spec.attached_rule_codes) {
    const attached = rules.find((rule) => rule.code === code);
    if (!attached || attached.mechanicId == null) continue;
    const mechanic = mechanics.find((entry) => entry.id === attached.mechanicId);
    if (!mechanic) continue;
    const handler = damageTypeHookRegistry.resolve(mechanic.code, mechanic.version);
    if (!handler) continue;
    const payload = attached.mechanic_payload;
    const woundMultiplier = payload?.type === 'exhaustion_wound' ? payload.multiplier : handler.defaultWoundMultiplier;
    const efficiencyDelta = payload?.type === 'injury_efficiency' ? payload.delta : handler.efficiencyDelta;

    hooks.push({
      ruleCode: attached.code,
      mechanicCode: mechanic.code,
      version: mechanic.version ?? DAMAGE_TYPE_HOOK_VERSION_1,
      phase: handler.phase,
      extraDiceFromSrDivisor: handler.extraDiceFromSrDivisor,
      efficiencyDelta,
      woundMultiplier,
    });
  }

  return hooks;
}

export function injuryHooksOf(hooks: DamageTypeHook[]): DamageTypeHook[] {
  return hooks.filter((hook) => hook.phase === 'injury');
}

export function applyHooksOf(hooks: DamageTypeHook[]): DamageTypeHook[] {
  return hooks.filter((hook) => hook.phase === 'apply');
}

export function attackHooksOf(hooks: DamageTypeHook[]): DamageTypeHook[] {
  return hooks.filter((hook) => hook.phase === 'attack');
}
