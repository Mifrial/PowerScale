function entityToken(key: string, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

export type DeclineOutcome = 'clear' | 'weakness' | 'disabled' | 'unconscious';

export function declineOutcomeFromRating(rating: number): DeclineOutcome {
  if (rating <= -3) return 'unconscious';
  if (rating === -2) return 'disabled';
  if (rating === -1) return 'weakness';

  return 'clear';
}

export function declineOutcomeLabel(outcome: DeclineOutcome): string {
  if (outcome === 'weakness') return 'Слабость';
  if (outcome === 'disabled') return 'Обессилен';
  if (outcome === 'unconscious') return 'Потеря сознания';

  return 'без Упадка сил';
}

export function formatExhaustionCheckMessage(
  targetName: string,
  rating: number,
  outcome: DeclineOutcome,
  targetKey?: string,
): string {
  const target = targetKey ? entityToken(targetKey, targetName) : targetName;
  if (outcome === 'clear') {
    return `${target} выдерживает истощение (РУ ${rating >= 0 ? '+' : ''}${rating}) — ${declineOutcomeLabel(outcome)}.`;
  }

  return `${target} не выдерживает истощение (РУ ${rating}) — ${declineOutcomeLabel(outcome)}.`;
}

export type ExhaustionChange = 'increase' | 'decrease';

/** Рост истощения в бессознательности — без Воли; снижение — чтобы можно было очнуться. */
export function shouldSkipExhaustionCheck(unconscious: boolean, change: ExhaustionChange): boolean {
  return unconscious && change === 'increase';
}
