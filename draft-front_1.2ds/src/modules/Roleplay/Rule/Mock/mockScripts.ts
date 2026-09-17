import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ScriptKind } from '@/modules/Roleplay/Rule/Enum/ScriptKind';

function scriptRule(id: number, code: string, name: string, description: string, kind: ScriptKind): Rule {
  return {
    id,
    code,
    type: 'script',
    name,
    description,
    spaceId: 1,
    spec: { type: 'script', kind },
    keywordIds: [],
    mechanicId: null,
    mechanicPayload: null,
    createdAt: 1786356000,
  };
}

/** Письменности сеттинга: алфавит 1 ОР, иероглифы 1/1/1. */
export const mockScripts: Rule[] = [
  scriptRule(
    2414,
    'raden-alphabet',
    'Раденский алфавит',
    '<p>Фонетическая письменность раденской группы. Один навык покрывает и Пра-Радос, и современный Радос.</p><p>Иероглифы той же группы — отдельный экземпляр письменности.</p>',
    'alphabetic',
  ),
  scriptRule(
    2415,
    'raden-glyphs',
    'Раденские иероглифы',
    '<p>Логографическая письменность раденской группы: храм, печать, старые трактаты. Три уровня, каждый по 1 ОР.</p><p>Не заменяет раденский алфавит и не пишет языки долины.</p>',
    'logographic',
  ),
  scriptRule(
    2416,
    'ishkhet-alphabet',
    'Ишхетский алфавит',
    "<p>Общий алфавит Ишхета и живых «тиль»: улай, шыим, чур, ар'тиль. Один экземпляр — все четыре языка, если есть речь.</p>",
    'alphabetic',
  ),
  scriptRule(
    2417,
    'gargat-alphabet',
    'Гаргатский алфавит',
    '<p>Алфавит клана Грогхар. Пишет гаргат, не раденские и не казарские знаки.</p>',
    'alphabetic',
  ),
  scriptRule(
    2418,
    'kalami-alphabet',
    'Каламийский алфавит',
    '<p>Алфавит королевства Оула. Только калами.</p>',
    'alphabetic',
  ),
  scriptRule(
    2419,
    'somnaril-alphabet',
    'Сомнарилский алфавит',
    '<p>Общий алфавит Сомнарила и Сомнорила. Один экземпляр покрывает праязык и официальную речь содружества.</p>',
    'alphabetic',
  ),
  scriptRule(
    2420,
    'kazar-glyphs',
    'Казарские иероглифы',
    '<p>Общие иероглифы Казара: ярын, хас, айныр. Три уровня. Не алфавит и не раденские знаки.</p>',
    'logographic',
  ),
];
