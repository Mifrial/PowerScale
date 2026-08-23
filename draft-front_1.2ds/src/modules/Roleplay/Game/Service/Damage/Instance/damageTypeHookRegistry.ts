import { DamageTypeHookRegistry } from '@/modules/Roleplay/Game/Service/Damage/DamageTypeHookRegistry';
import { damageTypeHookV1 } from '@/modules/Roleplay/Game/Service/Damage/damageTypeHookV1';

export const damageTypeHookRegistry = new DamageTypeHookRegistry();
for (const handler of damageTypeHookV1) {
  damageTypeHookRegistry.register(handler);
}
