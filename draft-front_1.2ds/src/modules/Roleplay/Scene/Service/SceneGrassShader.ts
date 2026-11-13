import { Shader, UniformGroup, compileHighShaderGlProgram, localUniformBitGl, roundPixelsBitGl } from 'pixi.js';
import type { Texture } from 'pixi.js';
import type { SceneGrassTrail } from '@/modules/Roleplay/Scene/Dto/SceneGrassTrail';
import { SCENE_GRASS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_GRASS';

/** Готовый шейдер ковра и запись следа движения. */
export interface SceneGrassShaderHandle {
  shader: Shader;
  apply(trail: SceneGrassTrail): void;
}

/**
 * Бит ковра травы.
 *
 * Тинт и прозрачность приходят вершинным атрибутом, точка основания — след
 * движения: вершина наклоняется по ходу актора, основание пучка остаётся на
 * месте. Атлас загружен с премультиплицированной альфой, поэтому выход тоже
 * премультиплицирован.
 */
const grassBit = (spots: number) => ({
  name: 'grass-carpet-bit',
  vertex: {
    header: `
      in vec4 aTint;
      in vec2 aOrigin;

      out vec4 vTint;
      out vec2 vGrassUv;

      uniform vec4 uTrail[${spots}];
      uniform float uTrailCount;
      uniform float uTrailSpan;
      uniform float uTrailWidth;
      uniform float uTrailBend;
      uniform float uTrailPress;
    `,
    main: `
      vTint = aTint;
      vGrassUv = uv;

      // Наклон растёт к верхушке квадратично: основание и середина пучка
      // почти стоят, поэтому пучок гнётся, а не уезжает целиком.
      float bend = clamp(length(position - aOrigin) / uTrailSpan, 0.0, 1.0);
      float lean = bend * bend;

      // Точки следа перекрываются, но их влияние не складывается: несколько
      // соседних точек дают один общий поток, иначе верхушка уезжает в
      // несколько раз дальше своего роста и трава «едет».
      vec2 flowSum = vec2(0.0);
      for (int i = 0; i < ${spots}; i++) {
        if (float(i) < uTrailCount) {
          vec4 point = uTrail[i];
          vec2 flow = point.zw;
          float strength = length(flow);
          if (strength > 0.001) {
            float distance = length(aOrigin - point.xy);
            float influence = 1.0 - smoothstep(uTrailWidth * 0.35, uTrailWidth, distance);
            if (influence > 0.0) {
              flowSum += (flow / strength) * (strength * influence);
            }
          }
        }
      }

      float push = length(flowSum);
      if (push > 0.001) {
        vec2 direction = flowSum / push;
        float force = min(push, 1.0) * lean * uTrailSpan;
        position += direction * (force * uTrailBend);
        position.y += force * uTrailPress;
      }
    `,
  },
  fragment: {
    header: `
      precision highp float;

      in vec4 vTint;
      in vec2 vGrassUv;

      uniform sampler2D uAtlas;
    `,
    main: `
      vec4 texel = texture2D(uAtlas, vGrassUv);
      float alpha = texel.a * vTint.a;
      if (alpha < 0.004) discard;

      // Текстура уже премультиплицирована: цвет домножается на тинт и альфу,
      // но не на альфу текстуры второй раз.
      outColor = vec4(texel.rgb * vTint.rgb * vTint.a, alpha);
    `,
  },
});

/** Шейдер ковра травы: атлас пучков, тинт, основание и след движения. */
export class SceneGrassShader {
  private readonly program = compileHighShaderGlProgram({
    name: 'grass-carpet',
    bits: [localUniformBitGl, roundPixelsBitGl, grassBit(SCENE_GRASS.trailSpots)],
  });

  /** Шейдер меша ковра и запись следа в его юниформы. */
  create(atlas: Texture): SceneGrassShaderHandle {
    const uniforms = new UniformGroup({
      uTrail: {
        value: new Float32Array(SCENE_GRASS.trailSpots * 4),
        type: 'vec4<f32>',
        size: SCENE_GRASS.trailSpots,
      },
      uTrailCount: { value: 0, type: 'f32' },
      uTrailSpan: { value: 1, type: 'f32' },
      uTrailWidth: { value: 1, type: 'f32' },
      uTrailBend: { value: SCENE_GRASS.trailBend, type: 'f32' },
      uTrailPress: { value: SCENE_GRASS.trailPress, type: 'f32' },
    });
    const shader = new Shader({
      glProgram: this.program,
      resources: {
        uAtlas: atlas.source,
        grassUniforms: uniforms,
      },
    });

    return {
      shader,
      apply: (trail: SceneGrassTrail): void => {
        uniforms.uniforms.uTrailCount = trail.count;
        uniforms.uniforms.uTrailSpan = trail.span;
        uniforms.uniforms.uTrailWidth = trail.width;
        uniforms.uniforms.uTrailBend = SCENE_GRASS.trailBend;
        uniforms.uniforms.uTrailPress = SCENE_GRASS.trailPress;
        uniforms.uniforms.uTrail.set(trail.spots);
      },
    };
  }
}
