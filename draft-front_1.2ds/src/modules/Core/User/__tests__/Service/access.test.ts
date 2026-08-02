import { describe, it, expect } from 'vitest'
import { accessService } from '@/modules/Core/User/Service/AccessService'
import type { User } from '@/modules/Core/User/Dto/User'

const user = (permissions: string[] | undefined, superAdmin = false): User => ({
  id: 1, name: 'U', login: 'u', email: 'u@t', groups: [], registered: '', active: true,
  permissions, super_admin: superAdmin,
})

describe('hasAnyPermission', () => {
  it('null/undefined user → false', () => {
    expect(accessService.hasAnyPermission(null, ['user.view'])).toBe(false)
    expect(accessService.hasAnyPermission(undefined, ['user.view'])).toBe(false)
  })

  it('пустой список ключей → false', () => {
    expect(accessService.hasAnyPermission(user(['user.view']), [])).toBe(false)
  })

  it('нет permissions → false', () => {
    expect(accessService.hasAnyPermission(user(undefined), ['user.view'])).toBe(false)
  })

  it('любой ключ из списка → true', () => {
    expect(accessService.hasAnyPermission(user(['keyword.view', 'user.view']), ['user.view'])).toBe(true)
  })

  it('ни одного ключа → false', () => {
    expect(accessService.hasAnyPermission(user(['keyword.view']), ['user.view'])).toBe(false)
  })

  it('super_admin обходит без ключей', () => {
    expect(accessService.hasAnyPermission(user([], true), ['user.view'])).toBe(true)
  })
})

describe('hasAllPermissions', () => {
  it('null/undefined user → false', () => {
    expect(accessService.hasAllPermissions(null, ['user.view'])).toBe(false)
    expect(accessService.hasAllPermissions(undefined, ['user.view'])).toBe(false)
  })

  it('все ключи есть → true', () => {
    expect(accessService.hasAllPermissions(user(['user.view', 'user.edit']), ['user.view', 'user.edit'])).toBe(true)
  })

  it('неполный набор → false', () => {
    expect(accessService.hasAllPermissions(user(['user.view']), ['user.view', 'user.edit'])).toBe(false)
  })

  it('пустой список ключей → true (every на пустом)', () => {
    expect(accessService.hasAllPermissions(user([]), [])).toBe(true)
  })

  it('super_admin обходит без ключей', () => {
    expect(accessService.hasAllPermissions(user([], true), ['user.view', 'user.edit'])).toBe(true)
  })
})
