<?php
/**
 * Copyright (C) 2020 Graham Breach
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

/**
 * Class for measuring
 */
class BoundingBox {

  public $x1, $x2, $y1, $y2;

  public function __construct($x1, $y1, $x2, $y2)
  {
    $this->x1 = $x1;
    $this->x2 = $x2;
    $this->y1 = $y1;
    $this->y2 = $y2;
  }

  /**
   * Returns the width of the box
   */
  public function width()
  {
    return $this->x2 - $this->x1;
  }

  /**
   * Returns the height of the box
   */
  public function height()
  {
    return $this->y2 - $this->y1;
  }

  /**
   * Expands the box to fit the new sides
   */
  public function grow($x1, $y1, $x2, $y2)
  {
    $this->x1 = min($this->x1, $x1);
    $this->y1 = min($this->y1, $y1);
    $this->x2 = max($this->x2, $x2);
    $this->y2 = max($this->y2, $y2);
  }

  /**
   * Expands using another BoundingBox
   */
  public function growBox(BoundingBox $box)
  {
    $this->x1 = min($this->x1, $box->x1);
    $this->y1 = min($this->y1, $box->y1);
    $this->x2 = max($this->x2, $box->x2);
    $this->y2 = max($this->y2, $box->y2);
  }

  /**
   * Moves the box by $x, $y
   */
  public function offset($x, $y)
  {
    $this->x1 += $x;
    $this->y1 += $y;
    $this->x2 += $x;
    $this->y2 += $y;
  }

  /**
   * Flips the Y-axis values
   */
  public function flipY()
  {
    $tmp = $this->y1;
    $this->y1 = -$this->y2;
    $this->y2 = -$tmp;
  }
}

