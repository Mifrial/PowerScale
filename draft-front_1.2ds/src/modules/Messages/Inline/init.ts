import { defineAsyncComponent } from 'vue';
import type { IInlineRenderer } from '@/modules/Messages/Inline/Interface/IInlineRenderer';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import { inlineRendererRegistry } from '@/modules/Messages/Inline/Service/Instance/inlineRendererRegistry';
import { inlineTokenSourceRegistry } from '@/modules/Messages/Inline/Service/Instance/inlineTokenSourceRegistry';

export { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Inline/Constant/INLINE_CONTENT_TOKEN_RE';
export { inlineContentService } from '@/modules/Messages/Inline/Service/Instance/inlineContentService';
export { inlineHtmlContentService } from '@/modules/Messages/Inline/Service/Instance/inlineHtmlContentService';

export const InlineTokenPicker = defineAsyncComponent(
  () => import('@/modules/Messages/Inline/Component/InlineTokenPicker.vue'),
);

export const RichTextEditor = defineAsyncComponent(
  () => import('@/modules/Messages/Inline/Component/RichTextEditor.vue'),
);

export const RichTextHtml = defineAsyncComponent(() => import('@/modules/Messages/Inline/Component/RichTextHtml.vue'));

export function registerInlineRenderer(renderer: IInlineRenderer): void {
  inlineRendererRegistry.register(renderer);
}

export function getInlineRenderer(type: string): IInlineRenderer | undefined {
  return inlineRendererRegistry.get(type);
}

export function registerTokenSource(source: ITokenSource): void {
  inlineTokenSourceRegistry.register(source);
}

export function getTokenSources(): ITokenSource[] {
  return inlineTokenSourceRegistry.list();
}

export function resetInlineRenderers(): void {
  inlineRendererRegistry.reset();
}

export function resetInlineTokenSources(): void {
  inlineTokenSourceRegistry.reset();
}
