import { inject } from 'vue';
import type { ComputedRef } from 'vue';
import { spaceContextKey } from '@/modules/Roleplay/Space/Constant/spaceContextKey';
import type { ISpaceContext } from '@/modules/Roleplay/Space/Interface/ISpaceContext';

/** Читает hosting-контекст пространства, предоставляемый `SpaceContextLayout`.
 *  Вызывать только внутри страниц, смонтированных под `/space/:code/...`. */
export function useSpaceContext(): ComputedRef<ISpaceContext> {
  const context = inject(spaceContextKey);
  if (!context) {
    throw new Error('useSpaceContext: провайдер SpaceContextLayout не найден');
  }

  return context;
}
