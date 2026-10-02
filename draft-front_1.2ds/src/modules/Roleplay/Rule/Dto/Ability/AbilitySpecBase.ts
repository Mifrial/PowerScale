import type { ZoneId } from '@/modules/Roleplay/Rule/Dto/Ability/ZoneId';
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { HitResolution } from '@/modules/Roleplay/Rule/Dto/Ability/HitResolution';
import type { SpellUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/SpellUpgrade';
import type { StrikeUpgrade } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeUpgrade';
import type { PushSpec } from '@/modules/Roleplay/Rule/Dto/Ability/PushSpec';

/** Общие поля способности (не типоспецифичные). */
export interface AbilitySpecBase {
  zones: Partial<Record<ZoneId, AbilityCost>>;
  requirements: { level: number; requirements: Requirement[] }[];
  grants: { level: number; grants: Grant[] }[];
  /** Временные эффекты действия, исполняемые боевым движком. */
  action_effects?: ActionEffect[];
  /** Доставка попадания. У заклинания обязательна; у обычного действия может отсутствовать. */
  hit_resolution?: HitResolution;
  /** Режим выбора целей для атакующего действия. */
  attack_mode?: 'single' | 'wide';
  /** Нижняя граница итоговой стоимости действия в ОД после pending. */
  min_total_action_cost?: number;
  /** Сколько последовательных ударов в одном запуске; нет — один. Не для Сдвоенного. */
  strike_count?: number;
  /** Удары должны быть разным экземпляром оружия. */
  distinct_weapons?: boolean;
  /** Несколько экземпляров одного оружия, один бросок, N попаданий. */
  same_weapon?: boolean;
  /** Минимум экземпляров для same_weapon. */
  min_weapons?: number;
  /** Максимум экземпляров для same_weapon; навык-ребёнок может снять. */
  max_weapons?: number;
  /** У улучшения родителя same_weapon: снять max_weapons. */
  lift_parent_max_weapons?: boolean;
  /** Толчок: контест Силы или урона оружия вместо обычного попадания. */
  push?: PushSpec;
  max_targets?: number;
  parent_ability_code: string | null;
  /** Модификатор каста (дельта ОД, преимущество сотворения, цепь). С родителем — на то заклинание; без — на выбранный каст. */
  spell_upgrade?: SpellUpgrade;
  /** Трата РУ успешного сотворения на шаги мощи. */
  spell_saturation?: {
    min_rating: number;
    rating_per_step: number;
    power_per_step: number;
  };
  /** Бонус сложности следующего сотворения, если после насыщения осталось достаточно РУ. */
  next_cast_difficulty?: {
    min_remaining_rating: number;
    delta: number;
    source_code?: string;
  };
  /**
   * Доставка заклинания касанием. Нет поля — урон оружия применяется, бонус РУ 0.
   * Бонус прибавляется к исходному РУ успешного удара, если он уже не меньше 1.
   */
  spell_touch?: {
    weapon_damage: boolean;
    attack_sr_bonus: number;
  };
  /** Роль в бою. Нет роли — действие этим путём не находится. */
  combat_action?: 'dodge' | 'block' | 'turn' | 'wait' | 'recover-stability' | 'simple-touch';
  /** Включает трату нескольких жетонов. Пороги и «уровень + 1» остаются в сервисе. */
  peak_concentration?: boolean;
  /** Включает трату жетона на проверки воли. Порог воли остаётся в сервисе. */
  will_focus?: boolean;
  /** Включает концентрацию длиннее одного хода. Потолок ходов остаётся в сервисе. */
  long_tension?: boolean;
  /** Альтернативный защитник одной реакции. Цена — в action_components. */
  cover_ally?: {
    circumstance_delta: number;
  };
  /** Преимущество на защиту от атаки, которой владеет защитник. */
  known_attack_defense?: {
    delta: number;
    source_code?: string;
  };
  /** Модификатор запуска удара (режимы на проверку увечья). Родитель карточки — только покупка. */
  strike_upgrade?: StrikeUpgrade;
  /** Для способностей владения оружием — код предмета-оружия (напр. «sword»). */
  weapon_item_code?: string | null;
  /** Параметры «X»: подстановка `{code}` в цене/дарах/описании (Дискуссия 2). */
  parameters?: AbilityParameter[];
  /** Код группирующего правила (type 'group'), в которое входит способность («часть группы»). */
  group_code?: string | null;
  /**
   * Множественный навык (напр. «Владение языком», «Знание о животных»): изучается по домену
   * (значение выбирается при покупке — из справочника или кастомный текст).
   */
  multiple?: boolean;
  /**
   * Код справочника домена для множественного навыка (напр. 'language' | 'region' | 'species' |
   * 'culture' | 'subject' | 'instrument'). Заполняется вместе с `multiple`.
   */
  domain_ref?: string | null;
  /** Шаблон знания: фиксирует тип поля экземпляра `znanie`, сам на лист не покупается. */
  knowledge_template_field?: string | null;
  /** У улучшения: фильтр типа знания родителя `znanie` (напр. `laws`). */
  parent_knowledge_field?: string | null;
  /**
   * Агрегат «Развитие X» (D108): бесплатный навык-агрегатор. Уровень агрегата определяется
   * по числу взятых навыков с признаком method_keyword: уровень N достигнут, если для каждой
   * ступени стоимости 1..N есть минимум `levels[N-1]` методов со стоимостью ≥ N (без пересечения).
   * Бонус уровня раздаётся даром characteristic_modify от ability_level самого агрегата.
   */
  aggregate?: {
    characteristic_code: string;
    method_keyword: string;
    /** levels[N-1] = минимум методов со стоимостью ≥ N для уровня N. */
    levels: number[];
  } | null;
  /**
   * Производный уровень способности (D109): уровень = число порогов thresholds, не
   * превышающих «опыт» = сумму стоимостей взятых способностей с признаком source_keyword
   * (напр. «Ближний бой»: опыт ближнего боя → уровень при 2/8/16).
   */
  derived_level?: { source_keyword: string; thresholds: number[] } | null;
  /** Изменение размерности собственного шага, задаваемое врождённой/расовой способностью. */
  movement_step_size_delta?: number;
}
