<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { ItemModifierSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierSpec';
import RuleEditorBase from '@/modules/Roleplay/Rule/Component/Editors/RuleEditorBase.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import ItemModifierOpsEditor from '@/modules/Roleplay/Rule/Component/Editors/ItemModifierOpsEditor.vue';
import type { ItemModifierOp } from '@/modules/Roleplay/Rule/Dto/Item/ItemModifierOp';
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
  /** Код правила типа `item_modifier_type`. */
  typeOptions: { title: string; value: string }[];
  /** id → код признака (для селекторов специфики — спека хранит коды). */
  keywordCodeById: Map<number, string>;
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:mechanicId': [value: number | null];
  'update:keywordIds': [value: number[]];
  'update:spec': [value: ItemModifierSpec];
}>();

function emptySpec(): ItemModifierSpec {
  return {
    type_code: '',
    applies: { keyword_all: [], keyword_any: [], keyword_none: [] },
    price: { factor: null, add_gm: null, add_gm_per_100g: null, min_final_gm: null },
    effects: [],
  };
}

const draft = ref<ItemModifierSpec>(props.spec ? (cloneData(props.spec) as ItemModifierSpec) : emptySpec());

watch(
  () => props.spec,
  (value) => {
    draft.value = value ? { ...emptySpec(), ...(cloneData(value) as ItemModifierSpec) } : emptySpec();
  },
);

const specToEmit = computed<ItemModifierSpec>(() => cloneData(draft.value));

watch(specToEmit, (value) => emit('update:spec', value), { deep: true });

/** Селект-опции признаков (id → код → вариант). */
const codeOptions = computed(() =>
  props.keywordOptions.map((option) => ({
    title: option.title,
    value: props.keywordCodeById.get(option.value) ?? String(option.value),
  })),
);

function addEffect(): void {
  draft.value.effects.push({ text: '' });
}

function removeEffect(index: number): void {
  draft.value.effects = draft.value.effects.filter((_, i) => i !== index);
}

function updateEffectText(index: number, text: string): void {
  draft.value.effects = draft.value.effects.map((effect, i) => (i === index ? { ...effect, text } : effect));
}

function updateEffectLabel(index: number, label: string): void {
  draft.value.effects = draft.value.effects.map((effect, i) =>
    i === index ? { ...effect, label: label || null } : effect,
  );
}

function updateEffectOps(index: number, ops: ItemModifierOp[]): void {
  draft.value.effects = draft.value.effects.map((effect, i) => (i === index ? { ...effect, ops } : effect));
}
</script>

<template>
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
  >
    <template #spec>
      <v-select
        v-model="draft.type_code"
        :items="typeOptions"
        item-title="title"
        item-value="value"
        label="Тип модификатора"
        hint="Категория (качество изделия, вес, материал…). Exclusive задаётся на типе."
        density="compact"
        class="mt-2 mb-4"
      />
      <v-expansion-panels multiple>
        <v-expansion-panel>
          <v-expansion-panel-title>Применимость</v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-select
              v-model="draft.applies.keyword_all"
              :items="codeOptions"
              item-title="title"
              item-value="value"
              label="Требует все признаки (AND)"
              hint="Предмет обязан иметь все выбранные признаки (например «weapon» — оружие, «metal» — металлическое)."
              multiple
              chips
              closable-chips
              density="compact"
              class="mt-2"
            />

            <v-select
              v-model="draft.applies.keyword_any"
              :items="codeOptions"
              item-title="title"
              item-value="value"
              label="Требует один из признаков (OR)"
              hint="Предмет обязан иметь хотя бы один из выбранных признаков."
              multiple
              clearable
              chips
              closable-chips
              density="compact"
              class="mt-2"
            />

            <v-select
              v-model="draft.applies.keyword_none"
              :items="codeOptions"
              item-title="title"
              item-value="value"
              label="Запрещённые признаки"
              hint="Предмет НЕ должен иметь ни одного из этих признаков (например «two-handed»)."
              multiple
              clearable
              chips
              closable-chips
              density="compact"
              class="mt-2"
            />
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel>
          <v-expansion-panel-title>Влияние на цену</v-expansion-panel-title>
          <v-expansion-panel-text>
            <div class="d-flex gap-2 flex-wrap">
              <ClampedNumberField
                :model-value="draft.price.factor ?? 0"
                @update:model-value="(v: number) => (draft.price.factor = v !== 0 ? v : null)"
                label="Множитель (×)"
                :min="0"
                step="0.1"
                density="compact"
                hide-details
                style="flex: 1 1 160px"
              />
              <ClampedNumberField
                :model-value="draft.price.add_gm ?? 0"
                @update:model-value="(v: number) => (draft.price.add_gm = v !== 0 ? v : null)"
                label="Слагаемое (гм)"
                density="compact"
                hide-details
                style="flex: 1 1 160px"
              />
              <ClampedNumberField
                :model-value="draft.price.add_gm_per_100g ?? 0"
                @update:model-value="(v: number) => (draft.price.add_gm_per_100g = v !== 0 ? v : null)"
                label="На 100 г веса (гм)"
                density="compact"
                hide-details
                style="flex: 1 1 160px"
              />
              <ClampedNumberField
                :model-value="draft.price.min_final_gm ?? 0"
                @update:model-value="(v: number) => (draft.price.min_final_gm = v !== 0 ? v : null)"
                label="Мин. итог (гм)"
                density="compact"
                hide-details
                style="flex: 1 1 160px"
              />
            </div>
            <div class="text-body-2 text-medium-emphasis mt-2">
              Формула: итог = max(округл(база × множитель) + слагаемое + (вес_г / 100) × на_100г, min_итог). Деление
              («/2») — множитель 0.5. Значения в гм (1 гс = 10 гм, 1 гз = 100 гм).
            </div>
          </v-expansion-panel-text>
        </v-expansion-panel>

        <v-expansion-panel>
          <v-expansion-panel-title>Эффекты</v-expansion-panel-title>
          <v-expansion-panel-text>
            <div class="text-body-2 text-medium-emphasis mb-2">
              Текстовые описания и структурные операции (вес, прочность, блок, защита…). Метка «Оружие»/«Щит»/«Доспех»
              ограничивает, к какому блоку спека применяются ops; пустая и «Общее» — ко всему предмету.
            </div>
            <div v-for="(effect, index) in draft.effects" :key="index" class="effect-block mb-3">
              <div class="d-flex gap-2 align-center mb-1">
                <v-text-field
                  :model-value="effect.label ?? ''"
                  @update:model-value="(v: string) => updateEffectLabel(index, v)"
                  label="Метка"
                  density="compact"
                  hide-details
                  style="flex: 0 0 160px"
                />
                <v-text-field
                  :model-value="effect.text"
                  @update:model-value="(v: string) => updateEffectText(index, v)"
                  label="Эффект"
                  density="compact"
                  hide-details
                  style="flex: 1 1 auto"
                />
                <v-btn icon size="small" color="error" variant="text" @click="removeEffect(index)">
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </div>
              <ItemModifierOpsEditor
                :model-value="effect.ops ?? []"
                @update:model-value="(ops: ItemModifierOp[]) => updateEffectOps(index, ops)"
              />
            </div>
            <v-btn variant="text" color="primary" size="small" @click="addEffect">
              <v-icon start>mdi-plus</v-icon>
              Добавить эффект
            </v-btn>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </template>
  </RuleEditorBase>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
