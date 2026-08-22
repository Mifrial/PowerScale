import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';

/**
 * Хендлер механики: подписан на события потока (`subscriptions`: событие → приоритет,
 * меньше — раньше) и МУТИРУЕТ контекст (draft) при срабатывании. Движок не знает
 * семантику механик — только события и контекст, поэтому новые механики не требуют
 * доработок движка (Вариант A, спека §7.17). Тип контекста — у домена (сборка/бросок).
 */
export interface MechanicHandler<TContext = object> {
  code: string;
  version: string;
  subscriptions: Readonly<Record<string, number>>;
  run(input: { payload: MechanicPayload | null; context: TContext; event: string }): void;
}
