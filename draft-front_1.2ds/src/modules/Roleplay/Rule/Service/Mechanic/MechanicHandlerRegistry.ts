import type { MechanicHandler } from '@/modules/Roleplay/Rule/Interface/MechanicHandler';

/**
 * Реестр хендлеров механик: code@version → хендлер. Версия-aware: правило ссылается
 * на Mechanic.id, по которому движок берёт code@version из каталога механик.
 */
export class MechanicHandlerRegistry {
  private readonly byKey = new Map<string, MechanicHandler>();

  register(handler: MechanicHandler): void {
    this.byKey.set(`${handler.code}@${handler.version}`, handler);
  }

  resolve(code: string, version: string): MechanicHandler | undefined {
    return this.byKey.get(`${code}@${version}`);
  }
}
