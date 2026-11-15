/**
 * Знаковая дистанция до границы маски.
 *
 * Общий примитив для воды и берега: шейдеру воды поле нужно для цвета и
 * пены, сетке поверхностей — для наклонного откоса у уреза.
 */

/**
 * Знаковая дистанция до границы маски в клетках: плюс внутри, минус снаружи.
 *
 * Урез проходит между клетками, а не по их центрам, поэтому расстояние
 * сдвинуто на полклетки: на границе силуэта значение равно нулю.
 *
 * @param mask Маска: `1` внутри фигуры, `0` снаружи.
 * @param cols Столбцов в маске.
 * @param rows Строк в маске.
 * @returns Поле дистанции той же длины, что маска.
 */
export function signedDistanceField(mask: Float32Array, cols: number, rows: number): Float32Array {
  const inside = distanceTransform(mask, cols, rows);
  const outside = distanceTransform(inverted(mask), cols, rows);
  const far = Math.max(cols, rows);
  const signed = new Float32Array(mask.length);
  for (let index = 0; index < mask.length; index += 1) {
    // У маски без единого противоположного пикселя расстояние бесконечно;
    // для воды это значит «до берега дальше, чем есть поле».
    const toLand = Number.isFinite(inside[index]) ? inside[index] : far;
    const toWater = Number.isFinite(outside[index]) ? outside[index] : far;
    signed[index] = mask[index] > 0.5 ? toLand - 0.5 : 0.5 - toWater;
  }

  return signed;
}

/** Дистанционное преобразование Шама: расстояние до границы в клетках. */
function distanceTransform(mask: Float32Array, cols: number, rows: number): Float32Array {
  const size = cols * rows;
  const distance = new Float32Array(size);
  const horizontal = 1;
  const diagonal = Math.SQRT2;
  for (let index = 0; index < size; index += 1) {
    distance[index] = mask[index] > 0.5 ? Number.POSITIVE_INFINITY : 0;
  }

  for (let row = 0; row < rows; row += 1) {
    for (let column = 0; column < cols; column += 1) {
      const index = row * cols + column;
      if (distance[index] === 0) {
        continue;
      }

      let best = distance[index];
      if (column > 0) best = Math.min(best, distance[index - 1] + horizontal);
      if (row > 0) best = Math.min(best, distance[index - cols] + horizontal);
      if (column > 0 && row > 0) best = Math.min(best, distance[index - cols - 1] + diagonal);
      if (column < cols - 1 && row > 0) best = Math.min(best, distance[index - cols + 1] + diagonal);
      distance[index] = best;
    }
  }

  for (let row = rows - 1; row >= 0; row -= 1) {
    for (let column = cols - 1; column >= 0; column -= 1) {
      const index = row * cols + column;
      if (distance[index] === 0) {
        continue;
      }

      let best = distance[index];
      if (column < cols - 1) best = Math.min(best, distance[index + 1] + horizontal);
      if (row < rows - 1) best = Math.min(best, distance[index + cols] + horizontal);
      if (column < cols - 1 && row < rows - 1) best = Math.min(best, distance[index + cols + 1] + diagonal);
      if (column > 0 && row < rows - 1) best = Math.min(best, distance[index + cols - 1] + diagonal);
      distance[index] = best;
    }
  }

  return distance;
}

/** Инверсия маски: внутренность становится внешностью. */
function inverted(mask: Float32Array): Float32Array {
  const result = new Float32Array(mask.length);
  for (let index = 0; index < mask.length; index += 1) {
    result[index] = mask[index] > 0.5 ? 0 : 1;
  }

  return result;
}
