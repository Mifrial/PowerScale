<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue';
import { useKeywords } from '@/modules/Roleplay/Keyword/init';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef';
import type { KeywordRef } from '@/modules/Roleplay/Rule/Dto/Ability/KeywordRef';
import type { SourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/SourceRef';
import { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_LABELS';
import { ABILITY_SPEC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SPEC_FIELDS';
import { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { ruleReferenceService } from '@/modules/Roleplay/Rule/Service/Instance/ruleReferenceService';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import RequirementListEditor from '@/modules/Roleplay/Rule/Component/Editors/RequirementListEditor.vue';
import GrantEditor from '@/modules/Roleplay/Rule/Component/Editors/GrantEditor.vue';
import ProcessEditor from '@/modules/Roleplay/Rule/Component/Editors/ProcessEditor.vue';
import SpellEditor from '@/modules/Roleplay/Rule/Component/Editors/SpellEditor.vue';
import ZoneCostsEditor from '@/modules/Roleplay/Rule/Component/Editors/ZoneCostsEditor.vue';
import ActionComponentsEditor from '@/modules/Roleplay/Rule/Component/Editors/ActionComponentsEditor.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import CatalogPlacementEditor from '@/modules/Roleplay/Rule/Component/Editors/CatalogPlacementEditor.vue';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

const props = defineProps<{
  name: string;
  code: string;
  codeDisabled?: boolean;
  description: string;
  mechanicId: number | null;
  keywordIds: number[];
  spec: RuleSpec | null;
  mechanicOptions: { title: string; value: number }[];
  keywordOptions: { title: string; value: number }[];
  spaceId: number;
  rules: Rule[];
  catalogSection: string | null;
  catalogSortOrder: number;
  sections: { code: string; name: string; parentCode: string | null; sortOrder: number }[];
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: AbilitySpec];
  'update:catalogSection': [value: string | null];
  'update:catalogSortOrder': [value: number];
}>();

const { keywords: catalogKeywords, error: keywordsError, fetchTags } = useKeywords();

const expandedPanels = ref<string[]>([
  'general',
  'zones',
  'requirements',
  'grants',
  'action_components',
  'spell',
  'upgrade',
]);

const typeOptions = Object.entries(ABILITY_TYPE_LABELS).map(([value, label]) => ({
  label,
  value: value as AbilityType,
}));

function emptyAbilityDraft(): AbilitySpecDraft {
  return {
    zones: {},
    requirements: [],
    grants: [],
    action_components: [],
    parent_ability_code: null,
  };
}

function abilityDraftFromSpec(value: RuleSpec | null): AbilitySpecDraft {
  if (!value || typeof value !== 'object') return emptyAbilityDraft();
  const loaded = cloneData(value as AbilitySpecDraft);

  return {
    ...loaded,
    type: loaded.type,
    zones: loaded.zones ?? {},
    requirements: loaded.requirements ?? [],
    grants: loaded.grants ?? [],
    action_components: loaded.action_components ?? [],
    process: loaded.process,
    spell: loaded.spell,
    parent_ability_code: loaded.parent_ability_code ?? null,
    group_code: loaded.group_code ?? null,
    selectLimit: loaded.selectLimit ?? 1,
  };
}

const innerSpec = ref<AbilitySpecDraft>(abilityDraftFromSpec(props.spec));

const currentType = computed<AbilityType | null>(() => {
  if (innerSpec.value.type) return innerSpec.value.type;
  const codes = props.keywordIds
    .map((id) => catalogKeywords.value.find((t) => t.id === id)?.code)
    .filter((c): c is string => !!c);

  return abilitySpecService.resolveTypeFromKeywords(codes);
});

const isGroup = computed(() => currentType.value === 'group');

const currentFields = computed(() => (currentType.value ? ABILITY_SPEC_FIELDS[currentType.value] : []));
const showActionComponents = computed(() => currentFields.value.includes('action_components'));
const isProcess = computed(() => currentFields.value.includes('process'));
const isSpell = computed(() => currentFields.value.includes('spell'));
const typeModel = computed<AbilityType | null>({
  get: () => currentType.value,
  set: (v) => setType(v),
});

const characteristics = computed<CharacteristicRef[]>(() =>
  ruleReferenceService.characteristicOptions(props.rules, props.spaceId),
);

const resources = computed<ResourceRef[]>(() => ruleReferenceService.resourceOptions(props.rules));

const abilities = computed<AbilityRef[]>(() => ruleReferenceService.abilityOptions(props.rules));

const groups = computed(() => ruleReferenceService.groupOptions(props.rules));

const items = computed(() => ruleReferenceService.itemOptions(props.rules));

const magicPaths = computed(() => ruleReferenceService.magicPathOptions(props.rules));

const keywords = computed<KeywordRef[]>(() => catalogKeywords.value.map((t) => ({ code: t.code, name: t.name })));

const abilityKeywords = computed<KeywordRef[]>(() => keywords.value);

const sources = computed<SourceRef[]>(() => ruleReferenceService.sourceOptions(props.rules));

const damageTypes = computed(() => ruleReferenceService.damageTypeOptions(props.rules));

const senses = computed(() => ruleReferenceService.senseOptions(props.rules));

const states = computed(() => ruleReferenceService.stateOptions(props.rules));

const zoneOptions = computed<{ label: string; value: string }[]>(() => ruleReferenceService.zoneOptions(props.rules));

function updateReqLevel(levelIndex: number, value: number) {
  innerSpec.value = abilitySpecService.updateReqLevel(innerSpec.value, levelIndex, value);
}

function updateRequirementLevelRequirements(levelIndex: number, reqs: Requirement[]) {
  innerSpec.value = abilitySpecService.updateRequirementLevelRequirements(innerSpec.value, levelIndex, reqs);
}

function removeRequirementLevel(levelIndex: number) {
  innerSpec.value = abilitySpecService.removeRequirementLevel(innerSpec.value, levelIndex);
}

function addRequirementLevel() {
  innerSpec.value = abilitySpecService.addRequirementLevel(innerSpec.value);
}

function updateGrantLevel(levelIndex: number, value: number) {
  innerSpec.value = abilitySpecService.updateGrantLevel(innerSpec.value, levelIndex, value);
}

function updateGrant(levelIndex: number, grantIndex: number, grant: Grant) {
  innerSpec.value = abilitySpecService.updateGrant(innerSpec.value, levelIndex, grantIndex, grant);
}

function removeGrant(levelIndex: number, grantIndex: number) {
  innerSpec.value = abilitySpecService.removeGrant(innerSpec.value, levelIndex, grantIndex);
}

function addGrant(levelIndex: number) {
  innerSpec.value = abilitySpecService.addGrant(innerSpec.value, levelIndex);
}

function removeGrantLevel(levelIndex: number) {
  innerSpec.value = abilitySpecService.removeGrantLevel(innerSpec.value, levelIndex);
}

function addGrantLevel() {
  innerSpec.value = abilitySpecService.addGrantLevel(innerSpec.value);
}

function patchSpec(key: string, value: unknown) {
  innerSpec.value = { ...innerSpec.value, [key]: value };
}

function setHasSpellUpgrade(enabled: boolean) {
  innerSpec.value = abilitySpecService.setSpellUpgrade(
    innerSpec.value,
    enabled ? abilitySpecService.createEmptySpellUpgrade() : null,
  );
}

function patchSpellUpgradeDelta(delta: number) {
  innerSpec.value = abilitySpecService.patchSpellUpgrade(innerSpec.value, { action_point_delta: delta });
}

function patchSpellUpgradeAdvantage(amount: number) {
  innerSpec.value = abilitySpecService.patchSpellUpgrade(innerSpec.value, {
    check_advantage: amount === 0 ? undefined : amount,
  });
}

function setHasStrikeUpgrade(enabled: boolean) {
  innerSpec.value = abilitySpecService.setStrikeUpgrade(
    innerSpec.value,
    enabled ? abilitySpecService.createEmptyStrikeUpgrade() : null,
  );
}

function patchStrikeUpgradeGroup(value: string) {
  innerSpec.value = abilitySpecService.patchStrikeUpgrade(innerSpec.value, { exclusive_group: value });
}

function setStrikeRequiresPhysiology(enabled: boolean) {
  innerSpec.value = abilitySpecService.patchStrikeUpgrade(innerSpec.value, {
    requires_physiology: enabled ? true : undefined,
  });
}

function patchStrikeModeCode(index: number, value: string) {
  innerSpec.value = abilitySpecService.patchStrikeUpgradeMode(innerSpec.value, index, { code: value });
}

function patchStrikeModeLabel(index: number, value: string) {
  innerSpec.value = abilitySpecService.patchStrikeUpgradeMode(innerSpec.value, index, { label: value });
}

function patchStrikeModeAdvantage(index: number, value: number) {
  innerSpec.value = abilitySpecService.patchStrikeUpgradeMode(innerSpec.value, index, {
    injury_check_advantage: value,
  });
}

function addStrikeUpgradeMode() {
  innerSpec.value = abilitySpecService.addStrikeUpgradeMode(innerSpec.value);
}

function removeStrikeUpgradeMode(index: number) {
  innerSpec.value = abilitySpecService.removeStrikeUpgradeMode(innerSpec.value, index);
}

function setType(value: string | null) {
  const type = (value as AbilityType | null) ?? null;
  let next: AbilitySpecDraft = { ...innerSpec.value, type: type ?? undefined };
  if (type === 'spell') {
    next = abilitySpecService.ensureHitResolution({
      ...next,
      spell: next.spell ?? abilitySpecService.createEmptySpellSpec(),
    });
  }
  innerSpec.value = next;
  if (type) {
    emit('update:keywordIds', abilitySpecService.syncTypeTags(type, props.keywordIds, catalogKeywords.value));
  }
  if (type === 'spell' || type === 'action') {
    innerSpec.value = abilitySpecService.ensureActionPointCost(innerSpec.value, type === 'spell');
  }
}

/** Смена группы у участника: кладёт group_code и признак домена (Внешность/Голос/Слух/Зрение). */
function updateGroupCode(value: string | null) {
  innerSpec.value = { ...innerSpec.value, group_code: value ?? null };
  emit('update:keywordIds', abilitySpecService.syncGroupPartTag(value, props.keywordIds, catalogKeywords.value));
}

const specToEmit = computed<AbilitySpec | AbilitySpecDraft>(() => {
  const type = currentType.value;
  const plain = cloneData(innerSpec.value);
  if (!type) return plain;

  return abilitySpecService.prune(plain, type);
});

watch(specToEmit, (value) => emit('update:spec', cloneData(value) as AbilitySpec), { deep: true, immediate: true });

onMounted(async () => {
  if (catalogKeywords.value.length === 0) {
    await fetchTags();
  }
  if (innerSpec.value.type === 'spell') {
    innerSpec.value = abilitySpecService.ensureHitResolution({
      ...innerSpec.value,
      spell: innerSpec.value.spell ?? abilitySpecService.createEmptySpellSpec(),
    });
  }
  if ((innerSpec.value.type === 'spell' || innerSpec.value.type === 'action') && !hasActionPointCost()) {
    innerSpec.value = abilitySpecService.ensureActionPointCost(innerSpec.value, innerSpec.value.type === 'spell');
  }
});

function hasActionPointCost(): boolean {
  return innerSpec.value.action_components.some(
    (c): c is Extract<AbilitySpecDraft['action_components'][number], { type: 'resource' }> =>
      c.type === 'resource' && c.resource_code === ACTION_POINTS_RESOURCE_CODE,
  );
}
</script>

<template>
  <div>
    <v-alert v-if="keywordsError" type="error" variant="tonal" density="compact" class="mb-2">
      {{ keywordsError }}
      <v-btn class="ml-2" size="small" variant="tonal" @click="fetchTags()">Повторить</v-btn>
    </v-alert>
    <v-expansion-panels v-model="expandedPanels" multiple>
      <v-expansion-panel value="general">
        <v-expansion-panel-title>Общее</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-select
            :model-value="typeModel"
            @update:model-value="(v) => (typeModel = v as AbilityType | null)"
            :items="typeOptions"
            item-title="label"
            item-value="value"
            label="Тип способности"
            :rules="[(v) => !!v || 'Обязательное поле']"
            density="compact"
            hide-details="auto"
            class="mb-2"
          />
          <div class="text-body-2 text-medium-emphasis mb-2">
            Тип определяет видимые блоки и карточку. Типообразующие признаки проставляются автоматически.
          </div>
          <RuleEditorBase
            :name="name"
            @update:name="(v) => emit('update:name', v)"
            :code="code"
            @update:code="(v) => emit('update:code', v)"
            :code-disabled="codeDisabled"
            :description="description"
            @update:description="(v) => emit('update:description', v)"
            :mechanic-id="mechanicId"
            @update:mechanic-id="(v) => emit('update:mechanicId', v)"
            :keyword-ids="keywordIds"
            @update:keyword-ids="(v) => emit('update:keywordIds', v)"
            :mechanic-options="mechanicOptions"
            :keyword-options="keywordOptions"
            :rules="rules"
          >
            <template #spec></template>
          </RuleEditorBase>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <CatalogPlacementEditor
        :catalog-section="catalogSection"
        :catalog-sort-order="catalogSortOrder"
        :sections="sections"
        @update:catalog-section="emit('update:catalogSection', $event)"
        @update:catalog-sort-order="emit('update:catalogSortOrder', $event)"
      />

      <v-expansion-panel v-if="isGroup" value="grouping">
        <v-expansion-panel-title>Группировка</v-expansion-panel-title>
        <v-expansion-panel-text>
          <ClampedNumberField
            :model-value="innerSpec.selectLimit ?? 1"
            @update:model-value="(v) => patchSpec('selectLimit', v)"
            label="Сколько способностей можно выбрать"
            :min="1"
            density="compact"
            hide-details
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Группа — контейнер: участники ссылаются на неё через group_code и несут признак «часть группы».
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="currentType && !isGroup" value="group">
        <v-expansion-panel-title>Группа</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-autocomplete
            :model-value="innerSpec.group_code ?? null"
            @update:model-value="updateGroupCode($event ?? null)"
            :items="groups"
            item-title="name"
            item-value="code"
            label="Группа"
            density="compact"
            hide-details
            clearable
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Способность отображается внутри группы и подчиняется её лимиту выбора. Признак «часть группы» проставляется
            автоматически.
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="!isGroup" value="zones">
        <v-expansion-panel-title>Зоны и стоимость</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Очки определяются зоной: каждая зона — очки-правило пространства. Зона без галочки — способность в ней не
            представлена.
          </div>
          <ZoneCostsEditor
            :model-value="innerSpec.zones"
            @update:model-value="(v) => patchSpec('zones', v)"
            :zone-options="zoneOptions"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="!isGroup" value="requirements">
        <v-expansion-panel-title>Требования</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Требования по уровням: уровень 1 — получение способности, уровни N — для взятия N-го уровня. Требования
            накапливаются (взял уровень N — уровни ниже уже выполнены).
          </div>
          <div
            v-for="(entry, levelIndex) in innerSpec.requirements"
            :key="`rbl-${levelIndex}`"
            class="pa-1 mb-2 rounded bg-accent"
          >
            <div class="bg-surface rounded pa-2">
              <div class="d-flex align-center mb-1">
                <ClampedNumberField
                  :model-value="entry.level"
                  @update:model-value="updateReqLevel(levelIndex, $event)"
                  label="Уровень"
                  :min="1"
                  density="compact"
                  hide-details
                  style="min-width: 120px"
                />
                <v-chip v-if="entry.level === 1" class="ml-2" size="x-small" color="primary" variant="tonal">
                  Получение
                </v-chip>
                <v-spacer />
                <v-btn icon size="x-small" color="error" variant="text" @click="removeRequirementLevel(levelIndex)">
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </div>
              <RequirementListEditor
                :model-value="entry.requirements"
                @update:model-value="(v) => updateRequirementLevelRequirements(levelIndex, v)"
                :characteristics="characteristics"
                :resources="resources"
                :abilities="abilities"
                :keywords="keywords"
                :ability-keywords="abilityKeywords"
                :magic-paths="magicPaths"
              />
            </div>
          </div>
          <v-btn variant="text" color="primary" size="small" @click="addRequirementLevel">
            <v-icon start>mdi-plus</v-icon>
            Добавить уровень
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="!isGroup" value="grants">
        <v-expansion-panel-title>Дары</v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="text-body-2 text-medium-emphasis mb-2">
            Дары по уровням: уровень 1 — при получении, уровни N — при достижении N-го уровня. «Постоянный» — дар
            действует на всех уровнях ≥ этого (по умолчанию); без него — строго на своём уровне. Формулы уровня
            масштабируются сами — не дублируйте.
          </div>
          <div
            v-for="(entry, levelIndex) in innerSpec.grants"
            :key="`l-${levelIndex}`"
            class="pa-1 mb-2 rounded bg-accent"
          >
            <div class="bg-surface rounded pa-2">
              <div class="d-flex align-center mb-1">
                <ClampedNumberField
                  :model-value="entry.level"
                  @update:model-value="updateGrantLevel(levelIndex, $event)"
                  label="Уровень"
                  :min="1"
                  density="compact"
                  hide-details
                  style="min-width: 120px"
                />
                <v-chip v-if="entry.level === 1" class="ml-2" size="x-small" color="primary" variant="tonal">
                  Получение
                </v-chip>
                <v-spacer />
                <v-btn icon size="x-small" color="error" variant="text" @click="removeGrantLevel(levelIndex)">
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </div>
              <div v-for="(grant, grantIndex) in entry.grants" :key="`l-${levelIndex}-${grantIndex}`" class="mb-1">
                <GrantEditor
                  :model-value="grant"
                  :characteristics="characteristics"
                  :resources="resources"
                  :abilities="abilities"
                  :keywords="keywords"
                  :items="items"
                  :magic-paths="magicPaths"
                  :sources="sources"
                  :damage-types="damageTypes"
                  :senses="senses"
                  :states="states"
                  @update:model-value="(v) => updateGrant(levelIndex, grantIndex, v)"
                  @remove="removeGrant(levelIndex, grantIndex)"
                />
              </div>
              <v-btn variant="text" color="primary" size="small" @click="addGrant(levelIndex)">
                <v-icon start>mdi-plus</v-icon>
                Добавить дар
              </v-btn>
            </div>
          </div>
          <v-btn variant="text" color="primary" size="small" @click="addGrantLevel">
            <v-icon start>mdi-plus</v-icon>
            Добавить уровень
          </v-btn>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="showActionComponents" value="action_components">
        <v-expansion-panel-title>Компоненты действия</v-expansion-panel-title>
        <v-expansion-panel-text>
          <ActionComponentsEditor
            :model-value="innerSpec.action_components"
            @update:model-value="(v) => patchSpec('action_components', v)"
            :resources="resources"
            :items="items"
            :keywords="keywords"
            :is-spell="isSpell"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="isProcess" value="process">
        <v-expansion-panel-title>Процесс</v-expansion-panel-title>
        <v-expansion-panel-text>
          <ProcessEditor
            :model-value="innerSpec.process ?? null"
            @update:model-value="(v) => patchSpec('process', v)"
            :resources="resources"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="isSpell" value="spell">
        <v-expansion-panel-title>Заклинание</v-expansion-panel-title>
        <v-expansion-panel-text>
          <SpellEditor
            :model-value="innerSpec.spell ?? null"
            :hit-resolution="innerSpec.hit_resolution"
            :parameters="innerSpec.parameters ?? []"
            :rules="rules"
            @update:model-value="(v) => patchSpec('spell', v)"
            @update:hit-resolution="(v) => patchSpec('hit_resolution', v)"
          />
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="!isGroup" value="upgrade">
        <v-expansion-panel-title>Улучшение</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-autocomplete
            :model-value="innerSpec.parent_ability_code"
            @update:model-value="patchSpec('parent_ability_code', $event ?? null)"
            :items="abilities"
            item-title="name"
            item-value="code"
            label="Родительская способность"
            density="compact"
            hide-details
            clearable
          />
          <div class="text-body-2 text-medium-emphasis mt-2">
            Если способность — улучшение другой, укажите родительскую способность.
          </div>
          <v-checkbox
            :model-value="!!innerSpec.spell_upgrade"
            label="Модификатор каста"
            density="compact"
            hide-details
            class="mt-2"
            @update:model-value="(v) => setHasSpellUpgrade(!!v)"
          />
          <div v-if="innerSpec.spell_upgrade" class="d-flex ga-2 mt-2 flex-wrap">
            <ClampedNumberField
              :model-value="innerSpec.spell_upgrade.action_point_delta"
              label="Дельта ОД сотворения"
              density="compact"
              hide-details
              style="max-width: 180px"
              @update:model-value="patchSpellUpgradeDelta"
            />
            <ClampedNumberField
              :model-value="innerSpec.spell_upgrade.check_advantage ?? 0"
              label="Преимущество на сотворение"
              density="compact"
              hide-details
              style="max-width: 220px"
              @update:model-value="patchSpellUpgradeAdvantage"
            />
          </div>
          <v-checkbox
            :model-value="!!innerSpec.strike_upgrade"
            label="Модификатор удара"
            density="compact"
            hide-details
            class="mt-2"
            @update:model-value="(v) => setHasStrikeUpgrade(!!v)"
          />
          <div v-if="innerSpec.strike_upgrade" class="d-flex flex-column ga-2 mt-2">
            <v-text-field
              :model-value="innerSpec.strike_upgrade.exclusive_group"
              label="Группа взаимоисключения"
              density="compact"
              hide-details
              @update:model-value="patchStrikeUpgradeGroup"
            />
            <v-checkbox
              :model-value="innerSpec.strike_upgrade.requires_physiology === true"
              label="Нужна физиология цели"
              density="compact"
              hide-details
              @update:model-value="(v) => setStrikeRequiresPhysiology(!!v)"
            />
            <div
              v-for="(mode, index) in innerSpec.strike_upgrade.modes"
              :key="index"
              class="d-flex ga-2 flex-wrap align-center"
            >
              <v-text-field
                :model-value="mode.code"
                label="Код режима"
                density="compact"
                hide-details
                style="max-width: 160px"
                @update:model-value="(v) => patchStrikeModeCode(index, String(v ?? ''))"
              />
              <v-text-field
                :model-value="mode.label"
                label="Подпись"
                density="compact"
                hide-details
                style="max-width: 180px"
                @update:model-value="(v) => patchStrikeModeLabel(index, String(v ?? ''))"
              />
              <ClampedNumberField
                :model-value="mode.injury_check_advantage"
                label="Преим. увечья"
                density="compact"
                hide-details
                style="max-width: 160px"
                @update:model-value="(v) => patchStrikeModeAdvantage(index, v)"
              />
              <v-btn
                icon="mdi-delete-outline"
                variant="text"
                size="small"
                :disabled="innerSpec.strike_upgrade.modes.length < 2"
                @click="removeStrikeUpgradeMode(index)"
              />
            </div>
            <v-btn variant="text" size="small" class="align-self-start" @click="addStrikeUpgradeMode"> Режим </v-btn>
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
.gap-4 {
  gap: 16px;
}
</style>
