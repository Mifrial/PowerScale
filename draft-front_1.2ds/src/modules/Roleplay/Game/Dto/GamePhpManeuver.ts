/** Проверка защитника в раскрытом Обманном манёвре. */
export interface GamePhpManeuverTarget {
  actor: { type: string; id: number } | null;
  check: { check: string; characteristic: string } | null;
  roll: Record<string, unknown> | null;
  won: boolean | null;
  decision: 'keep' | 'refuse' | null;
}

/** Раскрытый Обманный манёвр открытого удара. */
export interface GamePhpManeuver {
  ruleCode: string;
  phase: 'check' | 'decision' | 'done' | string;
  attacker?: { type: string; id: number } | null;
  targets: Record<string, GamePhpManeuverTarget>;
}
