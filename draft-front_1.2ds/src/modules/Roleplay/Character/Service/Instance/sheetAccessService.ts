import { SheetAccessService } from '@/modules/Roleplay/Character/Service/SheetAccessService';
import { sheetRoleRegistry } from '@/modules/Roleplay/Character/Service/Instance/sheetRoleRegistry';
import { accessService } from '@/modules/Core/User/init';

export const sheetAccessService = new SheetAccessService(
  () => sheetRoleRegistry.list(),
  (user) => accessService.hasAnyPermission(user, ['character.view']),
);
