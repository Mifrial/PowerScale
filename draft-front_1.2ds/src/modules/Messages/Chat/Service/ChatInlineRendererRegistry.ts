import type { IRenderer } from '@/modules/Messages/Chat/Interface/IRenderer';

export class ChatInlineRendererRegistry {
  private readonly renderers = new Map<string, IRenderer>();

  register(renderer: IRenderer): void {
    this.renderers.set(renderer.type, renderer);
  }

  get(type: string): IRenderer | undefined {
    return this.renderers.get(type);
  }
}
