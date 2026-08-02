import { describe, it, expect } from 'vitest'
import { rollService } from '@/modules/Roleplay/Game/Service/RollService'

describe('parseRollFormula', () => {
  it('разбирает латинскую формулу', () => {
    expect(rollService.parseRollFormula('3d6')).toEqual({ diceCount: 3, dieFaces: 6 })
  })

  it('разбирает кириллическую формулу', () => {
    expect(rollService.parseRollFormula('5к10')).toEqual({ diceCount: 5, dieFaces: 10 })
  })

  it('отклоняет не-формулу', () => {
    expect(rollService.parseRollFormula('abc')).toBeNull()
    expect(rollService.parseRollFormula('3d')).toBeNull()
  })

  it('отклоняет нулевой и завышенный количество кубов', () => {
    expect(rollService.parseRollFormula('0d6')).toBeNull()
    expect(rollService.parseRollFormula('31d6')).toBeNull()
  })

  it('отклоняет некорректную размерность грани', () => {
    expect(rollService.parseRollFormula('3d1')).toBeNull()
    expect(rollService.parseRollFormula('3d101')).toBeNull()
  })
})

describe('parseRollCommand', () => {
  it('возвращает null для обычного текста', () => {
    expect(rollService.parseRollCommand('Привет всем')).toBeNull()
    expect(rollService.parseRollCommand('/roll')).toBeNull()
    expect(rollService.parseRollCommand('/roll без формулы')).toBeNull()
  })

  it('разбирает минимальную команду', () => {
    const res = rollService.parseRollCommand('/roll 3d6')
    expect(res?.content).toBe('/roll 3d6')
    expect(res?.rolls).toEqual([
      { diceCount: 3, dieFaces: 6, efficiency: 3, adv: 0, dieSize: 0, label: undefined },
    ])
  })

  it('учитывает эффективность и метку', () => {
    const res = rollService.parseRollCommand('/roll 3d6 e:2 Проверка на силу')
    expect(res?.rolls[0].efficiency).toBe(2)
    expect(res?.rolls[0].label).toBe('Проверка на силу')
  })

  it('превращает dis в отрицательный adv', () => {
    expect(rollService.parseRollCommand('/roll 3d6 dis:2')?.rolls[0].adv).toBe(-2)
    expect(rollService.parseRollCommand('/roll 3d6 pom:1')?.rolls[0].adv).toBe(-1)
  })

  it('разбирает adv и префикс prem', () => {
    expect(rollService.parseRollCommand('/roll 3d6 adv:1')?.rolls[0].adv).toBe(1)
    expect(rollService.parseRollCommand('/roll 3d6 prem:2')?.rolls[0].adv).toBe(2)
  })

  it('ограничивает adv максимумом', () => {
    expect(rollService.parseRollCommand('/roll 3d6 adv:99')?.rolls[0].adv).toBe(10)
    expect(rollService.parseRollCommand('/roll 3d6 dis:99')?.rolls[0].adv).toBe(-10)
  })

  it('отбрасывает невалидную эффективность в пользу дефолта', () => {
    expect(rollService.parseRollCommand('/roll 3d6 e:0')?.rolls[0].efficiency).toBe(3)
    expect(rollService.parseRollCommand('/roll 3d6 e:21')?.rolls[0].efficiency).toBe(3)
  })

  it('разбирает размерность', () => {
    expect(rollService.parseRollCommand('/roll 3d6 size:2')?.rolls[0].dieSize).toBe(2)
    expect(rollService.parseRollCommand('/roll 3d6 dim:-1')?.rolls[0].dieSize).toBe(-1)
  })
})

describe('formatRollSize', () => {
  it('возвращает пустую строку для нуля', () => {
    expect(rollService.formatRollSize(0)).toBe('')
  })

  it('форматирует одиночную размерность', () => {
    expect(rollService.formatRollSize(1)).toBe('↑')
    expect(rollService.formatRollSize(-1)).toBe('↓')
  })

  it('использует суперскрипт для модуля ≥ 2', () => {
    expect(rollService.formatRollSize(2)).toBe('↑²')
    expect(rollService.formatRollSize(-3)).toBe('↓³')
    expect(rollService.formatRollSize(10)).toBe('↑¹⁰')
  })
})
