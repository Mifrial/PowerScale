import { computed, onMounted, ref, type ComputedRef, type Ref } from 'vue';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { characterSheetValidationIssues } from '@/modules/Roleplay/Character/Utils/characterSheetValidation';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';

/**
 * Черновик и быстрое сохранение с карточки персонажа: экип (и дальше другие правки)
 * идут в тот же `character:${id}`, что standalone-редактор.
 */
export function useCharacterCardDraft(
  detail: ComputedRef<CharacterDetail | null>,
  rules: Ref<Rule[]>,
  canEdit: ComputedRef<boolean>,
  signal: ComputedRef<AbortSignal>,
) {
  const draftStore = useCharacterDraftStore();
  const characterStore = useCharacterStore();
  const keywordStore = useKeywordStore();
  const mechanics = ref<Mechanic[]>([]);
  const saving = ref(false);
  const saveError = ref<string | null>(null);

  const draftKey = computed(() => {
    const id = detail.value?.character.id;
    if (id == null) return null;

    return `character:${id}`;
  });

  const draft = computed(() => draftStore.draftOf(draftKey.value));

  const config = computed(() => {
    if (draft.value) return draft.value.config;
    const version = detail.value?.version;
    if (!version) return { osTotal: null, orTotal: null, moneyBudget: null };

    return {
      osTotal: version.budgets?.osTotal ?? null,
      orTotal: version.points.orTotal ?? null,
      moneyBudget: version.budgets?.moneyBudget ?? null,
    };
  });

  const build = computed(() => {
    const current = detail.value;
    if (draft.value) return draft.value.build;
    if (!current || rules.value.length === 0) return null;

    return characterBuildService.fromVersion(current.version, current.character.spaceId, rules.value);
  });

  const model = computed(() => {
    if (!build.value || rules.value.length === 0) return null;

    return characterEditorService.build(build.value, rules.value, config.value, keywordStore.keywords, mechanics.value);
  });

  const displayVersion = computed<CharacterVersion | null>(() => {
    const current = detail.value;
    if (!current) return null;
    if (!draft.value?.dirty || !build.value || rules.value.length === 0) return current.version;

    return characterEditorService.toVersion(
      build.value,
      rules.value,
      config.value,
      keywordStore.keywords,
      mechanics.value,
    );
  });

  const validationIssues = computed(() => characterSheetValidationIssues(draft.value?.build, model.value, true));

  function ensureDraft(): void {
    if (!canEdit.value) return;
    const current = detail.value;
    const key = draftKey.value;
    if (!current || key === null || draftStore.draftOf(key)) return;
    const version = current.version;
    const fromRules = rules.value;
    const nextBuild = characterBuildService.fromVersion(version, current.character.spaceId, fromRules);
    const baseline = {
      inventory: version.inventory.map((item) => ({ ...item })),
      money: version.money,
    };
    draftStore.initDraft(
      key,
      nextBuild,
      {
        osTotal: version.budgets?.osTotal ?? null,
        orTotal: version.points.orTotal ?? null,
        moneyBudget: version.budgets?.moneyBudget ?? null,
      },
      baseline,
    );
  }

  async function save(): Promise<void> {
    const current = detail.value;
    const key = draftKey.value;
    const entry = draftStore.draftOf(key);
    if (!current || key === null || !entry || !model.value || saving.value) return;
    saveError.value = null;
    const issues = characterSheetValidationIssues(entry.build, model.value, true);
    if (issues.length > 0) {
      saveError.value = `Нельзя сохранить: ${issues.join('; ')}`;

      return;
    }
    saving.value = true;
    try {
      const version = characterEditorService.toVersion(
        entry.build,
        rules.value,
        entry.config,
        keywordStore.keywords,
        mechanics.value,
      );
      const updated = await getCharacterApi().updateCharacter(
        current.character.id,
        { version, status: 'ready' },
        signal.value,
      );
      characterStore.currentCharacter = updated;
      draftStore.discard(key);
    } catch (e) {
      saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить персонажа';
    } finally {
      saving.value = false;
    }
  }

  onMounted(() => {
    if (keywordStore.keywords.length === 0) void keywordStore.fetchTags();
    void getRuleApi()
      .getMechanics(signal.value)
      .then((list) => {
        mechanics.value = list;
      })
      .catch(() => {
        mechanics.value = [];
      });
  });

  return {
    draftKey,
    draft,
    build,
    model,
    displayVersion,
    validationIssues,
    saving,
    saveError,
    keywords: computed(() => keywordStore.keywords),
    ensureDraft,
    save,
  };
}
