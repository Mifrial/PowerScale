# Материалы террейна

Текстуры взяты из библиотеки [Poly Haven](https://polyhaven.com/textures) и
лицензированы как CC0 (public domain): можно использовать, изменять и
распространять без указания авторства.

| Файл | Материал | Источник |
|---|---|---|
| `grass.jpg` | трава | [grass_ground](https://polyhaven.com/a/grass_ground) |
| `sand.jpg` | песок | [dirt_floor](https://polyhaven.com/a/dirt_floor) |
| `dirt.jpg` | грунт | [dirt](https://polyhaven.com/a/dirt) |
| `stone.jpg` | камень, щебень | [river_small_rocks](https://polyhaven.com/a/river_small_rocks) |
| `mud.jpg` | грязь | [brown_mud_02](https://polyhaven.com/a/brown_mud_02) |
| `bed.jpg` | дно водоёма, галька | [ganges_river_pebbles](https://polyhaven.com/a/ganges_river_pebbles) |
| `wetsand.jpg` | мокрый берег | [damp_beach_sand](https://polyhaven.com/a/damp_beach_sand) |

Все файлы приведены к 512×512 и бесшовны по краям. В коде они получают
`addressMode: repeat` и мипмапы, поэтому не мерцают при отдалении карты.

`water_surface.jpg`, `water_caustics.png` и `macro.png` — собственные
процедурные текстуры проекта (CC0): мягкая рябь поверхности, каустика для дна и
крупная вариация, сбивающая сетку повторов тайла.
