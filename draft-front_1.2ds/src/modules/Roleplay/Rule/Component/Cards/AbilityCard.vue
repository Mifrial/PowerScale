<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_LABELS';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { resourceShortName } from '@/modules/Roleplay/Rule/Utils/resourceShortName';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
  keywords: { id: number; code: string; name: string }[];
}>();

const spec = computed<AbilitySpecDraft | null>(() => (props.rule.spec as AbilitySpecDraft) ?? null);

const ruleTagCodes = computed(() => {
  const ids = props.rule.keywordIds ?? [];

  return props.keywords.filter((t) => ids.includes(t.id)).map((t) => t.code);
});

const type = computed<AbilityType | null>(() => {
  const s = spec.value;
  if (s?.type) return s.type;

  return abilitySpecService.resolveTypeFromKeywords(ruleTagCodes.value);
});

const groupInfo = computed(() => {
  const s = spec.value;
  if (s?.type !== 'group') return null;

  return {
    selectLimit: s.selectLimit ?? -1,
    members: props.rules.filter(
      (r) =>
        r.type === 'ability' && (r.spec as { group_code?: string | null } | undefined)?.group_code === props.rule.code,
    ),
  };
});

const actionComponents = computed(() => spec.value?.action_components ?? []);
const resourceComponents = computed(() => actionComponents.value.filter((c) => c.type === 'resource'));
const nonResourceComponents = computed(() => actionComponents.value.filter((c) => c.type !== 'resource'));

const sortedRequirements = computed(() => [...(spec.value?.requirements ?? [])].sort((a, b) => a.level - b.level));

const sortedGrants = computed(() => [...(spec.value?.grants ?? [])].sort((a, b) => a.level - b.level));

const pointNames = computed(() => {
  const map = new Map<string, string>();
  for (const r of props.rules) {
    if (r.type === 'points') map.set(r.code, r.name);
  }

  return map;
});

function zoneLabel(zone: string): string {
  return pointNames.value.get(zone) ?? zone;
}

function resourceName(code: string): string {
  const r = props.rules.find((rr) => rr.code === code && rr.type === 'resource');

  return r?.name ?? code;
}

function abilityName(code: string): string {
  const r = props.rules.find((rr) => rr.code === code && rr.type === 'ability');

  return r?.name ?? code;
}

function formatAmount(amount: unknown): string {
  if (typeof amount === 'number') return String(amount);
  const a = amount as { base?: number; size?: number };
  if (a && typeof a.base === 'number' && typeof a.size === 'number') {
    return `${a.base}${a.size ? `×${a.size}` : ''}`;
  }

  return String(amount ?? '');
}

function formatDimensional(v: { base: number; size: number }): string {
  return `${v.base}${v.size ? `×${v.size}` : ''}`;
}

const durationLabel = computed(() => {
  const d = spec.value?.spell?.duration;
  if (!d) return '—';
  if (d.type === 'instant') return 'Мгновенное';
  const parts = [d.type === 'refreshable' ? 'Обновляемое' : 'Поддерживаемое'];
  if (d.difficulty) parts.push(`сложность ${formatDimensional(d.difficulty)}`);
  if (d.action_cost !== undefined) parts.push(`${formatAmount(d.action_cost)} ОД`);
  if (d.limit) parts.push(`предел ${formatAmount(d.limit.value)} ${limitUnitLabel(d.limit.unit)}`);

  return parts.join(', ');
});

function limitUnitLabel(unit: string): string {
  return { turn: 'ход', minute: 'мин', hour: 'час' }[unit] ?? unit;
}

function componentLabel(comp: ActionComponent): string {
  if (comp.type === 'resource') {
    const short = resourceShortName(comp.resource_code);

    return short
      ? `${formatAmount(comp.amount)} ${short}`
      : `${comp.label ?? resourceName(comp.resource_code)}: ${formatAmount(comp.amount)}`;
  }
  if (comp.type === 'verbal') return comp.note ? `Вербальный (${comp.note})` : 'Вербальный';
  if (comp.type === 'somatic') return comp.note ? `Соматический (${comp.note})` : 'Соматический';
  const mode = comp.mode === 'consume' ? 'израсходовать' : 'использовать';
  if (comp.item_code) {
    const name = props.rules.find((r) => r.code === comp.item_code)?.name ?? comp.item_code;

    return `Материальный: ${mode} ${name}`;
  }
  if (comp.keyword_codes?.length) {
    const tags = comp.keyword_codes.map((code) => props.keywords.find((t) => t.code === code)?.name ?? code).join(', ');

    return `Материальный: ${mode} предмет с тегами ${tags}`;
  }

  return comp.description ? `Материальный: ${comp.description}` : 'Материальный';
}

function stepName(code: string): string {
  const s = spec.value?.process?.steps.find((st) => st.code === code);

  return s?.name ?? code;
}

const transitionSummary = computed(() => {
  const t = spec.value?.process?.transition;
  if (!t) return '';
  if (t.mode === 'chain') {
    const dir = t.direction === 'forward' ? 'только вперёд' : 'в обе стороны';

    return `Переходы: ±${t.max_shift} шаг(а) (${dir}); повторить шаг можно всегда.`;
  }
  if (t.mode === 'free') return 'Переходы: с любого шага на любой (включая повтор).';
  const edges = (t.edges ?? []).filter((e) => e.from && e.to);
  if (!edges.length) return 'Переходы: не заданы.';

  return `Переходы: ${edges.map((e) => `${stepName(e.from)} → ${stepName(e.to)}`).join(', ')}`;
});

const failureLabel = computed(() => {
  const f = spec.value?.process?.failure;
  if (f === 'restart_from_first') return 'При провале шага — действие начинается заново с первого шага.';
  if (f === 'end_action') return 'При провале шага — действие завершается.';

  return '';
});

const hasGrants = computed(() => {
  const g = spec.value?.grants;

  return !!g && g.length > 0 && g.some((entry) => entry.grants.length > 0);
});

function zoneCostLabel(cost: unknown): string {
  const c = cost as {
    kind: string;
    levels_cost?: number[];
    max_level?: number;
    base_cost?: number;
    step?: number;
    parameter_code?: string;
    per_unit?: number;
  };
  if (c.kind === 'array') return `массив: ${(c.levels_cost ?? []).join(', ')}`;
  if (c.kind === 'progression') return `прогрессия: ${c.base_cost} + (ур−1)×${c.step}, макс. ${c.max_level}`;
  if (c.kind === 'parameter') return `параметр «${c.parameter_code ?? 'x'}» × ${c.per_unit ?? 1}`;

  return 'авто-получение';
}

function requirementsSummary(reqs: Requirement[]): string {
  return reqs.map(reqText).join('; ');
}

function reqText(req: Requirement): string {
  switch (req.type) {
    case 'has_ability':
      return `${abilityName(req.ability_code)}${req.min_level ? ` ≥${req.min_level}` : ''}`;
    case 'has_ability_keyword':
      return `${req.min_count} способность(ей) с признаком «${req.keyword_code}»`;
    case 'has_keyword':
      return `признак «${req.keyword_code}»`;
    case 'characteristic_value':
      return `${req.characteristic_code} ≥ ${formatDimensional(req.min)}`;
    case 'resource_limit':
      return `ресурс «${resourceName(req.resource_code)}»${req.min ? ` ≥ ${formatAmount(req.min)}` : ''}`;
    case 'and':
      return `(${req.children.map(reqText).join(' и ')})`;
    case 'or':
      return `(${req.children.map(reqText).join(' или ')})`;
    default:
      return '';
  }
}

function formulaLabel(f: Formula): string {
  if (f.type === 'fixed') return String(f.value);
  if (f.type === 'characteristic')
    return `${f.characteristic_code}${f.modifier ? ` ${f.modifier > 0 ? '+' : ''}${f.modifier}` : ''}`;
  if (f.type === 'ability_level')
    return `ур. ${abilityName(f.ability_code)}${f.multiplier && f.multiplier !== 1 ? `×${f.multiplier}` : ''}`;
  if (f.type === 'dimensional') return formatDimensional(f);

  return '';
}

function grantLabel(grant: Grant): string {
  switch (grant.type) {
    case 'characteristic':
      return `Даёт характеристику «${grant.characteristic_code}» (${formatDimensional(grant.value)})`;
    case 'characteristic_parameter':
      return `Даёт характеристику «${grant.characteristic_code}» = параметр ${grant.parameter_code} × ${grant.per_unit}`;
    case 'characteristic_modify':
      return `Модификатор «${grant.characteristic_code}»: +${formulaLabel(grant.amount)}`;
    case 'resource':
      return `Даёт ресурс «${resourceName(grant.resource_code)}» (лимит ${formatAmount(grant.limit)})`;
    case 'resource_limit_change':
      return `Меняет лимит ресурса «${resourceName(grant.resource_code)}» на ${formulaLabel(grant.amount)}`;
    case 'ability':
      return `Даёт способность «${abilityName(grant.ability_code)}»`;
    case 'keyword':
      return grant.remove ? `Убирает признак «${grant.keyword_code}»` : `Добавляет признак «${grant.keyword_code}»`;
    case 'item': {
      const quantity = grant.quantity ?? 1;

      return quantity > 1 ? `Даёт предмет «${grant.item_code}» ×${quantity}` : `Даёт предмет «${grant.item_code}»`;
    }
    case 'money':
      return `Стартовый капитал: ${grant.fixed} гз или ${grant.percent}% от лимита (${grant.apply === 'max' ? 'большее' : 'меньшее'})`;
    default:
      return '';
  }
}
</script>

<template>
  <div v-if="spec">
    <div v-if="type" class="mb-2">
      <v-chip color="primary" variant="tonal" size="small">{{ ABILITY_TYPE_LABELS[type] }}</v-chip>
    </div>

    <v-card v-if="groupInfo" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-body-2">
          Выбрать:
          <strong>{{
            groupInfo.selectLimit === -1 ? 'без лимита' : groupInfo.selectLimit === 1 ? 'одну' : groupInfo.selectLimit
          }}</strong>
          из {{ groupInfo.members.length }}
        </div>
        <div v-if="groupInfo.members.length" class="text-body-2 text-medium-emphasis mt-1">
          Участники: {{ groupInfo.members.map((m) => m.name).join(', ') }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="type === 'action' || type === 'spell'" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Действие</div>
        <div v-for="(comp, index) in resourceComponents" :key="index" class="d-flex align-center ga-2">
          <span>{{ comp.label ?? resourceName(comp.resource_code) }}:</span>
          <strong>{{ formatAmount(comp.amount) }}</strong>
        </div>
        <div v-if="!resourceComponents.length" class="text-medium-emphasis">Без стоимости</div>
        <div v-if="nonResourceComponents.length && type !== 'spell'" class="mt-2">
          <div class="text-body-2 text-medium-emphasis mb-1">Компоненты</div>
          <div class="d-flex flex-wrap ga-2">
            <v-chip v-for="(comp, index) in nonResourceComponents" :key="index" size="small" variant="outlined">
              {{ componentLabel(comp) }}
            </v-chip>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="type === 'process' && spec.process" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Процесс</div>
        <div v-if="spec.process.start_step_code" class="text-body-2 mb-1">
          Начало: <strong>{{ stepName(spec.process.start_step_code) }}</strong>
        </div>
        <div class="text-body-2 mb-2">{{ transitionSummary }}</div>
        <v-list density="compact">
          <v-list-item
            v-for="(step, index) in spec.process.steps"
            :key="step.code"
            :title="`${index + 1}. ${step.name}`"
          >
            <template #subtitle>
              <div class="ability-card__step-desc">{{ step.description }}</div>
              <div v-if="step.costs?.length" class="ability-card__step-cost">
                {{
                  step.costs
                    .map((c) =>
                      resourceShortName(c.resource_code)
                        ? `${formatAmount(c.amount)} ${resourceShortName(c.resource_code)}`
                        : `${resourceName(c.resource_code)}: ${formatAmount(c.amount)}`,
                    )
                    .join(', ')
                }}
              </div>
            </template>
          </v-list-item>
        </v-list>
        <div v-if="spec.process.failure" class="text-body-2 text-medium-emphasis mt-1">
          {{ failureLabel }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="type === 'spell' && spec.spell" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Заклинание</div>
        <div class="d-flex align-center ga-2 mb-1">
          <span>Сложность сотворения:</span>
          <strong>{{ formatDimensional(spec.spell.difficulty) }}</strong>
        </div>
        <div class="mb-1">Продолжительность: {{ durationLabel }}</div>
        <div v-if="nonResourceComponents.length" class="d-flex flex-wrap ga-2">
          <v-chip v-for="(comp, index) in nonResourceComponents" :key="index" size="small" variant="outlined">
            {{ componentLabel(comp) }}
          </v-chip>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="Object.keys(spec.zones ?? {}).length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Цена</div>
        <div v-for="(cost, zone) in spec.zones" :key="zone" class="text-body-2">
          <strong>{{ zoneLabel(zone) }}</strong
          >: {{ zoneCostLabel(cost) }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="spec.requirements?.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Требования</div>
        <div v-for="entry in sortedRequirements" :key="`r-${entry.level}`" class="text-body-2">
          {{ entry.level === 1 ? 'Получение' : `Уровень ${entry.level}` }}:
          {{ requirementsSummary(entry.requirements) }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="hasGrants" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Дары</div>
        <div v-for="entry in sortedGrants" :key="`l-${entry.level}`" class="text-body-2">
          {{ entry.level === 1 ? 'Получение' : `Уровень ${entry.level}` }}:
          <span v-for="(grant, index) in entry.grants" :key="index">
            {{ grantLabel(grant) }}<template v-if="grant.permanent === false"> (только уровень)</template
            >{{ index < entry.grants.length - 1 ? '; ' : '' }}
          </span>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="spec.parent_ability_code" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-body-2">
          Улучшение: <strong>{{ abilityName(spec.parent_ability_code) }}</strong>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
/* Переносы строк внутри описаний шага процесса. */
.ability-card__step-desc,
.ability-card__step-cost {
  white-space: pre-line;
}
</style>
