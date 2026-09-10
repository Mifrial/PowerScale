import { describe, it, expect, beforeEach } from 'vitest';
import { registerAdminSection, resetPermissionRegistries, visibleAdminSections } from '@/modules/Core/User/init';
import { LOGS_ADMIN_SECTION } from '@/modules/Core/Logger/Constant/Permission/LOGS_ADMIN_SECTION';
import type { User } from '@/modules/Core/User/Dto/User';

const user = (permissions: string[], hasBypass = false): User => ({
  id: 1,
  name: 'U',
  login: 'u',
  email: 'u@t',
  groups: [],
  registered: 0,
  active: true,
  permissions,
  bypass: hasBypass,
});

describe('пункт журнала в админке', () => {
  beforeEach(() => {
    resetPermissionRegistries();
    registerAdminSection(LOGS_ADMIN_SECTION);
  });

  it('без logger.view пункт не виден', () => {
    expect(visibleAdminSections(user(['user_group.view'])).map((section) => section.id)).toEqual([]);
  });

  it('с logger.view пункт виден', () => {
    expect(visibleAdminSections(user(['logger.view'])).map((section) => section.id)).toEqual(['logs']);
  });
});
