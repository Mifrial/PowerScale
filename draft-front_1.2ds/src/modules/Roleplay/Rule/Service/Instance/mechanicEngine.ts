import { MechanicEngine } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicEngine';
import { MechanicHandlerRegistry } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandlerRegistry';
import { purchaseSurchargeHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/PurchaseSurchargeHandler';
import { rollAdvantageHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/RollAdvantageHandler';
import { rollSixOneHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/RollSixOneHandler';
import { rollCriticalStrikeHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/Handlers/RollCriticalStrikeHandler';
import { MovementStateMechanic } from '@/modules/Roleplay/Rule/Service/Mechanic/MovementStateMechanic';

export const mechanicHandlerRegistry = new MechanicHandlerRegistry();
mechanicHandlerRegistry.register(purchaseSurchargeHandler);
mechanicHandlerRegistry.register(rollAdvantageHandler);
mechanicHandlerRegistry.register(rollSixOneHandler);
mechanicHandlerRegistry.register(rollCriticalStrikeHandler);
mechanicHandlerRegistry.register(new MovementStateMechanic());

export const mechanicEngine = new MechanicEngine(mechanicHandlerRegistry);
