<?php
/**
 * Copyright (C) 2019-2020 Graham Breach
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
 * Class for axis grid points
 */
class GridPoint {

  public $position;
  public $value;
  protected $text = [];

  public function __construct($position, $text, $value)
  {
    $this->position = $position;
    $this->value = $value;

    if(!is_array($text)) {
      $this->text[0] = (string)$text;
      return;
    }

    foreach($text as $t)
      $this->text[] = (string)$t;
  }

  /**
   * Returns the grid point text for an axis level
   */
  public function getText($level = 0)
  {
    return isset($this->text[$level]) ? $this->text[$level] : '';
  }

  /**
   * Returns true when the text is empty
   */
  public function blank($level = 0)
  {
    return !isset($this->text[$level]) || $this->text[$level] == '';
  }
}

