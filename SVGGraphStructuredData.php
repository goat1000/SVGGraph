<?php
/**
 * Copyright (C) 2013-2015 Graham Breach
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

/**
 * Class for structured data
 */
class SVGGraphStructuredData implements Countable, ArrayAccess, Iterator { 

  private $datasets = 0;
  private $key_field = 0;
  private $axis_text_field = null;
  private $dataset_fields = array();
  private $data;
  private $force_assoc = false;
  private $assoc = null;
  private $repeated_keys;
  private $assoc_test;
  private $structure = array();
  private $iterators = array();
  private $max_keys = array();
  private $min_keys = array();
  private $max_values = array();
  private $min_values = array();
  private $before_label = '';
  private $after_label = '';
  private $encoding = 'UTF-8';
  public $error = null;

  public function __construct(&$data, $force_assoc, $structure, $repeated_keys,
    $integer_keys, $requirements)
  {
    if(!is_null($structure) && !empty($structure)) {
      // structure provided, is it valid?
      foreach(array('key', 'value') as $field) {
        if(!array_key_exists($field, $structure)) {
          $this->error = $field . ' field not set for structured data';
          return;
        }
      }

      if(!is_array($structure['value']))
        $structure['value'] = array($structure['value']);
      $this->key_field = $structure['key'];
      $this->dataset_fields = is_array($structure['value']) ?
        $structure['value'] : array($structure['value']);
      if(isset($structure['_before']))
        $this->before_label = $structure['_before'];
      if(isset($structure['_after']))
        $this->after_label = $structure['_after'];
      if(isset($structure['_encoding']))
        $this->encoding = $structure['_encoding'];
    } else {
      // find key and datasets
      $keys = array_keys($data[0]);
      $this->key_field = array_shift($keys);
      $this->dataset_fields = $keys;

      // check for more datasets
      foreach($data as $item) {
        foreach(array_keys($item) as $key)
          if($key != $this->key_field && 
            array_search($key, $this->dataset_fields) === FALSE)
            $this->dataset_fields[] = $key;
      }

      // default structure
      $structure = array(
        'key' => $this->key_field,
        'value' => $this->dataset_fields
      );
    }

    // check any extra requirements
    if(is_array($requirements)) {
      $missing = array();
      foreach($requirements as $req) {
        if(!isset($structure[$req])) {
          $missing[] = $req;
        }
      }
      if(!empty($missing)) {
        $missing = implode(', ', $missing);
        $this->error = "Required field(s) [{$missing}] not set in data structure";
        return;
      }
    }

    $this->structure = $structure;
    // check if it really has more than one dataset
    if(isset($structure['datasets']) && $structure['datasets'] &&
      is_array(current($data)) && is_array(current(current($data)))) {
      $this->Scatter2DDatasets($data);
    } else {
      $this->data = &$data;
    }
    $this->datasets = count($this->dataset_fields);
    $this->force_assoc = $force_assoc;
    $this->assoc_test = $integer_keys ? 'is_int' : 'is_numeric';
    if(isset($structure['axis_text']))
      $this->axis_text_field = $structure['axis_text'];

    if($this->AssociativeKeys()) {
      // reindex the array to 0, 1, 2, ...
      $this->data = array_values($this->data);
    } elseif(!is_null($this->key_field)) {
      // if not associative, sort by key field
      $GLOBALS['SVGGraphFieldSortField'] = $this->key_field;
      usort($this->data, 'SVGGraphFieldSort');
    }

    if($this->RepeatedKeys()) {
      if($repeated_keys == 'force_assoc')
        $this->force_assoc = true;
      elseif($repeated_keys != 'accept')
        $this->error = 'Repeated keys in data';
    }
    if(!$this->error) {
      for($i = 0; $i < $this->datasets; ++$i) {
        $this->iterators[$i] = new SVGGraphStructuredDataIterator($this->data,
          $i, $this->structure);
      }
    }
  }

  /**
   * Sets up normal structured data from scatter_2d datasets
   */
  private function Scatter2DDatasets(&$data)
  {
    $newdata = array();
    $key_field = $this->structure['key'];
    $value_field = $this->structure['value'][0];

    // update structure
    $this->structure['key'] = 0;
    $this->structure['value'] = array();
    $this->key_field = 0;
    $this->dataset_fields = array();
    $set = 1;
    foreach($data as $dataset) {
      foreach($dataset as $item) {
        if(isset($item[$key_field]) && isset($item[$value_field])) {
          // no need to dedupe keys - no extra data and scatter_2d
          // only supported by scatter graphs
          $newdata[] = array(0 => $item[$key_field], $set => $item[$value_field]);
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
    throw new Exception("Cannot iterate " . __CLASS__);
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
    return $this->iterators[$offset];
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
   * Returns minimum data value for a dataset
   */
  public function GetMinValue($dataset = 0)
  {
    if(isset($this->min_values[$dataset]))
      return $this->min_values[$dataset];

    $min = null;
    $key = $this->dataset_fields[$dataset];
    foreach($this->data as $item) {
      if(isset($item[$key]) && (is_null($min) || $item[$key] < $min))
        $min = $item[$key];
    }

    return ($this->min_values[$dataset] = $min);
  }

  /**
   * Returns maximum data value for a dataset
   */
  public function GetMaxValue($dataset = 0)
  {
    if(isset($this->max_values[$dataset]))
      return $this->max_values[$dataset];

    $max = null;
    $key = $this->dataset_fields[$dataset];
    foreach($this->data as $item) {
      if(isset($item[$key]) && (is_null($max) || $item[$key] > $max))
        $max = $item[$key];
    }

    return ($this->max_values[$dataset] = $max);
  }

  /**
   * Returns the minimum key value
   */
  public function GetMinKey($dataset = 0)
  {
    if(isset($this->min_keys[$dataset]))
      return $this->min_keys[$dataset];

    if($this->AssociativeKeys())
      return ($this->min_keys[$dataset] = 0);

    $min = null;
    $key = $this->key_field;
    $set = $this->dataset_fields[$dataset];
    if(is_null($key)) {
      foreach($this->data as $k => $item) {
        if(isset($item[$set]) && (is_null($min) || $k < $min))
          $min = $k;
      }
    } else {
      foreach($this->data as $item) {
        if(isset($item[$key]) && isset($item[$set]) &&
          (is_null($min) || $item[$key] < $min))
          $min = $item[$key];
      }
    }

    return ($this->min_keys[$dataset] = $min);
  }

  /**
   * Returns the maximum key value for a dataset
   */
  public function GetMaxKey($dataset = 0)
  {
    if(isset($this->max_keys[$dataset]))
      return $this->max_keys[$dataset];

    if($this->AssociativeKeys())
      return ($this->max_keys[$dataset] = count($this->data) - 1);

    $max = null;
    $key = $this->key_field;
    $set = $this->dataset_fields[$dataset];
    if(is_null($key)) {
      foreach($this->data as $k => $item) {
        if(isset($item[$set]) && (is_null($max) || $k > $max))
          $max = $k;
      }
    } else {
      foreach($this->data as $item) {
        if(isset($item[$key]) && isset($item[$set]) &&
          (is_null($max) || $item[$key] > $max))
          $max = $item[$key];
      }
    }

    return ($this->max_keys[$dataset] = $max);
  }

  /**
   * Returns the key (or axis text) at a given index/key
   */
  public function GetKey($index, $dataset = 0)
  {
    if(is_null($this->axis_text_field) && !$this->AssociativeKeys())
      return $index;
    $index = (int)round($index);
    if($this->AssociativeKeys()) {
      $item = $this->iterators[$dataset]->GetItemByIndex($index);
    } else {
      $item = $this->iterators[$dataset]->GetItemByKey($index);
    }
    if(is_null($item))
      return null;
    if($this->axis_text_field)
      return $item->RawData($this->axis_text_field);
    return $item->key;
  }

  /**
   * Returns TRUE if the keys are associative
   */
  public function AssociativeKeys()
  {
    if($this->force_assoc)
      return true;

    if(!is_null($this->assoc))
      return $this->assoc;

    // use either is_int or is_numeric to test
    $test = $this->assoc_test;
    if(is_null($this->key_field)) {
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
  public function ItemsCount($dataset = 0)
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
  public function RepeatedKeys()
  {
    if(!is_null($this->repeated_keys))
      return $this->repeated_keys;
    if(is_null($this->key_field))
      return false;
    $keys = array();
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
  public function GetMinMaxSumValues($start = 0, $end = NULL)
  {
    if($start >= $this->datasets || (!is_null($end) && $end >= $this->datasets))
      throw new Exception('Dataset not found');

    if(is_null($end))
      $end = $this->datasets - 1;
    $min_stack = array();
    $max_stack = array();

    foreach($this->data as $item) {
      $smin = $smax = 0;
      for($dataset = $start; $dataset <= $end; ++$dataset) {
        $vfield = $this->dataset_fields[$dataset];
        if(!isset($item[$vfield]))
          continue;
        $value = $item[$vfield];
        if(!is_null($value) && !is_numeric($value))
          throw new Exception('Non-numeric value');
        if($value > 0)
          $smax += $value;
        else
          $smin += $value;
      }
      $min_stack[] = $smin;
      $max_stack[] = $smax;
    }
    if(!count($min_stack))
      return array(NULL, NULL);
    return array(min($min_stack), max($max_stack));
  }

  /**
   * Strips units from before and after label
   */
  protected function StripLabel($label)
  {
    $before = $this->before_label;
    $after = $this->after_label;
    $enc = $this->encoding;
    $llen = SVGGraphStrlen($label, $enc);
    $blen = SVGGraphStrlen($before, $enc);
    $alen = SVGGraphStrlen($after, $enc);
    if($alen > 0 && SVGGraphSubstr($label, $llen - $alen, $alen, $enc) == $after)
      $label = SVGGraphSubstr($label, 0, $llen - $alen, $enc);
    if($blen > 0 && SVGGraphSubstr($label, 0, $blen, $enc) == $before)
      $label = SVGGraphSubstr($label, $blen, NULL, $enc);
    return $label;
  }
}

/**
 * For iterating over structured data
 */
class SVGGraphStructuredDataIterator implements Iterator { 

  private $data = 0;
  private $dataset = 0;
  private $position = 0;
  private $count = 0;
  private $structure = null;
  private $key_field = 0;
  private $dataset_fields = array();

  public function __construct(&$data, $dataset, $structure)
  {
    $this->dataset = $dataset;
    $this->data =& $data;
    $this->count = count($data);
    $this->structure = $structure;

    $this->key_field = $structure['key'];
    $this->dataset_fields = $structure['value'];
  }

  public function current()
  {
    return $this->GetItemByIndex($this->position);
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
    return $this->position < $this->count;
  }

  /**
   * Returns an item by index
   */
  public function GetItemByIndex($index)
  {
    if(isset($this->data[$index])) {
      $item = $this->data[$index];
      $key = is_null($this->key_field) ? $index : null;
      return new SVGGraphStructuredDataItem($this->data[$index],
        $this->structure, $this->dataset, $key);
    }
    return null;
  }

  /**
   * Returns an item by key
   */
  public function GetItemByKey($key)
  {
    if(is_null($this->key_field)) {
      if(isset($this->data[$key]))
        return new SVGGraphStructuredDataItem($this->data[$key], 
          $this->structure, $this->dataset, $key);
    } else {
      foreach($this->data as $item)
        if(isset($item[$this->key_field]) && $item[$this->key_field] == $key)
          return new SVGGraphStructuredDataItem($item, $this->structure,
            $this->dataset, $key);
    }
    return null;
  }
}

/**
 * Class for structured data items
 */
class SVGGraphStructuredDataItem {

  private $item;
  private $dataset = 0;
  private $key_field = 0;
  private $dataset_fields = array();
  private $structure;
  public $key = 0;
  public $value = null;

  public function __construct($item, &$structure, $dataset, $key = null)
  {
    $this->item = $item;
    $this->key_field = $structure['key'];
    $this->dataset_fields = $structure['value'];
    $this->key = is_null($this->key_field) ? $key : $item[$this->key_field];
    if(isset($this->dataset_fields[$dataset]) && 
      isset($item[$this->dataset_fields[$dataset]]))
      $this->value = $item[$this->dataset_fields[$dataset]];

    $this->dataset = $dataset;
    $this->structure = &$structure;
  }

  /**
   * Constructs a new data item with a different dataset
   */
  public function NewFrom($dataset)
  {
    return new SVGGraphStructuredDataItem($this->item, $this->structure,
      $dataset, $this->key);
  }

  /**
   * Returns some extra data from item
   */
  public function Data($field)
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
   * Returns a value from the item without translating structure
   */
  public function RawData($field)
  {
    return isset($this->item[$field]) ? $this->item[$field] : null;
  }
}


/**
 * Function for sorting by fields
 */
function SVGGraphFieldSort($a, $b)
{
  $f = $GLOBALS['SVGGraphFieldSortField'];
  // check that fields are present
  if(!isset($a[$f]) || !isset($b[$f]))
    return 0;
  if($a[$f] == $b[$f])
    return 0;
  return $a[$f] > $b[$f] ? 1 : -1;
}

/**
 * Field to sort by
 */
$SVGGraphFieldSortField = 0;

