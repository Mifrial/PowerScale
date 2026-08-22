import type { InjectionKey, ComputedRef } from 'vue';
import type { ISpaceContext } from '@/modules/Roleplay/Space/Interface/ISpaceContext';

export const spaceContextKey: InjectionKey<ComputedRef<ISpaceContext>> = Symbol('spaceContext');
