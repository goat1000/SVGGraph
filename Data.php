<?php
/**
 * Copyright (C) 2013-2019 Graham Breach
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
 * Class for standard data
 */
class Data implements \Countable, \ArrayAccess, \Iterator {

  private $datasets = 0;
  private $data;
  private $assoc = null;
  private $datetime = null;
  private $min_value = [];
  private $max_value = [];
  private $min_key = [];
  private $max_key = [];
  public $error = null;

  public function __construct(&$data, $force_assoc, $datetime_keys)
  {
    if(empty($data[0])) {
      $this->error = 'No data';
      return;
    }
    $this->data = $data;
    $this->datasets = count($data);
    if($force_assoc)
      $this->assoc = true;
    if($datetime_keys) {
      if($this->rekey('Goat1000\\SVGGraph\\Graph::dateConvert')) {
        $this->datetime = true;
        $this->assoc = false;
        return;
      }
      $this->error = 'Too many date/time conversion errors';
    }
  }

  /**
   * Implement Iterator interface to prevent iteration...
   */
  private function notIterator()
  {
    throw new \Exception('Cannot iterate ' . __CLASS__);
  }
  public function current() { $this->notIterator(); }
  public function key() { $this->notIterator(); }
  public function next() { $this->notIterator(); }
  public function rewind() { $this->notIterator(); }
  public function valid() { $this->notIterator(); }

  /**
   * ArrayAccess methods
   */
  public function offsetExists($offset)
  {
    return array_key_exists($offset, $this->data);
  }

  public function offsetGet($offset)
  {
    return new DataIterator($this->data, $offset);
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
   * Returns minimum data value for a dataset
   */
  public function getMinValue($dataset = 0)
  {
    if(!isset($this->min_value[$dataset])) {
      $this->min_value[$dataset] = null;
      if(count($this->data[$dataset]))
        $this->min_value[$dataset] = Graph::min($this->data[$dataset]);
    }
    return $this->min_value[$dataset];
  }

  /**
   * Returns maximum data value for a dataset
   */
  public function getMaxValue($dataset = 0)
  {
    if(!isset($this->max_value[$dataset])) {
      $this->max_value[$dataset] = null;
      if(count($this->data[$dataset]))
        $this->max_value[$dataset] = max($this->data[$dataset]);
    }
    return $this->max_value[$dataset];
  }

  /**
   * Returns the minimum key value
   */
  public function getMinKey($dataset = 0)
  {
    if(!isset($this->min_key[$dataset])) {
      $this->min_key[$dataset] = null;
      if(count($this->data[$dataset])) {
        $this->min_key[$dataset] = $this->associativeKeys() ? 0 :
          min(array_keys($this->data[$dataset]));
      }
    }
    return $this->min_key[$dataset];
  }

  /**
   * Returns the maximum key value
   */
  public function getMaxKey($dataset = 0)
  {
    if(!isset($this->max_key[$dataset])) {
      $this->max_key[$dataset] = null;
      if(count($this->data[$dataset])) {
        $this->max_key[$dataset] = $this->associativeKeys() ?
          count($this->data[$dataset]) - 1 :
          max(array_keys($this->data[$dataset]));
      }
    }
    return $this->max_key[$dataset];
  }

  /**
   * Returns the key at a given index
   */
  public function getKey($index, $dataset = 0)
  {
    if(!$this->associativeKeys())
      return $index;

    // round index to nearest integer, or PHP will floor() it
    $index = (int)round($index);
    if($index >= 0) {
      $slice = array_slice($this->data[$dataset], $index, 1, true);
      // use foreach to get key and value
      foreach($slice as $k => $v)
        return $k;
    }
    return null;
  }

  /**
   * Returns TRUE if the keys are associative
   */
  public function associativeKeys()
  {
    if($this->assoc !== null)
      return $this->assoc;

    foreach(array_keys($this->data[0]) as $k)
      if(!is_integer($k))
        return ($this->assoc = true);
    return ($this->assoc = false);
  }

  /**
   * Returns the number of data items
   */
  public function itemsCount($dataset = 0)
  {
    if($dataset < 0)
      $dataset = 0;
    return count($this->data[$dataset]);
  }

  /**
   * Returns the min and max sum values
   */
  public function getMinMaxSumValues($start = 0, $end = null)
  {
    if($start != 0 || ($end !== null && $end != 0))
      throw new \Exception('Dataset not found');

    // structured data is used for multi-data, so just
    // return the min and max
    return [$this->getMinValue(), $this->getMaxValue()];
  }

  /**
   * Returns the min/max sum values for an array of datasets
   */
  public function getMinMaxSumValuesFor($datasets)
  {
    // Data class can't handle multiple datasets
    if(count($datasets) > 1)
      throw new \InvalidArgumentException('Multiple datasets not supported');

    $d = array_pop($datasets);
    if($d < 0 || $d >= $this->datasets)
      throw new \Exception('Dataset not found');

    return [$this->getMinValue($d), $this->getMaxValue($d)];
  }

  /**
   * Returns TRUE if the item exists, setting the $value
   */
  public function getData($index, $name, &$value)
  {
    // base class doesn't support this, so always return false
    return false;
  }

  /**
   * Transforms the keys using a callback function
   */
  public function rekey($callback)
  {
    $new_data = [];
    $count = $invalid = 0;
    for($d = 0; $d < $this->datasets; ++$d) {
      $new_data[$d] = [];
      foreach($this->data[$d] as $key => $value) {
        $new_key = call_user_func($callback, $key);

        // if the callback returns null, skip the value
        if($new_key === null) {
          ++$invalid;
          continue;
        }

        $new_data[$d][$new_key] = $value;
      }
      ++$count;
    }
    // if too many invalid, probably a format error
    if($count && $invalid / $count > 0.05)
      return false;
    $this->data = $new_data;
    // forget previous min/max
    $this->min_key = [];
    $this->max_key = [];
    return true;
  }
}

