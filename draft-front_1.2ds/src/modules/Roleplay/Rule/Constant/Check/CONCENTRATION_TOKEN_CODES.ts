/** Характеристики, на чей пул можно потратить жетон концентрации. */
export const CONCENTRATION_TOKEN_CHARACTERISTIC_CODES = [
  'perception',
  'attention',
  'reaction',
  'intellect',
  'memory',
  'reasoning',
  'communication',
] as const;

/** Предки проверки, которые сами по себе открывают трату жетона. */
export const CONCENTRATION_TOKEN_ANCESTOR_CODES = [
  'check-perception',
  'check-attention',
  'check-reaction',
  'check-intellect',
  'check-memory',
  'check-reasoning',
  'check-communication',
] as const;
