import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec'
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult'

export interface ParsedRollCommand {
  content: string
  rolls: DiceRollSpec[]
}

export interface ParsedRollFormula {
  diceCount: number
  dieFaces: number
}

export type DiceRng = () => number

export class RollService {
  private static readonly MAX_DICE_COUNT = 30
  private static readonly MAX_ADV = 10
  private static readonly MAX_EFFICIENCY = 20
  private static readonly MAX_DIE_FACES = 100
  private static readonly MAX_DIE_SIZE = 10
  private static readonly SUPERSCRIPTS: Record<number, string> = { 2: '²', 3: '³', 4: '⁴', 5: '⁵', 6: '⁶', 7: '⁷', 8: '⁸', 9: '⁹', 10: '¹⁰' }

  formatRollSize(size: number): string {
    if (!size) return ''
    const arrow = size > 0 ? '↑' : '↓'
    const mag = Math.abs(size)
    return arrow + (mag >= 2 ? (RollService.SUPERSCRIPTS[mag] ?? String(mag)) : '')
  }

  parseRollFormula(formula: string): ParsedRollFormula | null {
    const match = /^\s*(\d{1,2})\s*[dDкК]\s*(\d{1,3})\s*$/.exec(formula)
    if (!match) return null
    const diceCount = Number(match[1])
    const dieFaces = Number(match[2])
    if (diceCount < 1 || diceCount > RollService.MAX_DICE_COUNT) return null
    if (dieFaces < 2 || dieFaces > RollService.MAX_DIE_FACES) return null
    return { diceCount, dieFaces }
  }

  parseRollCommand(text: string): ParsedRollCommand | null {
    const trimmed = text.trim()
    const headMatch = /^\/(?:roll|бросок)\b(.*)$/is.exec(trimmed)
    if (!headMatch) return null

    const rest = headMatch[1]
    const formulaMatch = /^(\d{1,2}[dDкК]\d{1,3})(.*)$/s.exec(rest.trim())
    if (!formulaMatch) return null

    const formula = this.parseRollFormula(formulaMatch[1])
    if (!formula) return null

    const parts = formulaMatch[2].split(/\s+/).filter(Boolean)
    let efficiency = 3
    let adv = 0
    let dieSize = 0
    const labelParts: string[] = []

    for (const part of parts) {
      if (/^e:\d+$/i.test(part)) {
        const value = Number(part.slice(2))
        efficiency = value > 0 && value <= RollService.MAX_EFFICIENCY ? value : efficiency
      } else if (/^(?:adv|prem):[-+]?\d+$/i.test(part)) {
        const value = Number(part.split(':')[1])
        adv = Math.max(-RollService.MAX_ADV, Math.min(RollService.MAX_ADV, value))
      } else if (/^(?:dis|pom):[-+]?\d+$/i.test(part)) {
        const value = Number(part.split(':')[1])
        adv = Math.max(-RollService.MAX_ADV, Math.min(RollService.MAX_ADV, -value))
      } else if (/^(?:size|razm|dim):[-+]?\d+$/i.test(part)) {
        dieSize = Math.max(-RollService.MAX_DIE_SIZE, Math.min(RollService.MAX_DIE_SIZE, Number(part.split(':')[1])))
      } else if (/^label:/i.test(part)) {
        labelParts.push(part.slice(6))
      } else {
        labelParts.push(part)
      }
    }

    const rolls: DiceRollSpec[] = [{
      diceCount: formula.diceCount,
      dieFaces: formula.dieFaces,
      efficiency,
      adv,
      dieSize,
      label: labelParts.join(' ').trim() || undefined,
    }]

    return { content: trimmed, rolls }
  }

  computeRollResult(spec: DiceRollSpec, rng: DiceRng = Math.random): DiceRollResult {
    const diceCount = Math.max(1, spec.diceCount)
    const faces = Math.max(2, spec.dieFaces)
    const adv = spec.adv || 0
    const rollDie = () => Math.floor(rng() * faces) + 1

    const rolls = Array.from({ length: diceCount }, rollDie)
    const adjusted = adv !== 0 ? [...rolls, ...Array.from({ length: Math.abs(adv) }, rollDie)] : [...rolls]

    let droppedRolls: number[] = []
    if (adv > 0) {
      adjusted.sort((a, b) => b - a)
      droppedRolls = adjusted.splice(0, adv)
    } else if (adv < 0) {
      adjusted.sort((a, b) => a - b)
      droppedRolls = adjusted.splice(0, Math.abs(adv))
    }

    const successes = adjusted.map(v => {
      if (v === 1) return 2
      if (v <= spec.efficiency) return 1
      if (v < faces) return 0
      return -1
    })
    const totalSuccesses: number = successes.reduce<number>((sum, s) => sum + s, 0)

    return { spec, rolls, successes, adjustedRolls: adjusted, droppedRolls, totalSuccesses }
  }
}

export const rollService = new RollService()
