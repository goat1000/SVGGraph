<?php
/**
 * Copyright (C) 2013-2022 Graham Breach
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
 * Class to iterate over standard data
 */
class DataIterator implements \Iterator {

  private $data = 0;
  private $dataset = 0;
  private $position = 0;
  private $count = 0;

  public function __construct(&$data, $dataset)
  {
    $this->dataset = $dataset;
    $this->data =& $data;
    $this->count = count($data[$dataset]);
  }

  /**
   * Iterator methods
   */
  #[\ReturnTypeWillChange]
  public function current()
  {
    return $this->getItemByIndex($this->position);
  }

  #[\ReturnTypeWillChange]
  public function key()
  {
    return $this->position;
  }

  #[\ReturnTypeWillChange]
  public function next()
  {
    ++$this->position;
    next($this->data[$this->dataset]);
  }

  #[\ReturnTypeWillChange]
  public function rewind()
  {
    $this->position = 0;
    reset($this->data[$this->dataset]);
  }

  #[\ReturnTypeWillChange]
  public function valid()
  {
    return $this->position < $this->count;
  }

  /**
   * Returns an item by index
   */
  public function getItemByIndex($index)
  {
    $slice = array_slice($this->data[$this->dataset], $index, 1, true);
    // use foreach to get key and value
    foreach($slice as $k => $v)
      return new DataItem($k, $v);
    return null;
  }

  /**
   * Returns an item by its key
   */
  public function getItemByKey($key)
  {
    if(isset($this->data[$this->dataset][$key]))
      return new DataItem($key, $this->data[$this->dataset][$key]);
    return null;
  }
}

