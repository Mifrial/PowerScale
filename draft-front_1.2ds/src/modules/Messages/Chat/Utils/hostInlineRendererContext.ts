import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';

/** Мешок для зарегистрированных чипов/вложений: data-срез хоста + опциональный openEntity. */
export function hostInlineRendererContext(
  data: ChatInlineRendererContext | null | undefined,
  openEntity?: (ref: string) => void,
): Record<string, unknown> | undefined {
  if (!data && !openEntity) return undefined;
  const tokenLabels = data?.tokenLabels ?? {};

  return {
    tokenLabels,
    spaceId: data?.spaceId ?? null,
    rulesRevision: data?.rulesRevision ?? null,
    ...(openEntity ? { openEntity } : {}),
  };
}
