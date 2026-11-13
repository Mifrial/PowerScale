import { Shader, UniformGroup, compileHighShaderGlProgram, localUniformBitGl, roundPixelsBitGl } from 'pixi.js';

/**
 * Бит шейдера каустики.
 *
 * Узор рисуется во фрагменте по координатам русла: сетка ячеек Вороного с
 * доменным искажением. Текстуры и тайла нет, поэтому узор не повторяется.
 * В вершинной части координата русла передаётся отдельным highp-вэриингом:
 * `vUV` у Pixi mediump, а хешам нужна точность на десятках ячеек.
 */
const CAUSTICS_BIT = {
  name: 'water-caustics-bit',
  vertex: {
    header: `
      out highp vec2 vCausticUv;
    `,
    main: `
      vCausticUv = uv;
    `,
  },
  fragment: {
    header: `
      precision highp float;

      in highp vec2 vCausticUv;

      uniform float uTime;
      uniform float uSeed;
      uniform float uCellScale;
      uniform vec2 uFlow;
      uniform float uLine;

      const vec3 CAUSTICS_COLOR = vec3(0.737, 0.886, 0.941);

      float hash21(vec2 point) {
        point = fract(point * vec2(123.34, 456.21));
        point += dot(point, point + 45.32);
        return fract(point.x * point.y);
      }

      vec2 hash22(vec2 point) {
        float value = hash21(point);
        return vec2(fract(value * 123.34), fract(value * 456.21));
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

      float fbm(vec2 point) {
        float value = 0.0;
        float amplitude = 0.5;
        for (int octave = 0; octave < 3; octave++) {
          value += amplitude * valueNoise(point);
          point = point * 2.03 + 17.1;
          amplitude *= 0.5;
        }
        return value;
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
    `,
    main: `
      vec2 point = vCausticUv * uCellScale + uFlow * uTime;

      // Искажение однооктавное: у fbm высокие октавы рвут сетку в клочья.
      // Медленно дышит, поэтому узор не просто едет, а переливается.
      float breath = uTime * 0.25;
      vec2 warp = vec2(
        valueNoise(point * 2.0 + uSeed + breath * 0.15),
        valueNoise(point * 2.0 + uSeed + vec2(13.7, 7.1) - breath * 0.12)
      ) * 2.0 - 1.0;
      // Крупное искажение растягивает одни ячейки и сжимает другие: сетка
      // перестаёт быть ровной решёткой.
      vec2 spread = vec2(
        valueNoise(point * 0.45 + uSeed * 1.7 + vec2(5.3, 2.9)),
        valueNoise(point * 0.45 + uSeed * 1.7 + vec2(21.4, 11.2))
      ) * 2.0 - 1.0;

      vec2 cell = voronoi(point + warp * 0.5 + spread * 0.7);
      float edge = cell.x;

      // Линия: яркое ядро и мягкое свечение вокруг. Ширина uLine задана в
      // долях ячейки; в отдалении она растёт, а яркость падает — средняя
      // освещённость остаётся прежней, как при мипмапах у текстуры.
      float profile = 1.0 - clamp(edge / uLine, 0.0, 1.0);
      float core = pow(profile, 3.0);
      float halo = pow(profile, 1.6) * 0.8;
      float fade = min(1.0, 0.24 / uLine);
      float gain = 0.55 + 0.45 * cell.y;

      // Пятна гасят сетку участками: сплошной ковёр ячеек читался бы решёткой.
      float mask = clamp((fbm(point * 0.45 + uSeed * 3.1) - 0.2) / 0.5, 0.0, 1.0);
      float value = clamp(core + halo, 0.0, 1.0) * fade * gain * (0.65 + 0.35 * mask);

      outColor = vec4(CAUSTICS_COLOR * value, value);
    `,
  },
};

/**
 * Процедурная каустика для воды.
 *
 * Один GL-программ на все слои, свои униформы — у каждого слоя. Цвет уже
 * премножен на альфу узора: у Pixi смешивание считает альфу премноженной.
 */
export class SceneWaterCausticsShader {
  private readonly program = compileHighShaderGlProgram({
    name: 'water-caustics',
    bits: [localUniformBitGl, roundPixelsBitGl, CAUSTICS_BIT],
  });

  /** Слой каустики: шейдер и обновление времени; геометрию и альфу задаёт вызывающий. */
  create(options: {
    /** Сколько ячеек узора приходится на единицу UV. */
    cellsPerUv: number;
    /** Снос узора в ячейках в секунду по осям русла. */
    flow: [number, number];
    /** Толщина линии в долях ячейки. */
    line: number;
    /** Сдвиг узора: разводит слои и водоёмы между собой. */
    seed: number;
  }): { shader: Shader; update(seconds: number): void } {
    const uniforms = new UniformGroup({
      uTime: { value: 0, type: 'f32' },
      uSeed: { value: options.seed, type: 'f32' },
      uCellScale: { value: options.cellsPerUv, type: 'f32' },
      uFlow: { value: new Float32Array(options.flow), type: 'vec2<f32>' },
      uLine: { value: options.line, type: 'f32' },
    });
    const shader = new Shader({
      glProgram: this.program,
      resources: { causticsUniforms: uniforms },
    });

    return {
      shader,
      update: (seconds: number): void => {
        uniforms.uniforms.uTime = seconds;
      },
    };
  }
}
