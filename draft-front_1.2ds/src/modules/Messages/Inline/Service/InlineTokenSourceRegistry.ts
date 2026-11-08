import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';

/**
 * Реестр источников «Вставить ссылку»: глобальные и добавленные донорами.
 */
export class InlineTokenSourceRegistry {
  private readonly sources: ITokenSource[] = [];

  register(source: ITokenSource): void {
    this.sources.push(source);
  }

  list(): ITokenSource[] {
    return [...this.sources];
  }

  reset(): void {
    this.sources.length = 0;
  }
}
