import type { InjectionKey, ComputedRef } from 'vue';
import type { IRuleHostContext } from '@/modules/Roleplay/Rule/Interface/IRuleHostContext';

export const ruleHostContextKey: InjectionKey<ComputedRef<IRuleHostContext>> = Symbol('ruleHostContext');
