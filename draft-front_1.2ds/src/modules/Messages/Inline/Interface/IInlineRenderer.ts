import type { Component } from 'vue';
import type { InlineSegment } from '@/modules/Messages/Inline/Dto/InlineSegment';

/**
 * Контракт рендерера инлайн-сущности: чип в тексте и подпись для превью.
 */
export interface IInlineRenderer {
  type: string;
  component: Component;
  /** Человекочитаемая подпись токена (для превью списков и поиска). */
  describe?(segment: Extract<InlineSegment, { kind: 'token' }>): string | null;
}
