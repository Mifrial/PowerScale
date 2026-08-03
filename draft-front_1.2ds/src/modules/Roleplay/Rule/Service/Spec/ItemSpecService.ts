import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { ItemSpecDraft } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpecDraft';
import type { WeaponBlock } from '@/modules/Roleplay/Rule/Dto/Item/WeaponBlock';
import type { ArmorBlock } from '@/modules/Roleplay/Rule/Dto/Item/ArmorBlock';
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import { ITEM_SUBTYPE_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_SUBTYPE_FIELDS';
import { ITEM_BLOCK_FIELDS } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_BLOCK_FIELDS';

export class ItemSpecService {
  constructor(
    private readonly subtypeFields: Record<string, (keyof ItemSpecDraft)[]>,
    private readonly blockFields: (keyof ItemSpecDraft)[],
  ) {}

  ensureWeapon(spec: ItemSpecDraft): WeaponBlock {
    if (!spec.weapon) {
      spec.weapon = {
        min_strength: { base: 3, size: 0 },
        block_profile: null,
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
        block: {
          efficiency: { base: 3, size: 0 },
          defense: 0,
          resistances: [],
        },
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
        formula: { type: 'characteristic', characteristic_code: strengthCode, modifier: 0 },
        damage_type_code: null,
      },
      penetration: { type: 'characteristic', characteristic_code: strengthCode, modifier: 0 },
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
   * Оставляет в спеке только блоки активных подтипов (по манифесту).
   * Применяется на границе эмита (specToEmit): при смене подтипа черновые поля
   * редактора НЕ чистятся, но в сохранённый результат мусор не попадает.
   */
  prune(spec: ItemSpecDraft, subtypes: string[]): ItemSpec {
    const active = new Set<keyof ItemSpecDraft>();
    for (const subtype of subtypes) {
      for (const field of this.subtypeFields[subtype] ?? []) {
        active.add(field);
      }
    }
    const out: ItemSpecDraft = { ...spec };
    for (const field of this.blockFields) {
      if (!active.has(field)) {
        delete out[field];
      }
    }

    return out;
  }
}

export const itemSpecService = new ItemSpecService(ITEM_SUBTYPE_FIELDS, ITEM_BLOCK_FIELDS);
