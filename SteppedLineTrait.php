<?php
/**
 * Copyright (C) 2021 Graham Breach
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

trait SteppedLineTrait {

  /**
   * Double each point to create a stepped line
   */
  protected function getLinePoints($points)
  {
    $new_points = [];
    $prev = null;
    foreach($points as $point) {
      if($prev) {
        $prev[0] = $point[0];
        $new_points[] = $prev;
      }
      $new_points[] = $point;
      $prev = $point;
    }

    return $new_points;
  }

  /**
   * Override to expand clipping rectangle slightly
   */
  public function gridClipPath()
  {
    if(isset($this->grid_clip_id))
      return $this->grid_clip_id;

    $rect = [
      'x' => $this->pad_left - 2, 'y' => $this->pad_top - 2,
      'width' => $this->width - $this->pad_left - $this->pad_right + 4,
      'height' => $this->height - $this->pad_top - $this->pad_bottom + 4,
    ];
    $clip_id = $this->newID();
    $this->defs->add($this->element('clipPath', ['id' => $clip_id], null,
      $this->element('rect', $rect)));
    return ($this->grid_clip_id = $clip_id);
  }

}

