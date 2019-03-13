<?php
/**
 * Copyright (C) 2011-2019 Graham Breach
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

class MultiGraph implements \Countable, \ArrayAccess, \Iterator {

  private $values;
  private $datasets = 0;
  private $force_assoc;
  private $datetime_keys;
  private $max_key = null;
  private $min_key = null;
  private $max_value = null;
  private $min_value = null;
  private $max_sum_value = null;
  private $min_sum_value = null;
  private $item_list = [];
  private $position = 0;
  private $item_cache = [];
  private $item_cache_pos = -1;

  public function __construct($values, $force_assoc, $datetime_keys, $int_keys)
  {
    $this->values =& $values;
    $this->force_assoc = $force_assoc;
    $this->datetime_keys = $datetime_keys;
    $keys = [];

    // convert unstructured data to structured
    if(count($values) > 1 && $this->values instanceof Data) {
      $new_data = [];
      $count = count($values);
      for($i = 0; $i < $count; ++$i) {
        foreach($this->values[$i] as $item) {
          if(!isset($new_data[$item->key])) {
            // fill the data item with NULLs
            $new_data[$item->key] = array_fill(0, $count + 1, null);
            $new_data[$item->key][0] = $item->key;
          }
          $new_data[$item->key][$i + 1] = $item->value;
        }
      }
      $new_data = array_values($new_data);

      $this->values = new StructuredData($new_data, $force_assoc,
        $datetime_keys, null, false, false, $int_keys, null, true);
    }
    $this->datasets = count($this->values);
  }

  /**
   * Pass all unhandled functions through to the structured data instance
   */
  public function __call($name, $arguments)
  {
    return call_user_func_array([$this->values, $name], $arguments);
  }

  /**
   * Implement Iterator interface
   */
  public function current()
  {
    if($this->item_cache_pos != $this->position) {
      $this->item_cache[0] = $this->values[0]->getItemByIndex($this->position);

      // use NewFrom to create other data items quicker
      for($i = 1; $i < $this->datasets; ++$i)
        $this->item_cache[$i] = $this->item_cache[0]->newFrom($i);
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
    return $this->position < $this->itemsCount();
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
  public function offsetSet($offset, $value)
  {
    throw new \Exception('Read-only');
  }
  public function offsetUnset($offset)
  {
    throw new \Exception('Read-only');
  }

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
  public function itemsCount()
  {
    // use -1 for all items
    return $this->values->itemsCount(-1);
  }

  /**
   * Returns the maximum value
   */
  public function getMaxValue()
  {
    if(!is_null($this->max_value))
      return $this->max_value;
    $maxima = [];
    $chunk_count = count($this->values);
    for($i = 0; $i < $chunk_count; ++$i)
      $maxima[] = $this->values->getMaxValue($i);

    $this->max_value = max($maxima);
    return $this->max_value;
  }


  /**
   * Returns the minimum value
   */
  public function getMinValue()
  {
    if(!is_null($this->min_value))
      return $this->min_value;
    $minima = [];
    $chunk_count = count($this->values);
    for($i = 0; $i < $chunk_count; ++$i) {
      $min_val = $this->values->getMinValue($i);
      if(!is_null($min_val))
        $minima[] = $min_val;
    }

    $this->min_value = min($minima);
    return $this->min_value;
  }


  /**
   * Returns the maximum key value
   */
  public function getMaxKey()
  {
    if(!is_null($this->max_key))
      return $this->max_key;

    $max = [];
    for($i = 0; $i < $this->datasets; ++$i)
      $max[] = $this->values->getMaxKey($i);
    $this->max_key = max($max);
    return $this->max_key;
  }

  /**
   * Returns the minimum key value
   */
  public function getMinKey()
  {
    if(!is_null($this->min_key))
      return $this->min_key;

    $min = [];
    for($i = 0; $i < $this->datasets; ++$i)
      $min[] = $this->values->getMinKey($i);
    $this->min_key = min($min);
    return $this->min_key;
  }

  /**
   * Returns the maximum sum value
   */
  public function getMaxSumValue()
  {
    if(is_null($this->max_sum_value))
      $this->calcMinMaxSumValues();
    return $this->max_sum_value;
  }

  /**
   * Returns the minimum sum value (the negative part)
   */
  public function getMinSumValue()
  {
    if(is_null($this->min_sum_value))
      $this->calcMinMaxSumValues();
    return $this->min_sum_value;
  }


  /**
   * Calculates the minimum and maximum sum values
   */
  private function calcMinMaxSumValues()
  {
    list($this->min_sum_value, $this->max_sum_value) =
      $this->values->getMinMaxSumValues();
  }

  /**
   * Access to the structured data values
   */
  public function &getValues()
  {
    return $this->values;
  }

  /**
   * Pass through to the structured data values
   */
  public function getData($index, $name, &$value)
  {
    // the reference means __call can't handle this
    return $this->values->getData($index, $name, $value);
  }
}

