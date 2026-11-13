/**
 * Общий GLSL воды: шум, нормали и каустика.
 *
 * Тело и поверхность считают нормали одним и тем же кодом, иначе преломление
 * дна разъехалось бы с бликами. Всё процедурное: текстуры и тайла нет, поэтому
 * узор не повторяется на длинной реке.
 *
 * Строки подставляются в биты шейдеров, поэтому это не отдельный шейдер, а
 * библиотека функций.
 */

/** Хеши, value-noise и наклон поверхности по координатам русла. */
export const WATER_NOISE_GLSL = `
  float hash21(vec2 point) {
    point = fract(point * vec2(123.34, 456.21));
    point += dot(point, point + 45.32);
    return fract(point.x * point.y);
  }

  float valueNoise(vec2 point) {
    vec2 cell = floor(point);
    vec2 local = fract(point);
    vec2 weight = local * local * (3.0 - 2.0 * local);
    float a = hash21(cell);
    float b = hash21(cell + vec2(1.0, 0.0));
    float c = hash21(cell + vec2(0.0, 1.0));
    float d = hash21(cell + vec2(1.0, 1.0));
    return mix(mix(a, b, weight.x), mix(c, d, weight.x), weight.y);
  }

  /** Две октавы: для нормалей хватает, третья только жжёт пиксели. */
  float waterFbm(vec2 point) {
    float value = 0.5 * valueNoise(point);
    value += 0.25 * valueNoise(point * 2.03 + 17.1);
    return value;
  }

  /**
   * Наклон поверхности: производная шума по координатам русла.
   *
   * Волны идут вниз по течению, поэтому гребни стоят поперёк русла: поперёк
   * координата растянута, и шум меняется вдоль русла быстрее, чем поперёк.
   * Снос идёт по осевой: река не крутит узор на месте.
   */
  vec2 waterSlope(vec2 channel, vec2 seed, float time, float scale, float stretch, float speed) {
    vec2 point = vec2(channel.x * scale, channel.y * scale / stretch) - vec2(time * speed, 0.0) + seed;
    float delta = 0.35;
    float base = waterFbm(point);
    float dx = waterFbm(point + vec2(delta, 0.0));
    float dy = waterFbm(point + vec2(0.0, delta));
    return vec2(dx - base, dy - base) / delta;
  }

  /**
   * Смещение уреза в единицах сцены.
   *
   * Берег не ровный: крупный шум даёт изгибы, мелкий — рваную кромку. Тот же
   * сдвиг использует выплеск и пена, поэтому вода уходит за геометрию, а не
   * режется ровной линией силуэта.
   */
  float waterEdgeShift(vec2 channel, vec2 seed, float time, float scale, float speed, float amp, float fineAmp) {
    vec2 point = channel * scale - vec2(time * speed, 0.0);
    float big = (waterFbm(point + seed) - 0.45) * 2.0;
    float fine = (waterFbm(point * 2.2 + seed * 1.7 + vec2(5.1, 2.3)) - 0.45) * 2.0;
    return big * amp + fine * fineAmp;
  }

  /**
   * Фаза наката, общая для тела и поверхности.
   *
   * Тело и плёнка должны выдвигаться на берег одним и тем же сдвигом, иначе
   * вода и прозрачная поверхность расходятся на кромке.
   */
  float waterWashPhase(vec2 channel, vec2 seed, float time, float rate, float freq, float scale) {
    return time * rate + channel.x * freq + waterFbm(channel * scale * 1.3 + seed) * 6.2831853;
  }

  float waterWashValue(float phase) {
    return 0.5 + 0.5 * sin(phase);
  }

  /**
   * Импульс наката: 0 в покое, 1 на гребне, 0 на откате.
   *
   * По нему рождается пена: каждый накат даёт свой всплеск и сам его гасит.
   */
  float waterSurge(float phase) {
    float cycle = fract(phase / 6.2831853);
    return smoothstep(0.02, 0.18, cycle) * (1.0 - smoothstep(0.32, 0.88, cycle));
  }

  /**
   * Сушка берега: 0 сразу после гребня, 1 к следующему накату.
   *
   * Мокрота после отката должна остаться и плавно высохнуть, а не исчезнуть
   * вместе с водой.
   */
  float waterDryness(float phase) {
    float sincePeak = fract((phase - 1.5707963) / 6.2831853);
    return smoothstep(0.05, 0.7, sincePeak);
  }

  /**
   * Одна направленная волна реки.
   *
   * wave — (масштаб на единицу сцены, скорость фазы, угол, амплитуда).
   * Фаза искажается шумом: ровные синусоиды читаются графикой, а сдвинутые
   * гребни дают живую рябь по всей ширине русла.
   */
  float riverWave(vec2 point, float time, vec4 wave, vec2 seed) {
    vec2 direction = vec2(cos(wave.z), sin(wave.z));
    float phase = dot(point, direction) * wave.x - time * wave.y;
    float warp = waterFbm(point * (wave.x * 0.35) + seed - vec2(time * 0.03, 0.0)) - 0.5;
    return sin(phase + warp * 4.5) * wave.w;
  }

  /** Высота суммы трёх волн реки. */
  float riverHeight(vec2 point, float time, vec2 seed, float flow, vec4 a, vec4 b, vec4 c) {
    return riverWave(point, time * flow, a, seed)
      + riverWave(point, time * flow, b, seed + 13.7)
      + riverWave(point, time * flow, c, seed + 29.1);
  }

  /**
   * Градиент высоты волн реки.
   *
   * Конечные разности по высоте: производные синусов дали бы ровный наклон,
   * а нам нужен общий наклон всей суммы, включая шумовое искажение.
   */
  vec2 riverGradient(vec2 point, float time, vec2 seed, float flow, vec4 a, vec4 b, vec4 c, float eps) {
    float height = riverHeight(point, time, seed, flow, a, b, c);
    float heightX = riverHeight(point + vec2(eps, 0.0), time, seed, flow, a, b, c);
    float heightY = riverHeight(point + vec2(0.0, eps), time, seed, flow, a, b, c);
    return vec2(heightX - height, heightY - height) / eps;
  }

  /**
   * Градиент шумовой ряби: ломает ровные полосы направленных волн.
   *
   * Возвращает наклон в единицах сцены, как и riverGradient.
   */
  vec2 waterNoiseGradient(vec2 point, vec2 seed, float time, float flow, float scale, float stretch, float speed) {
    // Снос без профиля скорости: иначе центр и берега тянут рисунок
    // с разной скоростью и пятна вытягиваются в полосы.
    vec2 p = vec2(point.x * scale, point.y * scale / stretch) - vec2(time * speed, 0.0) + seed;
    float delta = 0.35;
    float base = waterFbm(p);
    float dx = waterFbm(p + vec2(delta, 0.0));
    float dy = waterFbm(p + vec2(0.0, delta));
    return vec2(dx - base, dy - base) / delta * vec2(scale, scale / stretch);
  }

  /**
   * Искры в ячейках: каждая вспыхивает и гаснет в своём ритме.
   *
   * Это не общий шум, а сетка мелких точек: у каждой ячейки своя фаза,
   * частота и положение. Поэтому искры не мигают синхронно и не плывут
   * одной светлой лентой.
   */
  float sparkleCells(vec2 point, float time, float pulseSpeed, float density) {
    vec2 cell = floor(point);
    vec2 local = fract(point);
    float h = hash21(cell);
    if (h < density) {
      return 0.0;
    }

    float phase = h * 6.2831853;
    float speed = pulseSpeed * (0.7 + 0.6 * fract(h * 13.7));
    // Плавная вспышка: яркость идёт по косинусу, без резкого старта.
    float pulse = 0.5 - 0.5 * cos(time * speed + phase);
    // Центр держим внутри ячейки, иначе точка обрезается её границей и
    // появляется ступенькой.
    vec2 center = vec2(fract(h * 37.1), fract(h * 91.7)) * 0.4 + 0.3;
    // Не круглая точка, а короткий штрих вдоль течения. Размер растёт и
    // убывает вместе с яркостью.
    vec2 delta = vec2((local.x - center.x) * 0.6, (local.y - center.y) * 3.0);
    float radius = 0.08 + 0.08 * pulse;
    float dot = 1.0 - smoothstep(0.0, radius, length(delta));
    return pulse * dot;
  }
`;

/** Каустика по дну: сетка Вороного с доменным искажением. */
export const WATER_CAUSTICS_GLSL = `
  vec2 hash22(vec2 point) {
    float value = hash21(point);
    return vec2(fract(value * 123.34), fract(value * 456.21));
  }

  // Расстояние до границы ячейки (F2 - F1) и яркость ближайшей ячейки.
  vec2 voronoi(vec2 point) {
    vec2 cell = floor(point);
    vec2 local = fract(point);
    float first = 8.0;
    float second = 8.0;
    float id = 0.0;
    for (int y = -1; y <= 1; y++) {
      for (int x = -1; x <= 1; x++) {
        vec2 offset = vec2(float(x), float(y));
        vec2 origin = hash22(cell + offset);
        vec2 delta = offset + origin - local;
        float distanceSquared = dot(delta, delta);
        if (distanceSquared < first) {
          second = first;
          first = distanceSquared;
          id = hash21(cell + offset);
        } else if (distanceSquared < second) {
          second = distanceSquared;
        }
      }
    }
    return vec2(sqrt(second) - sqrt(first), id);
  }

  /**
   * Яркость каустики в точке узора.
   *
   * detail подмешивает второй, более мелкий слой нитей: у одной решётки
   * Вороного середина ячейки пустая, и узор читается нарисованной сеткой.
   * У текстуры, которую заменяем, внутри ячеек ещё мелкая рябь — она и даёт
   * «фотографичность».
   *
   * Искажение однооктавное: у fbm высокие октавы рвут сетку в клочья. Крупное
   * искажение растягивает одни ячейки и сжимает другие, поэтому сетка не
   * читается ровной решёткой.
   */
  float waterCaustics(vec2 point, float time, float seed, float line, float detail) {
    float breath = time * 0.25;
    // Нити каустики в реке вытянуты по течению: поперёк узор сжат.
    vec2 shaped = vec2(point.x, point.y * 1.55);

    vec2 warp = vec2(
      valueNoise(shaped * 2.0 + seed + breath * 0.15),
      valueNoise(shaped * 2.0 + seed + vec2(13.7, 7.1) - breath * 0.12)
    ) * 2.0 - 1.0;
    vec2 spread = vec2(
      valueNoise(shaped * 0.45 + seed * 1.7 + vec2(5.3, 2.9) + breath * 0.05),
      valueNoise(shaped * 0.45 + seed * 1.7 + vec2(21.4, 11.2) - breath * 0.04)
    ) * 2.0 - 1.0;

    vec2 cell = voronoi(shaped + warp * 0.35 + spread * 0.45);
    float edge = cell.x;
    // Толщина своя у каждой ячейки: ровная решётка читается чертежом.
    float width = line * (0.75 + 0.5 * fract(cell.y * 13.7));

    // Линия мягкая, но с читаемым ядром: совсем размытая она превращается в
    // пятна и зерно, а тонкий шнур читается графикой. Нужна середина.
    float profile = 1.0 - clamp(edge / width, 0.0, 1.0);
    float soft = pow(profile, 2.2) * 0.6;
    float core = pow(profile, 6.0) * 0.7;
    float fade = min(1.0, 0.24 / width);
    // Контраст по ячейкам: одни светятся, другие почти гаснут.
    float gain = 0.35 + 0.65 * cell.y;

    // Мелкие нити внутри ячеек: тот же Вороной на 2.35× масштабе.
    vec2 detailCell = voronoi(shaped * 2.35 + vec2(9.1, 3.7) + warp * 0.5 + spread * 0.4);
    float detailProfile = 1.0 - clamp(detailCell.x / (width * 1.2), 0.0, 1.0);
    float detailLine = pow(detailProfile, 2.6) * (0.25 + 0.75 * detailCell.y);

    // Пятна гасят сетку участками: сплошной ковёр ячеек читался бы решёткой.
    // Диапазон широкий: должны быть и плотные места, и почти чистая вода.
    float mask = clamp((waterFbm(shaped * 0.45 + seed * 3.1) - 0.25) / 0.5, 0.0, 1.0);
    float lines = clamp(soft + core + detailLine * detail, 0.0, 1.0);
    return lines * fade * gain * (0.45 + 0.55 * mask);
  }
`;
