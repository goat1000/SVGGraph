<?php
/**
 * Copyright (C) 2021-2022 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

namespace Goat1000\SVGGraph;

class ExplodedSemiDonutGraph extends SemiDonutGraph {

  use ExplodedPieGraphTrait {
    dataLabelPosition as protected traitDLP;
  }

  /**
   * Overridden to keep inner text in the middle
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    if($dataset === 'innertext') {
      if($this->getOption('flipped'))
        $y_offset = new Number($label_h / 2);
      else
        $y_offset = new Number($label_h / -2);
      return ['centre middle 0 ' . $y_offset, [$x, $y] ];
    }

    return $this->traitDLP($dataset, $index, $item, $x, $y, $w, $h,
      $label_w, $label_h);
  }
}

