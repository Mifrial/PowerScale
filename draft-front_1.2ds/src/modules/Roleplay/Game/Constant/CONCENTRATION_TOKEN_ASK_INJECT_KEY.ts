import type { InjectionKey } from 'vue';

/** Спросить, сколько жетонов потратить перед автоматической проверкой Воли. */
export const CONCENTRATION_TOKEN_ASK_INJECT_KEY: InjectionKey<
  (input: { maxSpend: number; remaining: number }) => Promise<number>
> = Symbol('concentration-token-ask');
