import type { Component } from 'vue';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';

export interface IRenderer {
  type: string;
  component: Component;
  /** Человекочитаемая подпись токена (для превью списка чатов и т.п.). */
  describe?(segment: Extract<InlineSegment, { kind: 'token' }>): string | null;
  /**
   * Куда класть вложение: `meta` — в шапку сообщения рядом с глазиком видимости,
   * иначе отдельным блоком под текстом.
   */
  layout?: 'block' | 'meta';
}
