import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator'
import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi'
import type { IGroupApi } from '@/modules/Core/User/Interface/IGroupApi'
import type { ProfileSection } from '@/modules/Core/User/Dto/ProfileSection'
import type { PermissionCategory, AdminSection } from '@/modules/Core/User/Interface/IPermissionRegistry'
import { accessService } from '@/modules/Core/User/Service/AccessService'
import type { User } from '@/modules/Core/User/Dto/User'
import { USER_PERMISSION_CATEGORY, USER_GROUP_PERMISSION_CATEGORY, GROUPS_ADMIN_SECTION } from '@/modules/Core/User/Constant/permissions'

export { accessService } from '@/modules/Core/User/Service/AccessService'

export function registerUserApi(api: IUserApi): void {
  serviceLocator.set('Core.User.Service.UserApi', api)
}

export function getUserApi(): IUserApi {
  return serviceLocator.get('Core.User.Service.UserApi')
}

export function registerGroupApi(api: IGroupApi): void {
  serviceLocator.set('Core.User.Service.GroupApi', api)
}

export function getGroupApi(): IGroupApi {
  return serviceLocator.get('Core.User.Service.GroupApi')
}

const profileSections: ProfileSection[] = []

export function registerProfileSection(section: ProfileSection): void {
  if (!profileSections.some(s => s.id === section.id)) {
    profileSections.push(section)
  }
}

export function getProfileSections(): ProfileSection[] {
  return profileSections
}

const permissionCategories: PermissionCategory[] = []
const adminSections: AdminSection[] = []

export function registerPermissionCategory(category: PermissionCategory): void {
  if (!permissionCategories.some(c => c.key === category.key)) {
    permissionCategories.push(category)
  }
}

export function getPermissionCategories(): PermissionCategory[] {
  return permissionCategories
}

export function getPermissionKeys(): string[] {
  return permissionCategories.flatMap(c => c.actions.map(a => `${c.key}.${a.key}`))
}

export function registerAdminSection(section: AdminSection): void {
  if (!adminSections.some(s => s.id === section.id)) {
    adminSections.push(section)
  }
}

export function getAdminSections(): AdminSection[] {
  return adminSections
}

export function getAdminSectionPermissions(): string[] {
  return adminSections.map(s => s.permission)
}

export function isAdmin(user: User | null | undefined): boolean {
  return accessService.hasAnyPermission(user, getAdminSectionPermissions())
}

export function resetPermissionRegistries(): void {
  permissionCategories.splice(0)
  adminSections.splice(0)
}

export function registerUserModule(): void {
  registerPermissionCategory(USER_PERMISSION_CATEGORY)
  registerPermissionCategory(USER_GROUP_PERMISSION_CATEGORY)
  registerAdminSection(GROUPS_ADMIN_SECTION)
}
