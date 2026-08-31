import type { SheetRole } from '@/modules/Roleplay/Character/Interface/SheetRole';

export class SheetRoleRegistry {
  private readonly roles: SheetRole[] = [];

  register(role: SheetRole): void {
    if (!this.roles.some((existing) => existing.name === role.name)) {
      this.roles.push(role);
    }
  }

  list(): SheetRole[] {
    return this.roles;
  }

  reset(): void {
    this.roles.splice(0);
  }
}
