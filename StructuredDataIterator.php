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

/**
 * For iterating over structured data
 */
class StructuredDataIterator implements \Iterator {

  private $data = 0;
  private $dataset = 0;
  private $position = 0;
  private $count = 0;
  private $structure = null;
  private $key_field = 0;
  private $dataset_fields = [];

  public function __construct(&$data, $dataset, $structure)
  {
    $this->dataset = $dataset;
    $this->data =& $data;
    $this->count = count($data);
    $this->structure = $structure;

    $this->key_field = $structure['key'];
    $this->dataset_fields = $structure['value'];
  }

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
  }

  #[\ReturnTypeWillChange]
  public function rewind()
  {
    $this->position = 0;
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
    if(isset($this->data[$index])) {
      $key = $this->key_field === null ? $index : null;
      return new StructuredDataItem($this->data[$index],
        $this->structure, $this->dataset, $key);
    }
    return null;
  }

  /**
   * Returns an item by key
   */
  public function getItemByKey($key)
  {
    if($this->key_field === null) {
      if(isset($this->data[$key]))
        return new StructuredDataItem($this->data[$key], $this->structure,
          $this->dataset, $key);
      return null;
    }

    foreach($this->data as $item)
      if(isset($item[$this->key_field]) && $item[$this->key_field] == $key)
        return new StructuredDataItem($item, $this->structure,
          $this->dataset, $key);
    return null;
  }
}

