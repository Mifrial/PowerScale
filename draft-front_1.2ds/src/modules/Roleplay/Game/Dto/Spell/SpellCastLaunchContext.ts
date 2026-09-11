/** Как открыт диалог сотворения: обычный каст или трата заряда поддержания. */
export type SpellCastLaunchContext = { kind: 'free' } | { kind: 'charge_spend'; sustainId: string };
