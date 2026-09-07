import { MechanicEngine } from '@/modules/Roleplay/Mechanic/Service/MechanicEngine';
import { mechanicHandlerRegistry } from '@/modules/Roleplay/Mechanic/Service/Instance/mechanicHandlerRegistry';
import { purchaseSurchargeHandler } from '@/modules/Roleplay/Mechanic/Service/Handler/PurchaseSurchargeHandler';

mechanicHandlerRegistry.register(purchaseSurchargeHandler);

export const mechanicEngine = new MechanicEngine(mechanicHandlerRegistry);
