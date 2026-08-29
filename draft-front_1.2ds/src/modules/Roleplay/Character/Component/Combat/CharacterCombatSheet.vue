<script setup lang="ts">
import AbilityTab from '@/modules/Roleplay/Character/Component/Detail/AbilityTab.vue';
import InventoryTab from '@/modules/Roleplay/Character/Component/Editor/InventoryTab.vue';
import AttackTile from '@/modules/Roleplay/Character/Component/Detail/Attacks/AttackTile.vue';
import DefenseValue from '@/modules/Roleplay/Character/Component/Detail/Defense/DefenseValue.vue';
import ArmorTile from '@/modules/Roleplay/Character/Component/Detail/Defense/ArmorTile.vue';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';
import RuleSlider from '@/modules/Roleplay/Rule/Component/RuleSlider.vue';
import { useRuleDetailSlider } from '@/modules/Roleplay/Character/Composables/useRuleDetailSlider';
import type { CharacterCombatSheetPane } from '@/modules/Roleplay/Character/Enum/CharacterCombatSheetPane';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

withDefaults(
  defineProps<{
    pane: CharacterCombatSheetPane;
    overview: CharacterOverview | null;
    version: CharacterVersion | null;
    build: CharacterBuild | null;
    model: CharacterEditorModel | null;
    rules: Rule[];
    keywords: Keyword[];
    canEdit: boolean;
    characterId: number;
    showFavorites: boolean;
    spaceId: number;
    rulesRevision: number | null;
    defenseOpen?: boolean;
    attacksOpen?: boolean;
    showSlider?: boolean;
  }>(),
  { defenseOpen: true, attacksOpen: true, showSlider: false },
);

const emit = defineEmits<{
  'toggle-defense': [];
  'toggle-attacks': [];
  launch: [attack: AttackOverview];
  'toggle-equipped': [itemId: number];
}>();

const ruleSlider = useRuleDetailSlider();

function onToggleEquipped(itemId: number): void {
  emit('toggle-equipped', itemId);
}
</script>

<template>
  <section v-if="pane === 'overview' && overview?.defense" class="combat-sheet-section">
    <div class="combat-sheet-section__heading">
      <button type="button" class="combat-sheet-section__title" @click="emit('toggle-defense')">
        <v-icon size="18">{{ defenseOpen ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
        Защита
      </button>
      <span class="flex-shrink-0" @click.stop>
        <DefenseValue v-if="overview.defense.constantDefense > 0" :tiers="overview.defense.tiers" />
      </span>
    </div>
    <div v-show="defenseOpen" class="combat-sheet-pair">
      <ArmorTile v-for="armor in overview.defense.armor" :key="armor.itemRuleId" :item="armor" />
      <v-sheet v-if="overview.defense.shield" class="pa-2 rounded border">
        <div class="d-flex align-center ga-2">
          <v-icon icon="mdi-shield-outline" color="primary" />
          <RuleLink :rule-id="overview.defense.shield.itemRuleId" class="text-body-2 font-weight-medium">
            {{ overview.defense.shield.itemName }} — блокирование
          </RuleLink>
        </div>
        <div class="text-body-2 text-medium-emphasis">
          Защита {{ overview.defense.shield.defense }} · эффективность
          {{ overview.defense.shield.efficiency }}
        </div>
      </v-sheet>
      <div
        v-if="overview.defense.armor.length === 0 && !overview.defense.shield"
        class="text-medium-emphasis text-body-2"
      >
        Экипированной защиты нет
      </div>
    </div>
  </section>

  <section v-if="pane === 'overview' && (overview?.attacks ?? []).length > 0" class="combat-sheet-section">
    <button type="button" class="combat-sheet-section__title" @click="emit('toggle-attacks')">
      <v-icon size="18">{{ attacksOpen ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
      Атаки
    </button>
    <div v-show="attacksOpen" class="combat-sheet-pair">
      <AttackTile
        v-for="attack in overview?.attacks ?? []"
        :key="`${attack.itemRuleId}_${attack.profileType}_${attack.profileIndex ?? 0}`"
        variant="combat"
        :attack="attack"
        @launch="emit('launch', $event)"
      />
    </div>
  </section>

  <AbilityTab
    v-if="pane === 'abilities' && version"
    :version="version"
    :rules="rules"
    :rules-loading="false"
    :character-id="characterId"
    :show-favorites="showFavorites"
  />

  <InventoryTab
    v-if="pane === 'inventory' && build && model"
    variant="sheet"
    :build="build"
    :model="model"
    :draft-key="null"
    :rules="rules"
    :keywords="keywords"
    :can-edit="canEdit"
    :on-toggle-equipped="onToggleEquipped"
    list-height="calc(100vh - 160px)"
  />
  <div v-else-if="pane === 'inventory'" class="text-medium-emphasis text-body-2 pa-4">Инвентарь недоступен</div>

  <RuleSlider
    v-if="showSlider"
    v-model:open="ruleSlider.state.open"
    :rule-id="ruleSlider.state.ruleId"
    :space-id="spaceId"
    :rules-revision="rulesRevision"
    :rules="rules"
    :keywords="keywords"
  />
</template>

<style scoped>
.combat-sheet-section {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.combat-sheet-section__heading {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}
.combat-sheet-section__title {
  display: flex;
  align-items: center;
  gap: 4px;
  min-width: 0;
  padding: 0;
  border: 0;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  color: rgb(var(--v-theme-primary));
  text-transform: uppercase;
  letter-spacing: 0.02em;
  text-align: left;
}
.combat-sheet-pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
</style>
