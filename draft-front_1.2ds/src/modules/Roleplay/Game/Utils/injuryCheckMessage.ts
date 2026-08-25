import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type {
  InjuryDifficultyBreakdown,
  InjuryHealRoll,
  InjuryHealUnit,
  InjuryOutcome,
} from '@/modules/Roleplay/Game/Dto/InjuryOutcome';

function triggerLabel(source: InjuryDifficultyBreakdown['source']): string {
  if (source === 'manual') return 'заданная сложность';
  if (source === 'leftover') return 'повреждения удара';
  if (source === 'wound') return 'рана';

  return 'истощение';
}

function markChosen(
  breakdown: InjuryDifficultyBreakdown,
  source: InjuryDifficultyBreakdown['source'],
  text: string,
): string {
  if (breakdown.source !== source || breakdown.total <= 0) return text;

  return `${text} ←`;
}

/** Строки для [i] проверки: что дало сложность. */
export function injuryDifficultyDetailRows(breakdown: InjuryDifficultyBreakdown): { label: string; value: string }[] {
  const rows: { label: string; value: string }[] = [{ label: 'Триггер', value: triggerLabel(breakdown.source) }];
  if (breakdown.source === 'manual') {
    rows.push({
      label: 'Заданная сложность',
      value: String(Math.max(0, breakdown.total - breakdown.extraDifficulty)),
    });
  } else {
    rows.push({
      label: 'Повреждения',
      value: markChosen(
        breakdown,
        'leftover',
        `⌊${breakdown.leftoverDamage} / ${breakdown.endurance}⌋ = ${breakdown.fromDamage}`,
      ),
    });
    rows.push({
      label: 'Рана',
      value: markChosen(
        breakdown,
        'wound',
        `⌊${breakdown.woundStrength} / ${breakdown.woundDivisor}⌋ = ${breakdown.fromWound}`,
      ),
    });
    const exhaustionText =
      breakdown.fromExhaustion > 0
        ? `${breakdown.exhaustion} − ${breakdown.exhaustionOffset} = ${breakdown.fromExhaustion}`
        : `${breakdown.exhaustion} (< 7)`;
    rows.push({
      label: 'Истощение',
      value: markChosen(breakdown, 'exhaustion', exhaustionText),
    });
  }
  if (breakdown.extraDifficulty > 0) {
    rows.push({ label: 'Колющий', value: `+⌊РУ / 2⌋ = +${breakdown.extraDifficulty}` });
  }
  rows.push({ label: 'Итого', value: String(breakdown.total) });

  return rows;
}

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function healUnitLabel(unit: InjuryHealUnit): string {
  if (unit === 'days') return 'дн.';
  if (unit === 'decades') return 'дек.';
  if (unit === 'months') return 'мес.';

  return 'лет';
}

export function formatInjuryOutcome(injury: InjuryOutcome): string {
  if (injury.strength <= 0) return 'увечья нет';
  const duration = injury.permanent ? 'Постоянное' : 'Временное';
  const heal = injury.heal
    ? ` (${injury.heal.diceCount}д${injury.heal.dieFaces} ${healUnitLabel(injury.heal.unit)} → ${injury.heal.total})`
    : '';
  const lethal = injury.lethal ? 'да' : 'нет';
  const disfiguring = injury.disfiguring ? 'да' : 'нет';

  return `Увечье: ${injury.strength}, ${duration}${heal}, Смертельное: ${lethal}, Обезображивающее: ${disfiguring}`;
}

function pluralRu(n: number, one: string, few: string, many: string): string {
  const n10 = n % 10;
  const n100 = n % 100;
  if (n10 === 1 && n100 !== 11) return one;
  if (n10 >= 2 && n10 <= 4 && (n100 < 10 || n100 >= 20)) return few;

  return many;
}

function formatHealDuration(heal: InjuryHealRoll): string {
  const n = heal.total;
  if (heal.unit === 'days') return `${n} ${pluralRu(n, 'день', 'дня', 'дней')}`;
  if (heal.unit === 'months') return `${n} ${pluralRu(n, 'месяц', 'месяца', 'месяцев')}`;
  if (heal.unit === 'years') return `${n} ${pluralRu(n, 'год', 'года', 'лет')}`;

  return `${n} ${pluralRu(n, 'декаду', 'декады', 'декад')}`;
}

/** Бросок срока — интервал −1 к силе, не полное время лечения. */
function formatTickPhrase(heal: InjuryHealRoll): string {
  if (heal.total === 1) {
    if (heal.unit === 'days') return 'каждый день';
    if (heal.unit === 'months') return 'каждый месяц';
    if (heal.unit === 'years') return 'каждый год';

    return 'каждую декаду';
  }

  return `каждые ${formatHealDuration(heal)}`;
}

function formatFullHealDuration(heal: InjuryHealRoll, strength: number): string {
  return formatHealDuration({ ...heal, total: heal.total * Math.max(1, strength) });
}

/** Сообщение после броска: сила, интервал −1 (= бросок срока), полное время = интервал × сила. */
export function formatInjuryReceivedMessage(
  targetName: string,
  injury: InjuryOutcome,
  targetKey?: CombatEntityKey,
): string {
  const target = targetKey ? entityToken(targetKey, targetName) : targetName;
  if (injury.strength <= 0) return `${target} не получает увечье.`;
  const lines: string[] = [];
  if (injury.permanent) {
    lines.push(`${target} получает постоянное увечье с силой ${injury.strength}.`);
  } else if (injury.heal) {
    const tick = formatTickPhrase(injury.heal);
    const full = formatFullHealDuration(injury.heal, injury.strength);
    lines.push(
      `${target} получает увечье с силой ${injury.strength}. Оно будет уменьшаться на 1 ${tick}, пока полностью не пройдёт за ${full}.`,
    );
  } else {
    lines.push(`${target} получает временное увечье с силой ${injury.strength}.`);
  }
  if (injury.disfiguring) lines.push('Увечье обезображивает.');
  if (injury.lethal) lines.push('Увечье смертельно.');

  return lines.join('\n');
}
