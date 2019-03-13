<?php
/**
 * Copyright (C) 2019 Graham Breach
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
 * Class for structured data items
 */
class StructuredDataItem extends DataItem {

  private $item;
  private $dataset = 0;
  private $key_field = 0;
  private $dataset_fields = [];
  private $structure;
  public $key = 0;
  public $value = null;

  public function __construct($item, &$structure, $dataset, $key = null)
  {
    $this->item = $item;
    $this->key_field = $structure['key'];
    $this->dataset_fields = $structure['value'];
    $this->key = $this->key_field === null ? $key : $item[$this->key_field];
    if(isset($this->dataset_fields[$dataset]) &&
      isset($item[$this->dataset_fields[$dataset]]))
      $this->value = $item[$this->dataset_fields[$dataset]];

    $this->dataset = $dataset;
    $this->structure = &$structure;
  }

  /**
   * Constructs a new data item with a different dataset
   */
  public function newFrom($dataset)
  {
    return new StructuredDataItem($this->item, $this->structure,
      $dataset, $this->key);
  }

  /**
   * Getter for data fields
   */
  public function __get($field)
  {
    return $this->data($field);
  }

  /**
   * Returns some extra data from item
   */
  public function data($field)
  {
    if(!isset($this->structure[$field]))
      return null;
    $item_field = $this->structure[$field];
    if(is_array($item_field)) {
      if(!isset($item_field[$this->dataset]))
        return null;
      $item_field = $item_field[$this->dataset];
    }

    return isset($this->item[$item_field]) ? $this->item[$item_field] : null;
  }

  /**
   * Check if extra data field exists
   */
  public function rawDataExists($field)
  {
    return isset($this->item[$field]);
  }

  /**
   * Returns a value from the item without translating structure
   */
  public function rawData($field)
  {
    return isset($this->item[$field]) ? $this->item[$field] : null;
  }
}

