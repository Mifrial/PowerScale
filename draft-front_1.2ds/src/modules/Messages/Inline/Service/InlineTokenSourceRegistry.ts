import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';

/**
 * Реестр источников «Вставить ссылку»: глобальные и добавленные донорами.
 * Один тип — один источник: повторная регистрация (HMR, повторная инициализация) заменяет прежний.
 */
export class InlineTokenSourceRegistry {
  private readonly sources: ITokenSource[] = [];

  register(source: ITokenSource): void {
    const index = this.sources.findIndex((candidate) => candidate.type === source.type);
    if (index === -1) {
      this.sources.push(source);

      return;
    }
    this.sources[index] = source;
  }

  list(): ITokenSource[] {
    return [...this.sources];
  }

  reset(): void {
    this.sources.length = 0;
  }
}
