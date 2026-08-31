import type { DamageTypeHookHandler } from '@/modules/Roleplay/Game/Service/Damage/DamageTypeHookRegistry';
import {
  DAMAGE_TYPE_HOOK_MECHANIC_BLUNT_KO,
  DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_WOUND,
  DAMAGE_TYPE_HOOK_MECHANIC_INJURY_EFFICIENCY,
  DAMAGE_TYPE_HOOK_MECHANIC_INJURY_EXTRA_DICE,
  DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR,
  DAMAGE_TYPE_HOOK_VERSION_1,
} from '@/modules/Roleplay/Rule/init';

export const damageTypeHookV1: DamageTypeHookHandler[] = [
  {
    mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_INJURY_EXTRA_DICE,
    version: DAMAGE_TYPE_HOOK_VERSION_1,
    phase: 'injury',
    extraDiceFromSrDivisor: 2,
  },
  {
    mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_INJURY_EFFICIENCY,
    version: DAMAGE_TYPE_HOOK_VERSION_1,
    phase: 'injury',
    efficiencyDelta: -1,
  },
  {
    mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR,
    version: DAMAGE_TYPE_HOOK_VERSION_1,
    phase: 'attack',
  },
  {
    mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN,
    version: DAMAGE_TYPE_HOOK_VERSION_1,
    phase: 'apply',
  },
  {
    mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_WOUND,
    version: DAMAGE_TYPE_HOOK_VERSION_1,
    phase: 'apply',
    defaultWoundMultiplier: 1,
  },
  {
    mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS,
    version: DAMAGE_TYPE_HOOK_VERSION_1,
    phase: 'apply',
  },
  {
    mechanicCode: DAMAGE_TYPE_HOOK_MECHANIC_BLUNT_KO,
    version: DAMAGE_TYPE_HOOK_VERSION_1,
    phase: 'apply',
  },
];
