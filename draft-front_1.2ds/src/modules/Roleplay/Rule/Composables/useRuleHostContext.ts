import { inject } from 'vue';
import type { ComputedRef } from 'vue';
import { ruleHostContextKey } from '@/modules/Roleplay/Rule/Constant/ruleHostContextKey';
import type { IRuleHostContext } from '@/modules/Roleplay/Rule/Interface/IRuleHostContext';

/** Читает контекст хоста пространства (provide в SpaceContextLayout). Только под `/space/:code/...`. */
export function useRuleHostContext(): ComputedRef<IRuleHostContext> {
  const context = inject(ruleHostContextKey);
  if (!context) {
    throw new Error('useRuleHostContext: провайдер пространства не найден');
  }

  return context;
}
