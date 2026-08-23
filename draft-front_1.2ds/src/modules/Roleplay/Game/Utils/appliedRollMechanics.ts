import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';

/** Имена как в каталоге механик (карточка чата). */
export const ROLL_MECHANIC_NAME_ADVANTAGE = 'Помехи и преимущества';
export const ROLL_MECHANIC_NAME_SIX_ONE = 'Правило 6 и 1';

/**
 * Какие механики реально сдвинули бросок. `appliedMechanics` с движка — если есть;
 * иначе (фолбэк computeRollResult / старые сообщения) — по кубам.
 */
export function resolveAppliedMechanicNames(
  result: Pick<DiceRollResult, 'spec' | 'adjustedRolls' | 'successes' | 'droppedRolls' | 'appliedMechanics'>,
): string[] {
  const names: string[] = [];
  const add = (name: string): void => {
    if (!names.includes(name)) names.push(name);
  };
  for (const name of result.appliedMechanics ?? []) add(name);
  if (result.droppedRolls.length > 0) add(ROLL_MECHANIC_NAME_ADVANTAGE);
  if (sixOneShifted(result)) add(ROLL_MECHANIC_NAME_SIX_ONE);

  return names;
}

function sixOneShifted(result: Pick<DiceRollResult, 'spec' | 'adjustedRolls' | 'successes'>): boolean {
  const faces = result.spec.dieFaces;
  const efficiency = result.spec.efficiency;

  return result.adjustedRolls.some((face, index) => {
    const success = result.successes[index] ?? 0;
    if (face === 1 && success !== (face <= efficiency ? 1 : 0)) return true;
    if (face === faces && success === -1) return true;

    return false;
  });
}
