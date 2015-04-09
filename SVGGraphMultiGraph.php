<?php
/**
 * Copyright (C) 2011-2014 Graham Breach
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

class MultiGraph implements Countable, ArrayAccess, Iterator {

  private $values;
  private $datasets = 0;
  private $force_assoc;
  private $max_key = null;
  private $min_key = null;
  private $max_value = null;
  private $min_value = null;
  private $max_sum_value = null;
  private $min_sum_value = null;
  private $item_list = array();
  private $position = 0;
  private $item_cache = array();
  private $item_cache_pos = -1;

  public function __construct($values, $force_assoc, $int_keys)
  {
    $this->values =& $values;
    $this->force_assoc = $force_assoc;
    $keys = array();

    // convert unstructured data to structured
    if(count($values) > 1 && $this->values instanceof SVGGraphData) {
      $new_data = array();
      $count = count($values);
      for($i = 0; $i < $count; ++$i) {
        foreach($this->values[$i] as $item) {
          $new_data[$item->key][0] = $item->key;
          $new_data[$item->key][$i + 1] = $item->value;
        }
      }
      $new_data = array_values($new_data);
      require_once('SVGGraphStructuredData.php');
      $this->values = new SVGGraphStructuredData($new_data, $force_assoc,
        null, false, $int_keys, null);
    }
    $this->datasets = count($this->values);
  }

  /**
   * Implement Iterator interface
   */
  public function current()
  {
    if($this->item_cache_pos != $this->position) {
      $this->item_cache[0] = $this->values[0]->GetItemByIndex($this->position);

      // use NewFrom to create other data items quicker
      for($i = 1; $i < $this->datasets; ++$i)
        $this->item_cache[$i] = $this->item_cache[0]->NewFrom($i);
      $this->item_cache_pos = $this->position;
    }
    return $this->item_cache;
  }
  public function key()
  {
    return $this->position;
  }
  public function next()
  {
    ++$this->position;
  }
  public function rewind()
  {
    $this->position = 0;
  }
  public function valid()
  {
    return $this->position < $this->ItemsCount();
  }

  /**
   * ArrayAccess methods
   */
  public function offsetExists($offset)
  {
    return ($offset >= 0 && $offset < $this->datasets);
  }
  
  public function offsetGet($offset)
  {
    return $this->values[$offset];
  }

  /**
   * Don't allow writing to the data
   */
  public function offsetSet($offset, $value) { throw new Exception('Read-only'); }
  public function offsetUnset($offset) { throw new Exception('Read-only'); }

  /**
   * Countable method
   */
  public function count()
  {
    return $this->datasets;
  }

  /**
   * Returns the number of items
   */
  public function ItemsCount()
  {
    // use -1 for all items
    return $this->values->ItemsCount(-1);
  }

  /**
   * Returns the key for an item
   */
  public function GetKey($index)
  {
    return $this->values->GetKey($index);
  }

  /**
   * Returns the maximum value
   */
  public function GetMaxValue()
  {
    if(!is_null($this->max_value))
      return $this->max_value;
    $maxima = array();
    $chunk_count = count($this->values);
    for($i = 0; $i < $chunk_count; ++$i)
      $maxima[] = $this->values->GetMaxValue($i);

    $this->max_value = max($maxima);
    return $this->max_value;
  }


  /**
   * Returns the minimum value
   */
  public function GetMinValue()
  {
    if(!is_null($this->min_value))
      return $this->min_value;
    $minima = array();
    $chunk_count = count($this->values);
    for($i = 0; $i < $chunk_count; ++$i)
      $minima[] = $this->values->GetMinValue($i);

    $this->min_value = min($minima);
    return $this->min_value;
  }


  /**
   * Returns the maximum key value
   */
  public function GetMaxKey()
  {
    if(!is_null($this->max_key))
      return $this->max_key;
    
    $max = array();
    for($i = 0; $i < $this->datasets; ++$i)
      $max[] = $this->values->GetMaxKey($i);
    $this->max_key = max($max);
    return $this->max_key;
  }

  /**
   * Returns the minimum key value
   */
  public function GetMinKey()
  {
    if(!is_null($this->min_key))
      return $this->min_key;

    $min = array();
    for($i = 0; $i < $this->datasets; ++$i)
      $min[] = $this->values->GetMinKey($i);
    $this->min_key = min($min);
    return $this->min_key;
  }

  /**
   * Returns the maximum sum value
   */
  public function GetMaxSumValue()
  {
    if(is_null($this->max_sum_value))
      $this->CalcMinMaxSumValues();
    return $this->max_sum_value;
  }

  /**
   * Returns the minimum sum value (the negative part)
   */
  public function GetMinSumValue()
  {
    if(is_null($this->min_sum_value))
      $this->CalcMinMaxSumValues();
    return $this->min_sum_value;
  }


  /**
   * Calculates the minimum and maximum sum values
   */
  private function CalcMinMaxSumValues()
  {
    list($this->min_sum_value, $this->max_sum_value) =
      $this->values->GetMinMaxSumValues();
  }

  /**
   * Access to the structured data values
   */
  public function &GetValues()
  {
    return $this->values;
  }
}

