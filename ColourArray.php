<?php
/**
 * Copyright (C) 2019-2022 Graham Breach
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

class ColourArray implements \ArrayAccess {

  private $colours;
  private $count;

  public function __construct($colours)
  {
    $this->colours = $colours;
    $this->count = count($colours);
  }

  /**
   * Not used by this class
   */
  public function setup($count)
  {
    // count comes from array, not number of bars etc.
  }

  /**
   * always true, because it wraps around
   */
  #[\ReturnTypeWillChange]
  public function offsetExists($offset)
  {
    return true;
  }

  /**
   * return the colour
   */
  #[\ReturnTypeWillChange]
  public function offsetGet($offset)
  {
    return $this->colours[$offset % $this->count];
  }

  #[\ReturnTypeWillChange]
  public function offsetSet($offset, $value)
  {
    $this->colours[$offset % $this->count] = $value;
  }

  #[\ReturnTypeWillChange]
  public function offsetUnset($offset)
  {
    throw new \Exception('Unexpected offsetUnset');
  }
}

