# Материалы террейна

Текстуры взяты из библиотек [Poly Haven](https://polyhaven.com/textures) и
[ambientCG](https://ambientcg.com) и лицензированы как CC0 (public domain):
можно использовать, изменять и распространять без указания авторства.

| Файл          | Материал            | Источник                                                             |
| ------------- | ------------------- | -------------------------------------------------------------------- |
| `grass.jpg`   | трава               | [Grass004](https://ambientcg.com/view?id=Grass004)                   |
| `sand.jpg`    | песок               | [dirt_floor](https://polyhaven.com/a/dirt_floor)                     |
| `dirt.jpg`    | грунт               | [dirt](https://polyhaven.com/a/dirt)                                 |
| `stone.jpg`   | камень, щебень      | [river_small_rocks](https://polyhaven.com/a/river_small_rocks)       |
| `mud.jpg`     | грязь               | [brown_mud_02](https://polyhaven.com/a/brown_mud_02)                 |
| `bed.jpg`     | дно водоёма, галька | [ganges_river_pebbles](https://polyhaven.com/a/ganges_river_pebbles) |
| `wetsand.jpg` | мокрый берег        | [damp_beach_sand](https://polyhaven.com/a/damp_beach_sand)           |

Все файлы приведены к 512×512 и бесшовны по краям. В коде они получают
`addressMode: repeat` и мипмапы, поэтому не мерцают при отдалении карты.
`grass.jpg` дополнительно обесцвечен на 10 % и чуть осветлён, чтобы луг не
уходил в кислотно-жёлтый.

`water_surface.jpg`, `water_ripples.png`, `water_caustics.png`,
`water_glints.png`, `water_foam.png`, `water_macro.png` и `macro.png` —
собственные процедурные текстуры проекта (CC0): мягкая основа поверхности,
рябь, каустика для дна, блики, кружево пены, крупный тон воды и крупная
вариация, сбивающая сетку повторов тайла.

В реке каустика считается шейдером (`SceneWaterCausticsShader`) и
`water_caustics.png` там не используется: процедурный узор не повторяется на
длинной реке. Текстура остаётся у замкнутой воды — на луже мелкий узор читается
как лёд, и вихрь его прячет.

`water_foam.png` рассчитана на полосу у уреза: бесшовна по горизонтали, альфа
сосредоточена вдоль середины, края рваные, вдоль длины есть пропуски. В сцене
тайл растягивается вдоль берега, поэтому рисунок не повторяется на каждом
метре.

`water_macro.png` — почти белая карта тона: в сцене ложится множителем
(`multiply`) с периодом 44 ипари и чуть приглушает светлые участки. Она не
рисует волны, а сбивает сетку повторов на широкой воде.
