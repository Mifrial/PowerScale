import type { DimensionalNumberValue, DimensionalNumberBaseRange } from '@/modules/Core/Engine/Dto/DimensionalNumber'

export class DimensionalNumber {
  readonly value: DimensionalNumberValue

  constructor(value: DimensionalNumberValue) {
    this.value = value
  }

  static from(v: DimensionalNumberValue): DimensionalNumber {
    return new DimensionalNumber(v)
  }

  toNumber(): number {
    return Math.floor(this.value.base * Math.pow(2, this.value.size))
  }

  modify(delta: number, range?: DimensionalNumberBaseRange): DimensionalNumber {
    if (!range) {
      return new DimensionalNumber({ base: this.value.base + delta, size: this.value.size })
    }
    // Перенос между размерами: шаг базы = (max − min + 1) пунктов = один размер.
    const step = range.max - range.min + 1
    const sizeDelta = Math.floor(delta / step)
    const baseDelta = delta - sizeDelta * step
    let base = this.value.base + baseDelta
    let size = this.value.size + sizeDelta
    if (base > range.max) {
      base -= step
      size += 1
    } else if (base < range.min) {
      base += step
      size -= 1
    }
    return new DimensionalNumber({ base, size })
  }

  add(other: DimensionalNumber): DimensionalNumber {
    const size = Math.min(this.value.size, other.value.size)
    const a = this.value.base * Math.pow(2, this.value.size - size)
    const b = other.value.base * Math.pow(2, other.value.size - size)
    return new DimensionalNumber({ base: a + b, size })
  }

  subtract(other: DimensionalNumber): DimensionalNumber {
    const size = Math.min(this.value.size, other.value.size)
    const a = this.value.base * Math.pow(2, this.value.size - size)
    const b = other.value.base * Math.pow(2, other.value.size - size)
    return new DimensionalNumber({ base: a - b, size })
  }

  toString(): string {
    if (this.value.size === 0) return String(this.value.base)
    const arrow = this.value.size > 0 ? '↑' : '↓'
    const abs = Math.abs(this.value.size)
    const sup = abs >= 2 ? toSuperscript(abs) : ''
    return `${this.value.base}${arrow}${sup}`
  }
}

function toSuperscript(n: number): string {
  const digits = '⁰¹²³⁴⁵⁶⁷⁸⁹'
  return String(n)
    .split('')
    .map(d => digits[Number(d)] ?? d)
    .join('')
}
