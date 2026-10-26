import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';
import type { ResistanceSlot } from '@/modules/Roleplay/Rule/Dto/Item/ResistanceSlot';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { DAMAGE_TYPE_FORMS } from '@/modules/Roleplay/Rule/Constant/DAMAGE_TYPE_FORMS';
import { DAMAGE_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/DamageType/DAMAGE_TYPE_LABELS';
import { WEAPON_ACTION_GENITIVE } from '@/modules/Roleplay/Rule/Constant/Item/WEAPON_ACTION_GENITIVE';
import { WEAPON_PROFILE_TYPES } from '@/modules/Roleplay/Rule/Constant/Item/WEAPON_PROFILE_TYPES';
import { LIGHTING_LEVEL_LABELS } from '@/modules/Roleplay/Rule/Constant/Lighting/LIGHTING_LEVEL_LABELS';

export class RuleViewLabelService {
  ruleName(rules: Rule[], code: string): string {
    return rules.find((rule) => rule.code === code)?.name ?? code;
  }

  keywordName(keywords: Keyword[], code: string): string {
    return keywords.find((keyword) => keyword.code === code)?.name ?? code;
  }

  dimensional(value: DimensionalNumberValue): string {
    return new DimensionalNumber(value).toString();
  }

  amount(value: unknown, rules: Rule[] = []): string {
    if (typeof value === 'number') return String(value);
    if (value && typeof value === 'object' && 'type' in value && typeof (value as Formula).type === 'string') {
      return this.formula(value as Formula, rules);
    }
    if (value && typeof value === 'object' && 'base' in value && 'size' in value) {
      return this.dimensional(value as DimensionalNumberValue);
    }

    return String(value ?? '');
  }

  profileType(type: WeaponProfile['type']): string {
    return WEAPON_PROFILE_TYPES.find((entry) => entry.value === type)?.label ?? type;
  }

  formula(node: Formula, rules: Rule[]): string {
    if (node.type === 'fixed') return String(node.value);
    if (node.type === 'characteristic') {
      const name = this.ruleName(rules, node.characteristic_code);
      if (!node.modifier) return name;

      return `${name} ${node.modifier > 0 ? '+' : ''}${node.modifier}`;
    }
    if (node.type === 'ability_level') {
      const name = this.ruleName(rules, node.ability_code);
      const extra = node.multiplier && node.multiplier !== 1 ? `×${node.multiplier}` : '';
      const offset = node.offset ? ` ${node.offset > 0 ? '+' : ''}${node.offset}` : '';

      return `ур. ${name}${extra}${offset}`;
    }
    if (node.type === 'dimensional') return this.dimensional(node);
    if (node.type === 'parameter') return `${node.parameter_code} × ${node.per_unit}`;
    if (node.type === 'parameter_floor_div') return `⌊${node.parameter_code} / ${node.divisor}⌋`;
    if (node.type === 'to_scalar') return `база (${this.formula(node.value, rules)}) в среднем размере`;
    if (node.type === 'characteristic_size') return `размер «${this.ruleName(rules, node.characteristic_code)}»`;
    if (node.type === 'characteristic_size_positive') {
      return `max(0, размер «${this.ruleName(rules, node.characteristic_code)}»)`;
    }
    if (node.type === 'characteristic_size_gap') {
      return `разница размеров «${this.ruleName(rules, node.characteristic_code_from)}» − «${this.ruleName(rules, node.characteristic_code_to)}»`;
    }
    if (node.type === 'actionCharacteristic') {
      const characteristic = this.ruleName(rules, node.characteristic);
      const action = WEAPON_ACTION_GENITIVE[node.action] ?? node.action;
      const delta = node.modifier.reduce((sum, entry) => sum + entry.delta, 0);
      const extra = delta === 0 ? '' : ` ${delta > 0 ? '+' : '−'} ${Math.abs(delta)}`;
      const multiplier = node.multiplier && node.multiplier !== 1 ? ` ×${node.multiplier}` : '';

      return `${characteristic} ${action}${multiplier}${extra}`;
    }

    return '';
  }

  damageTypeLabel(code: string | null, rules: Rule[]): string | null {
    if (code === null) return null;

    return DAMAGE_TYPE_LABELS[code] ?? this.ruleName(rules, code);
  }

  weaponProfileFields(profile: WeaponProfile, rules: Rule[]): { label: string; value: string }[] {
    const distance = this.formula(profile.distance, rules);
    const range = profile.range ? this.formula(profile.range, rules) : null;
    const fields: { label: string; value: string }[] = [
      {
        label: this.damageTypeLabel(profile.damage.damage_type_code, rules) ?? 'Урон',
        value: this.formula(profile.damage.formula, rules),
      },
      { label: 'Пробитие', value: this.formula(profile.penetration, rules) },
      { label: 'Точность', value: this.dimensional(profile.accuracy) },
      { label: 'Дистанция', value: range ? `${distance}/${range}` : distance },
    ];
    if (profile.falloff) {
      fields.push({ label: 'Дальнобойность', value: this.dimensional(profile.falloff) });
    }
    if (profile.dodge_benefit !== undefined) {
      fields.push({ label: 'Польза уклонения', value: String(profile.dodge_benefit) });
    }

    return fields;
  }

  resistanceLabel(slot: ResistanceSlot): string {
    const dative = slot.damage_type_code ? DAMAGE_TYPE_FORMS[slot.damage_type_code]?.dative : null;
    const title = dative ? `Сопротивление ${dative}` : 'Сопротивление';
    const reliability = slot.durability === null ? 'абсолютная' : String(slot.durability);

    return `${title}: ${this.dimensional(slot.value)}, надёжность ${reliability}`;
  }

  grant(grant: Grant, rules: Rule[], keywords: Keyword[]): string {
    switch (grant.type) {
      case 'characteristic':
        return `Даёт характеристику «${this.ruleName(rules, grant.characteristic_code)}» (${this.dimensional(grant.value)})`;
      case 'characteristic_parameter':
        return `Даёт характеристику «${this.ruleName(rules, grant.characteristic_code)}» = параметр ${grant.parameter_code} × ${grant.per_unit}`;
      case 'characteristic_modify':
        return `Модификатор «${this.ruleName(rules, grant.characteristic_code)}»: ${this.formula(grant.amount, rules)}`;
      case 'resource':
        return `Даёт ресурс «${this.ruleName(rules, grant.resource_code)}» (лимит ${this.amount(grant.limit, rules)})`;
      case 'resource_limit_change':
        return `Меняет лимит «${this.ruleName(rules, grant.resource_code)}» на ${this.formula(grant.amount, rules)}`;
      case 'ability':
        return `Даёт способность «${this.ruleName(rules, grant.ability_code)}»`;
      case 'keyword':
        return grant.remove
          ? `Убирает признак «${this.keywordName(keywords, grant.keyword_code)}»`
          : `Добавляет признак «${this.keywordName(keywords, grant.keyword_code)}»`;
      case 'item': {
        const name = this.ruleName(rules, grant.item_code);
        const quantity = grant.quantity ?? 1;

        return quantity > 1 ? `Даёт предмет «${name}» ×${quantity}` : `Даёт предмет «${name}»`;
      }
      case 'magic_path':
        return `Даёт путь волшебства «${this.ruleName(rules, grant.path_code)}»`;
      case 'magic_study': {
        const maxCostLabel = typeof grant.max_cost === 'number' ? String(grant.max_cost) : 'значения характеристики';
        const scopeLabel =
          grant.scope === 'spell'
            ? `заклинаний со стоимостью ${maxCostLabel} и меньше`
            : `навыков волшебства (не заклинаний) со стоимостью ${maxCostLabel} и меньше`;
        const parts = [`Открывает изучение ${scopeLabel}`];
        if (grant.path_code) parts.push(`путь «${this.ruleName(rules, grant.path_code)}»`);
        if (grant.max_instances != null) {
          parts.push(grant.max_instances === 1 ? 'одно' : `не больше ${grant.max_instances}`);
        }
        if (grant.paid_cost != null) parts.push(`оплата ${grant.paid_cost} ОР`);

        return parts.join('; ');
      }
      case 'skill_study': {
        const names = grant.ability_codes.map((code) => this.ruleName(rules, code)).join(' / ');

        return `Изучение «${names}» до уровня ${grant.max_level} за ${grant.paid_cost} ОР`;
      }
      case 'resistance':
        return `Сопротивление «${this.ruleName(rules, grant.damage_type_code)}»: ${this.amount(grant.value, rules)} (${this.ruleName(rules, grant.source_code)})`;
      case 'sense_modify': {
        const status = grant.status ? `; статус ${grant.status}` : '';
        const lighting = grant.treat_as_good_down_to
          ? `; как при хорошем до «${LIGHTING_LEVEL_LABELS[grant.treat_as_good_down_to]}»`
          : '';

        return `Чувство «${this.ruleName(rules, grant.sense_code)}»: ${this.formula(grant.amount, rules)}${status}${lighting} (${this.ruleName(rules, grant.source_code)})`;
      }
      case 'process_distance_multiplier':
        return `Дистанция процесса «${this.ruleName(rules, grant.ability_code)}» ×${grant.multiplier}`;
      case 'state_modify':
        return `Состояние «${this.ruleName(rules, grant.state_code)}»: ${this.formula(grant.amount, rules)} (${this.ruleName(rules, grant.source_code)})`;
      case 'check_advantage':
        return `Преимущество на проверки (${grant.check_codes.join(', ') || '—'}): ${grant.amount >= 0 ? '+' : ''}${grant.amount}`;
      case 'check_efficiency':
        return `Эффективность проверок (${grant.check_codes.join(', ') || '—'}): ${grant.amount >= 0 ? '+' : ''}${grant.amount}`;
      case 'money':
        return `Стартовый капитал: ${grant.fixed} гз или ${grant.percent}% от лимита (${grant.apply === 'max' ? 'большее' : 'меньшее'})`;
    }
  }

  modifierOp(op: ItemModifierOp, rules: Rule[], keywords: Keyword[]): string {
    switch (op.type) {
      case 'weight': {
        const parts: string[] = [];
        if (op.factor != null) parts.push(`×${op.factor}`);
        if (op.add_kg != null) parts.push(`${op.add_kg >= 0 ? '+' : ''}${op.add_kg} кг`);

        return `Вес: ${parts.join(' · ') || 'без изменения'}`;
      }
      case 'min_strength':
        return `Мин. сила: ${op.delta >= 0 ? '+' : ''}${op.delta}`;
      case 'durability': {
        const parts: string[] = [];
        if (op.delta != null) parts.push(`${op.delta >= 0 ? '+' : ''}${op.delta}`);
        if (op.add_size != null) parts.push(`размер ${op.add_size >= 0 ? '+' : ''}${op.add_size}`);

        return `Прочность: ${parts.join(' · ')}`;
      }
      case 'block':
        return this.numericOpLabel('Блок', op);
      case 'defense':
        return this.numericOpLabel('Защита', op);
      case 'armor_reliability': {
        if (op.set != null) return `Надёжность брони: ${op.set}`;
        if (op.add != null) return `Надёжность брони: ${op.add >= 0 ? '+' : ''}${op.add}`;

        return 'Надёжность брони';
      }
      case 'max_agility': {
        const parts: string[] = [];
        if (op.delta != null) parts.push(`${op.delta >= 0 ? '+' : ''}${op.delta}`);
        if (op.add_size != null) parts.push(`размер ${op.add_size >= 0 ? '+' : ''}${op.add_size}`);

        return `Макс. ловкость: ${parts.join(' · ')}`;
      }
      case 'strength_penalty':
        if (op.set === null) return 'Штраф к силе: снят';
        if (op.set != null) return `Штраф к силе: ${op.set}`;
        if (op.add != null) return `Штраф к силе: ${op.add >= 0 ? '+' : ''}${op.add}`;

        return 'Штраф к силе';
      case 'characteristic_modify': {
        const name = this.ruleName(rules, op.characteristic_code);
        if (op.set === 0) return `Модификатор «${name}»: снят`;
        if (op.set != null) return `Модификатор «${name}»: ${op.set}`;
        if (op.add != null) return `Модификатор «${name}»: ${op.add >= 0 ? '+' : ''}${op.add}`;

        return `Модификатор «${name}»`;
      }
      case 'characteristic_limit': {
        const name = this.ruleName(rules, op.characteristic_code);
        const parts: string[] = [];
        if (op.delta != null) parts.push(`${op.delta >= 0 ? '+' : ''}${op.delta}`);
        if (op.add_size != null) parts.push(`размер ${op.add_size >= 0 ? '+' : ''}${op.add_size}`);

        return `Потолок «${name}»: ${parts.join(' · ')}`;
      }
      case 'action_strength': {
        const field = op.field === 'damage' ? 'урон' : 'пробитие';
        const profiles = op.profiles?.length ? ` (${op.profiles.join(', ')})` : '';

        return `Сила действия (${field}): ${op.delta >= 0 ? '+' : ''}${op.delta}${profiles}`;
      }
      case 'resistance': {
        const mode = { add: '+', add_size: 'размер +', max: 'макс. ' }[op.mode];

        return `Сопротивление «${this.ruleName(rules, op.damage_type_code)}»: ${mode}${op.value}`;
      }
      case 'keyword': {
        const add = (op.add ?? []).map((code) => this.keywordName(keywords, code));
        const remove = (op.remove ?? []).map((code) => this.keywordName(keywords, code));
        const parts: string[] = [];
        if (add.length) parts.push(`+ ${add.join(', ')}`);
        if (remove.length) parts.push(`− ${remove.join(', ')}`);

        return `Признаки: ${parts.join('; ')}`;
      }
      case 'min_action_cost':
        return `Минимум ОД: ${op.min}`;
      case 'magic_conductor':
        return `Проводник магии: ${op.value}`;
      case 'advantage':
        return `Помеха/преимущество: ${op.delta >= 0 ? '+' : ''}${op.delta} (${this.ruleName(rules, op.source_code)})`;
      case 'check_advantage': {
        const chars = op.characteristic_codes.map((code) => this.ruleName(rules, code)).join(', ');
        const hit = op.includes_hit ? ', включая попадание' : '';

        return `Помеха на проверки (${chars}${hit}): ${op.delta >= 0 ? '+' : ''}${op.delta}`;
      }
    }
  }

  private numericOpLabel(
    title: string,
    op: { factor?: number; add?: number; add_size?: number; min?: number },
  ): string {
    const parts: string[] = [];
    if (op.factor != null) parts.push(`×${op.factor}`);
    if (op.add != null) parts.push(`${op.add >= 0 ? '+' : ''}${op.add}`);
    if (op.add_size != null) parts.push(`размер ${op.add_size >= 0 ? '+' : ''}${op.add_size}`);
    if (op.min != null) parts.push(`мин. ${op.min}`);

    return `${title}: ${parts.join(' · ')}`;
  }
}
