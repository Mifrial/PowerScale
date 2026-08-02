import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useDraftRuleStore } from '@/modules/Roleplay/Rule/Store/draftRules'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'

function makeRule(id: string): Rule {
  return {
    id,
    code: `code-${id}`,
    type: 'simple',
    name: `Правило ${id}`,
    description: '',
    spaceId: 1,
    createdAt: '2026-08-02T00:00:00Z',
  }
}

describe('draftRules store persistence', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('сохраняет черновик в localStorage и восстанавливает после пересоздания стора', () => {
    const store = useDraftRuleStore()
    store.saveRule(1, makeRule('r1'))
    store.saveRule(1, makeRule('r2'))
    store.saveRule(2, makeRule('r3'))

    const fresh = useDraftRuleStore()
    expect(fresh.getDraftRules(1)).toHaveLength(2)
    expect(fresh.getDraftRules(1).map(r => r.id).sort()).toEqual(['r1', 'r2'])
    expect(fresh.getDraftRules(2).map(r => r.id)).toEqual(['r3'])
    expect(fresh.hasDraft(1)).toBe(true)
  })

  it('discardDraft удаляет ключ из localStorage', () => {
    const store = useDraftRuleStore()
    store.saveRule(1, makeRule('r1'))
    store.discardDraft(1)
    expect(localStorage.getItem('powerscale.drafts.v1')).toBeNull()

    const fresh = useDraftRuleStore()
    expect(fresh.hasDraft(1)).toBe(false)
  })

  it('невалидный JSON в хранилище не ломает стор', () => {
    localStorage.setItem('powerscale.drafts.v1', '{not json')
    const store = useDraftRuleStore()
    expect(store.hasDraft(1)).toBe(false)
  })

  it('неподходящая структура игнорируется', () => {
    localStorage.setItem('powerscale.drafts.v1', JSON.stringify([{ foo: 1 }, 42]))
    const store = useDraftRuleStore()
    expect(store.getDraftRules(1)).toEqual([])
  })
})
