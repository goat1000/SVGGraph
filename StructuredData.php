<?php
/**
 * Copyright (C) 2013-2020 Graham Breach
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
 * Class for structured data
 */
class StructuredData implements \Countable, \ArrayAccess, \Iterator {

  private $datasets = 0;
  private $key_field = 0;
  private $dataset_fields = [];
  private $data;
  private $force_assoc = false;
  private $assoc = null;
  private $datetime;
  private $repeated_keys;
  private $sort_keys;
  private $assoc_test;
  private $structure = [];
  private $max_keys = [];
  private $min_keys = [];
  private $max_values = [];
  private $min_values = [];
  public $error = null;

  public function __construct(&$data, $force_assoc, $datetime_keys,
    $structure, $repeated_keys, $sort_keys, $integer_keys, $requirements,
    $rekey_done = false)
  {
    if($structure !== null && !empty($structure)) {
      // structure provided, is it valid?
      foreach(['key', 'value'] as $field) {
        if(!array_key_exists($field, $structure)) {
          $this->error = $field . ' field not set for structured data';
          return;
        }
      }

      if(!is_array($structure['value']))
        $structure['value'] = [$structure['value']];
      $this->key_field = $structure['key'];
      $this->dataset_fields = is_array($structure['value']) ?
        $structure['value'] : [$structure['value']];
    } else {
      // find key and datasets
      $keys = array_keys($data[0]);
      $this->key_field = array_shift($keys);
      $this->dataset_fields = $keys;

      // check for more datasets
      foreach($data as $item) {
        foreach(array_keys($item) as $key) {
          if($key !== $this->key_field &&
            array_search($key, $this->dataset_fields) === false) {
            $this->dataset_fields[] = $key;
          }
        }
      }

      // default structure
      $structure = [
        'key' => $this->key_field,
        'value' => $this->dataset_fields
      ];
    }

    // check any extra requirements
    if(is_array($requirements)) {
      $missing = [];
      foreach($requirements as $req) {
        if(!isset($structure[$req])) {
          $missing[] = $req;
        }
      }
      if(!empty($missing)) {
        $this->error = 'Required field(s) [' . implode(', ', $missing) .
          '] not set in data structure';
        return;
      }
    }

    $this->structure = $structure;
    // check if it really has more than one dataset
    if(isset($structure['datasets']) && $structure['datasets'] &&
      is_array(current($data)) && is_array(current(current($data)))) {
      $this->scatter2DDatasets($data);
    } else {
      $this->data = &$data;
    }
    $this->datasets = count($this->dataset_fields);
    $this->force_assoc = $force_assoc;
    $this->assoc_test = $integer_keys ? 'is_int' : 'is_numeric';

    $do_sort = false;
    if($datetime_keys || $this->associativeKeys()) {
      // reindex the array to 0, 1, 2, ...
      $this->data = array_values($this->data);
      if($datetime_keys) {
        if($rekey_done || $this->rekey('Goat1000\\SVGGraph\\Graph::dateConvert')) {
          $this->datetime = true;
          $this->assoc = false;
        } else {
          $this->error = 'Too many date/time conversion errors';
          return;
        }
        $do_sort = true;
      }
    } elseif($this->key_field !== null) {
      // if not associative, sort by key field
      $do_sort = true;
    }

    if($do_sort && $sort_keys) {
      $this->sort_keys = true;
      $field_sort = new FieldSort($this->key_field);
      $field_sort->sort($this->data);
    }

    if($this->repeatedKeys()) {
      if($repeated_keys == 'force_assoc')
        $this->force_assoc = true;
      elseif($repeated_keys != 'accept')
        $this->error = 'Repeated keys in data';
    }
  }

  /**
   * Converts plain Data to StructuredData
   */
  public static function convertFrom($values, $force_assoc, $datetime_keys,
    $int_keys)
  {
    if(count($values) > 1 && $values instanceof Data) {
      $new_data = [];
      $count = count($values);
      for($i = 0; $i < $count; ++$i) {
        foreach($values[$i] as $item) {
          if(!isset($new_data[$item->key])) {
            // fill the data item with NULLs
            $new_data[$item->key] = array_fill(0, $count + 1, null);
            $new_data[$item->key][0] = $item->key;
          }
          $new_data[$item->key][$i + 1] = $item->value;
        }
      }
      $new_data = array_values($new_data);

      $new_values = new StructuredData($new_data, $force_assoc,
        $datetime_keys, null, false, false, $int_keys, null, true);
      return $new_values;
    }

    // just return the old values
    return $values;
  }

  /**
   * Sets up normal structured data from scatter_2d datasets
   */
  private function scatter2DDatasets(&$data)
  {
    $newdata = [];
    $key_field = $this->structure['key'];
    $value_field = $this->structure['value'][0];

    // update structure
    $this->structure['key'] = 0;
    $this->structure['value'] = [];
    $this->key_field = 0;
    $this->dataset_fields = [];
    $set = 1;
    foreach($data as $dataset) {
      foreach($dataset as $item) {
        if(isset($item[$key_field]) && isset($item[$value_field])) {
          // no need to dedupe keys - no extra data and scatter_2d
          // only supported by scatter graphs
          $newdata[] = [0 => $item[$key_field], $set => $item[$value_field]];
        }
      }
      $this->structure['value'][] = $set;
      $this->dataset_fields[] = $set;
      ++$set;
    }
    $this->data = $newdata;
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
    return array_key_exists($offset, $this->dataset_fields);
  }

  public function offsetGet($offset)
  {
    return new StructuredDataIterator($this->data, $offset, $this->structure);
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
    if(isset($this->min_values[$dataset]))
      return $this->min_values[$dataset];

    $min = null;
    $key = $this->dataset_fields[$dataset];
    foreach($this->data as $item) {
      if(isset($item[$key]) && ($min === null || $item[$key] < $min))
        $min = $item[$key];
    }

    return ($this->min_values[$dataset] = $min);
  }

  /**
   * Returns maximum data value for a dataset
   */
  public function getMaxValue($dataset = 0)
  {
    if(isset($this->max_values[$dataset]))
      return $this->max_values[$dataset];

    $max = null;
    $key = $this->dataset_fields[$dataset];
    foreach($this->data as $item) {
      if(isset($item[$key]) && ($max === null || $item[$key] > $max))
        $max = $item[$key];
    }

    return ($this->max_values[$dataset] = $max);
  }

  /**
   * Returns the minimum key value
   */
  public function getMinKey($dataset = 0)
  {
    if(isset($this->min_keys[$dataset]))
      return $this->min_keys[$dataset];

    if($this->associativeKeys())
      return ($this->min_keys[$dataset] = 0);

    $min = null;
    $key = $this->key_field;
    $set = $this->dataset_fields[$dataset];
    if($key === null) {
      foreach($this->data as $k => $item) {
        if(isset($item[$set]) && ($min === null || $k < $min))
          $min = $k;
      }
    } else {
      foreach($this->data as $item) {
        if(isset($item[$key]) && isset($item[$set]) &&
          ($min === null || $item[$key] < $min))
          $min = $item[$key];
      }
    }

    return ($this->min_keys[$dataset] = $min);
  }

  /**
   * Returns the maximum key value for a dataset
   */
  public function getMaxKey($dataset = 0)
  {
    if(isset($this->max_keys[$dataset]))
      return $this->max_keys[$dataset];

    if($this->associativeKeys())
      return ($this->max_keys[$dataset] = count($this->data) - 1);

    $max = null;
    $key = $this->key_field;
    $set = $this->dataset_fields[$dataset];
    if($key === null) {
      foreach($this->data as $k => $item) {
        if(isset($item[$set]) && ($max === null || $k > $max))
          $max = $k;
      }
    } else {
      foreach($this->data as $item) {
        if(isset($item[$key]) && isset($item[$set]) &&
          ($max === null || $item[$key] > $max))
          $max = $item[$key];
      }
    }

    return ($this->max_keys[$dataset] = $max);
  }

  /**
   * Returns the key at a given index
   */
  public function getKey($index, $dataset = 0)
  {
    if(!$this->associativeKeys())
      return $index;
    $index = (int)round($index);
    if(isset($this->data[$index])) {
      if($this->key_field === null)
        return $index;
      $item = $this->data[$index];
      if(isset($item[$this->key_field]))
        return $item[$this->key_field];
    }
    return null;
  }

  /**
   * Returns TRUE if the keys are associative
   */
  public function associativeKeys()
  {
    if($this->force_assoc)
      return true;

    if($this->assoc !== null)
      return $this->assoc;

    // use either is_int or is_numeric to test
    $test = $this->assoc_test;
    if($this->key_field === null) {
      foreach($this->data as $k => $item) {
        if(! $test($k))
          return ($this->assoc = true);
      }
    } else {
      foreach($this->data as $item) {
        if(isset($item[$this->key_field]) && !$test($item[$this->key_field]))
          return ($this->assoc = true);
      }
    }
    return ($this->assoc = false);
  }

  /**
   * Returns the number of data items in a dataset
   * If $dataset is -1, returns number of items across all datasets
   */
  public function itemsCount($dataset = 0)
  {
    if($dataset == -1)
      return count($this->data);

    if(!isset($this->dataset_fields[$dataset]))
      return 0;
    $count = 0;
    $key = $this->dataset_fields[$dataset];
    foreach($this->data as $item)
      if(isset($item[$key]))
        ++$count;
    return $count;
  }

  /**
   * Returns TRUE if there are repeated keys
   * (also culls items without key field)
   */
  public function repeatedKeys()
  {
    if($this->repeated_keys !== null)
      return $this->repeated_keys;
    if($this->key_field === null)
      return false;
    $keys = [];
    foreach($this->data as $k => $item) {
      if(!isset($item[$this->key_field]))
        unset($this->data[$k]);
      else
        $keys[] = $item[$this->key_field];
    }
    $len = count($keys);
    $ukeys = array_unique($keys);
    return ($this->repeated_keys = ($len != count($ukeys)));
  }

  /**
   * Returns the min and max sum values for some datasets
   */
  public function getMinMaxSumValues($start = 0, $end = null)
  {
    if($start >= $this->datasets || ($end !== null && $end >= $this->datasets))
      throw new \Exception('Dataset not found');

    if($end === null)
      $end = $this->datasets - 1;
    return $this->getMinMaxSumValuesFor(range($start, $end));
  }

  /**
   * Returns the min/max sum values for an array of datasets
   */
  public function getMinMaxSumValuesFor($datasets)
  {
    $min_stack = [];
    $max_stack = [];

    foreach($this->data as $item) {
      $smin = $smax = 0;
      foreach($datasets as $dataset) {
        $vfield = $this->dataset_fields[$dataset];
        if(!isset($item[$vfield]))
          continue;
        $value = $item[$vfield];
        if($value !== null && !is_numeric($value))
          throw new \Exception('Non-numeric value');
        if($value > 0)
          $smax += $value;
        else
          $smin += $value;
      }
      $min_stack[] = $smin;
      $max_stack[] = $smax;
    }
    if(!count($min_stack))
      return [null, null];
    return [min($min_stack), max($max_stack)];
  }

  /**
   * Returns TRUE if the data field exists, setting $value
   */
  public function getData($index, $name, &$value)
  {
    $index = (int)round($index);
    if(!isset($this->structure[$name]) || !isset($this->data[$index]))
      return false;

    $item = $this->data[$index];
    if($item === null)
      return false;

    $field = $this->structure[$name];
    if(!is_array($field)) {
      if(!isset($item[$field]))
        return false;
      $value = $item[$field];
      return true;
    }

    // handle array fields
    $vals = [];
    $count = 0;
    foreach($field as $f) {
      $v = null;
      if(isset($item[$f])) {
        $v = $item[$f];
        ++$count;
      }
      $vals[] = $v;
    }

    // return true if any fields are set
    if($count > 0) {
      $value = $vals;
      return true;
    }
    return false;
  }

  /**
   * Transforms the keys using a callback function
   */
  public function rekey($callback)
  {
    // use a tab character as the new key name
    $rekey_name = "\t";
    $invalid = 0;
    foreach($this->data as $index => $item) {
      $key = $item[$this->key_field];
      $new_key = call_user_func($callback, $key);

      // if the callback returns NULL, NULL the data item
      if($new_key === null) {
        $this->data[$index] = [$rekey_name => null];
        ++$invalid;
        continue;
      }

      $this->data[$index][$rekey_name] = $new_key;
    }

    // if too many invalid, probably a format error
    if(count($this->data) && $invalid / count($this->data) > 0.05)
      return false;

    // forget previous min/max and assoc settings
    $this->min_keys = [];
    $this->max_keys = [];
    $this->assoc = null;
    $this->key_field = $this->structure['key'] = $rekey_name;
    return true;
  }
}

