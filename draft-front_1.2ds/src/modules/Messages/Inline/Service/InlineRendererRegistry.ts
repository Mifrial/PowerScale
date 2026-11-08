import type { IInlineRenderer } from '@/modules/Messages/Inline/Interface/IInlineRenderer';

/**
 * Реестр инлайн-рендереров: один тип — один чип.
 */
export class InlineRendererRegistry {
  private readonly renderers = new Map<string, IInlineRenderer>();

  register(renderer: IInlineRenderer): void {
    this.renderers.set(renderer.type, renderer);
  }

  get(type: string): IInlineRenderer | undefined {
    return this.renderers.get(type);
  }

  reset(): void {
    this.renderers.clear();
  }
}
