import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft';
import type { ItemHandsSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemHandsSpec';
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock';
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock';
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Черновик спеки предмета: подтипы, вес в тиках, блоки оружия/брони/щита. */
export class ItemSpecService {
  constructor(
    private readonly subtypeFields: Record<string, (keyof ItemSpecDraft)[]>,
    private readonly blockFields: (keyof ItemSpecDraft)[],
    private readonly weightTickKilograms: number,
    private readonly weightSize: number,
  ) {}

  kilogramsToWeight(kilograms: number): DimensionalNumberValue {
    return {
      base: Math.max(0, Math.round(kilograms / this.weightTickKilograms)),
      size: this.weightSize,
    };
  }

  weightToKilograms(weight: DimensionalNumberValue | null | undefined): number | null {
    if (!weight) {
      return null;
    }

    return weight.base * this.weightTickKilograms * 2 ** (weight.size - this.weightSize);
  }

  setWeightKilograms(spec: ItemSpecDraft, kilograms: number | null): void {
    spec.weight = kilograms === null ? null : this.kilogramsToWeight(kilograms);
  }

  setWeight(spec: ItemSpecDraft, weight: DimensionalNumberValue | null): void {
    spec.weight = weight;
  }

  formatWeightKilograms(weight: DimensionalNumberValue | null | undefined): string {
    const kilograms = this.weightToKilograms(weight);
    if (kilograms === null) {
      return '';
    }

    return `${kilograms} кг`;
  }

  /** Подпись к «Занимает»: «1–2 руки», «1 руку, на действии 2»; null — предмет рук не занимает. */
  formatOccupyHands(hands: ItemHandsSpec | null | undefined): string | null {
    if (!hands || hands.max === 0) {
      return null;
    }
    const { min, max, action } = hands;
    const base =
      min === max
        ? `${min} ${this.handWord(min)}`
        : min === 0
          ? `до ${max} ${max === 1 ? 'руки' : 'рук'}`
          : `${min}–${max} ${this.handWord(max)}`;
    const actionLabel = action != null && action !== max ? `, на действии ${action}` : '';

    return `${base}${actionLabel}`;
  }

  private handWord(count: number): string {
    const mod10 = count % 10;
    const mod100 = count % 100;
    if (mod10 === 1 && mod100 !== 11) return 'руку';
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'руки';

    return 'рук';
  }

  ensureWeapon(spec: ItemSpecDraft): WeaponBlock {
    if (!spec.weapon) {
      spec.weapon = {
        min_strength: { base: 3, size: 0 },
        weapon_profiles: [],
      };
    }

    return spec.weapon;
  }

  ensureArmor(spec: ItemSpecDraft): ArmorBlock {
    if (!spec.armor) {
      spec.armor = {
        defense_slots: [],
        resistance_slots: [],
        characteristic_limits: [],
      };
    }

    return spec.armor;
  }

  ensureShield(spec: ItemSpecDraft): ShieldBlock {
    if (!spec.shield) {
      spec.shield = {
        min_strength: { base: 3, size: 0 },
      };
    }

    return spec.shield;
  }

  addWeaponProfile(weapon: WeaponBlock, strengthCode: string): void {
    weapon.weapon_profiles.push({
      type: 'strike',
      distance: { type: 'fixed', value: 0 },
      range: null,
      damage: {
        formula: {
          type: 'actionCharacteristic',
          action: 'strike',
          characteristic: strengthCode,
          modifier: [{ delta: 0, source_code: null, source_label: null }],
        },
        damage_type_code: null,
      },
      penetration: {
        type: 'actionCharacteristic',
        action: 'strike',
        characteristic: strengthCode,
        modifier: [{ delta: 0, source_code: null, source_label: null }],
      },
      accuracy: { base: 3, size: 0 },
    });
  }

  updateProfileType(weapon: WeaponBlock, index: number, type: WeaponProfile['type']): void {
    weapon.weapon_profiles[index].type = type;
  }

  removeWeaponProfile(weapon: WeaponBlock, index: number): void {
    weapon.weapon_profiles.splice(index, 1);
  }

  /**
   * Блоки подтипов, которые сейчас не выбраны. Не удаляются сами — только по действию пользователя.
   */
  unsupportedBlocks(spec: ItemSpecDraft, subtypes: string[]): (keyof ItemSpecDraft)[] {
    const active = new Set<keyof ItemSpecDraft>();
    for (const subtype of subtypes) {
      for (const field of this.subtypeFields[subtype] ?? []) {
        active.add(field);
      }
    }
    const extra: (keyof ItemSpecDraft)[] = [];
    for (const field of this.blockFields) {
      if (!active.has(field) && spec[field] !== undefined) {
        extra.push(field);
      }
    }

    return extra;
  }

  stripUnsupported(spec: ItemSpecDraft, subtypes: string[]): ItemSpecDraft {
    const drop = new Set(this.unsupportedBlocks(spec, subtypes));
    const out: ItemSpecDraft = { ...spec };
    for (const field of drop) {
      delete out[field];
    }

    return out;
  }

  applyInnateLimits(spec: ItemSpecDraft): ItemSpecDraft {
    if (!spec.innate) {
      return spec;
    }

    return { ...spec, cost_gm: null, weight: null };
  }

  patchOccupyHands(spec: ItemSpecDraft, field: 'min' | 'max' | 'action', value: number): void {
    const current = spec.occupy_hands;
    const min = field === 'min' ? value : (current?.min ?? 0);
    const maxRaw = field === 'max' ? value : (current?.max ?? 0);
    const actionRaw = field === 'action' ? value : (current?.action ?? 0);
    const max = Math.max(min, maxRaw);
    if (min === 0 && max === 0 && actionRaw === 0) {
      delete spec.occupy_hands;

      return;
    }
    spec.occupy_hands = actionRaw > 0 ? { min, max, action: actionRaw } : { min, max };
  }
}
