<?php
/**
 * Copyright (C) 2009-2022 Graham Breach
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

abstract class GridGraph extends Graph {

  protected $x_axes;
  protected $y_axes;
  protected $main_x_axis = 0;
  protected $main_y_axis = 0;
  protected $y_axis_positions = [];
  protected $dataset_axes = null;

  protected $g_width = null;
  protected $g_height = null;
  protected $label_adjust_done = false;
  protected $axes_calc_done = false;
  protected $grid_calc_done = false;
  protected $guidelines;
  protected $min_guide = ['x' => null, 'y' => null];
  protected $max_guide = ['x' => null, 'y' => null];

  protected $grid_limit;
  private $grid_clip_id = [];

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = [
      // Set to true for block-based labelling
      'label_centre' => false,
      // Set to true for graphs that don't support multiple axes (e.g. stacked)
      'single_axis' => false,
    ];
    $fs = array_merge($fs, $fixed_settings);

    // deprecated options need converting
    if(isset($settings['show_label_h']) &&
      !isset($settings['show_axis_text_h']))
      $settings['show_axis_text_h'] = $settings['show_label_h'];
    if(isset($settings['show_label_v']) &&
      !isset($settings['show_axis_text_v']))
      $settings['show_axis_text_v'] = $settings['show_label_v'];

    // convert _x and _y labels to _h and _v
    if(!empty($settings['label_y']) && empty($settings['label_v']))
      $settings['label_v'] = $settings['label_y'];
    if(!empty($settings['label_x']) && empty($settings['label_h']))
      $settings['label_h'] = $settings['label_x'];

    parent::__construct($w, $h, $settings, $fs);

    // set up user text classes file
    $tcf = $this->getOption('text_classes_file');
    if(!empty($tcf))
      TextClass::setFile($tcf);
  }

  /**
   * Modifies the graph padding to allow room for labels
   */
  protected function labelAdjustment()
  {
    $grid_l = $grid_t = $grid_r = $grid_b = null;

    $grid_set = $this->getOption('grid_left', 'grid_right', 'grid_top',
      'grid_bottom');
    if($grid_set) {
      if(!empty($this->getOption('grid_left')))
        $grid_l = $this->pad_left = abs($this->getOption('grid_left'));
      if(!empty($this->getOption('grid_top')))
        $grid_t = $this->pad_top = abs($this->getOption('grid_top'));

      if(!empty($this->getOption('grid_bottom'))) {
        $gb = $this->getOption('grid_bottom');
        $grid_b = $this->pad_bottom = $gb < 0 ? abs($gb) : $this->height - $gb;
      }
      if(!empty($this->getOption('grid_right'))) {
        $gr = $this->getOption('grid_right');
        $grid_r = $this->pad_right = $gr < 0 ? abs($gr) : $this->width - $gr;
      }
    }

    $label_v = $this->getOption('label_v');
    $label_h = $this->getOption('label_h');
    if($this->getOption('axis_right') && !empty($label_v) &&
      $this->yAxisCount() <= 1) {
      $label = is_array($label_v) ? $label_v[0] : $label_v;
      $this->setOption('label_v', [0 => '', 1 => $label]);
    }
    if($this->getOption('axis_top') && !empty($label_h) &&
      $this->xAxisCount() <= 1) {
      $label = is_array($label_h) ? $label_h[0] : $label_h;
      $this->setOption('label_h', [0 => '', 1 => $label]);
    }

    $pad_l = $pad_r = $pad_b = $pad_t = 0;
    $space_x = $this->width - $this->pad_left - $this->pad_right;
    $space_y = $this->height - $this->pad_top - $this->pad_bottom;
    $ends = $this->getAxisEnds();
    $extra_r = $extra_t = 0;

    for($i = 0; $i < 10; ++$i) {
      // find the text bounding box and add overlap to padding
      // repeat with the new measurements in case overlap increases
      $x_len = $space_x - $pad_r - $pad_l;
      $y_len = $space_y - $pad_t - $pad_b;

      // 3D graphs will use this to reduce axis length
      list($extra_r, $extra_t) = $this->adjustAxes($x_len, $y_len);
      list($x_axes, $y_axes) = $this->getAxes($ends, $x_len, $y_len);
      $bbox = $this->findAxisBBox($x_len, $y_len, $x_axes, $y_axes);
      $pr = $pl = $pb = $pt = 0;

      if($bbox['max_x'] > $x_len)
        $pr = ceil($bbox['max_x'] - $x_len);
      if($bbox['min_x'] < 0)
        $pl = ceil(abs($bbox['min_x']));
      if($bbox['min_y'] < 0)
        $pt = ceil(abs($bbox['min_y']));
      if($bbox['max_y'] > $y_len)
        $pb = ceil($bbox['max_y'] - $y_len);

      if($pr == $pad_r && $pl == $pad_l && $pt == $pad_t && $pb == $pad_b)
        break;
      $pad_r = $pr;
      $pad_l = $pl;
      $pad_t = $pt;
      $pad_b = $pb;
    }

    $pad_r += $extra_r;
    $pad_t += $extra_t;

    // apply the extra padding
    if($grid_l === null)
      $this->pad_left += $pad_l;
    if($grid_b === null)
      $this->pad_bottom += $pad_b;
    if($grid_r === null)
      $this->pad_right += $pad_r;
    if($grid_t === null)
      $this->pad_top += $pad_t;

    if($grid_r !== null || $grid_l !== null) {
      // fixed grid position means recalculating axis positions
      $offset = 0;
      foreach($this->y_axis_positions as $axis_no => $pos) {
        if($axis_no == 0)
          continue;
        if($offset) {
          $newpos = $pos + $offset;
        } else {
          $newpos = $this->width - $this->pad_left - $this->pad_right;
          $offset = $newpos - $pos;
        }
        $this->y_axis_positions[$axis_no] = $newpos;
      }
    }

    // see if the axes fit
    foreach($this->y_axis_positions as $axis_no => $pos) {
      if($axis_no > 0 && $pos <= 0)
        throw new \Exception('Not enough space for ' . $this->yAxisCount() . ' axes');
    }
    $this->label_adjust_done = true;
  }

  /**
   * Subclasses can override this to modify axis lengths
   * Return amount of padding added [r,t]
   */
  protected function adjustAxes(&$x_len, &$y_len)
  {
    return [0, 0];
  }

  /**
   * Find the bounding box of the axis text for given axis lengths
   */
  protected function findAxisBBox($length_x, $length_y, $x_axes, $y_axes)
  {
    // initialise maxima and minima
    $min_x = [$this->width];
    $min_y = [$this->height];
    $max_x = [0];
    $max_y = [0];

    $axis_no = -1;
    foreach($x_axes as $x_axis) {
      ++$axis_no;
      if($x_axis === null)
        continue;
      $display_axis = $this->getDisplayAxis($x_axis, $axis_no, 'h', 'x');
      $m = $display_axis->measure();
      $offset = $axis_no ? 0 : $length_y;
      $min_x[] = $m->x1;
      $min_y[] = $m->y1 + $offset;
      $max_x[] = $m->x2;
      $max_y[] = $m->y2 + $offset;
    }

    $axis_no = -1;
    $right_pos = $length_x;
    foreach($y_axes as $y_axis) {
      ++$axis_no;
      if($y_axis === null)
        continue;
      $ybb = $this->yAxisBBox($y_axis, $length_y, $axis_no);

      if($axis_no > 0) {
        // for offset axes, the inside overlap must be added on too
        $outer = $ybb['max_x'];
        $inner = $axis_no > 1 ? abs($ybb['min_x']) : 0;

        $this->y_axis_positions[$axis_no] = $right_pos + $inner;
        $ybb['max_x'] += $right_pos + $inner;
        $ybb['min_x'] += $right_pos + $inner;
        $right_pos += $inner + $outer + $this->getOption('axis_space');
      } else {
        $this->y_axis_positions[$axis_no] = 0;
      }

      $max_x[] = $ybb['max_x'];
      $min_x[] = $ybb['min_x'];
      $max_y[] = $ybb['max_y'];
      $min_y[] = $ybb['min_y'];
    }
    return [
      'min_x' => min($min_x), 'min_y' => min($min_y),
      'max_x' => max($max_x), 'max_y' => max($max_y)
    ];
  }

  /**
   * Returns bounding box for a Y-axis
   */
  protected function yAxisBBox($axis, $length, $axis_no)
  {
    $display_axis = $this->getDisplayAxis($axis, $axis_no, 'v', 'y');
    $bbox = $display_axis->measure();

    // reversed Y-axis measures from bottom
    if($axis->reversed())
      $bbox->offset(0, $length);
    return [ 'min_x' => $bbox->x1, 'min_y' => $bbox->y1,
      'max_x' => $bbox->x2, 'max_y' => $bbox->y2 ];
  }

  /**
   * Sets up grid width and height to fill padded area
   */
  protected function setGridDimensions()
  {
    $this->g_height = $this->height - $this->pad_top - $this->pad_bottom;
    $this->g_width = $this->width - $this->pad_left - $this->pad_right;
  }

  /**
   * Returns the list of datasets and their axis numbers
   */
  public function getDatasetAxes()
  {
    if($this->dataset_axes !== null)
      return $this->dataset_axes;

    $dataset_axis = $this->getOption('dataset_axis');
    $default_axis = $this->getOption('axis_right') ? 1 : 0;
    $single_axis = $this->getOption('single_axis');
    if(empty($this->multi_graph)) {
      $enabled_datasets = [0];
      $v =& $this->values;
    } else {
      $enabled_datasets = $this->multi_graph->getEnabledDatasets();
      $v =& $this->multi_graph->getValues();
    }

    $d_axes = [];
    foreach($enabled_datasets as $d) {
      $axis = $default_axis;

      // only use the chosen dataset axis when allowed and not empty
      if(!$single_axis && isset($dataset_axis[$d]) && $v->itemsCount($d) > 0)
        $axis = $dataset_axis[$d];
      $d_axes[$d] = $axis;
    }

    // check that the axes are used in order
    $used = [];
    foreach($d_axes as $a)
      $used[$a] = 1;
    $max = max($d_axes);
    $unused = [];
    for($a = $default_axis; $a <= $max; ++$a)
      if(!isset($used[$a]))
        $unused[] = $a;

    if(count($unused))
      throw new \Exception('Unused axis: ' . implode(', ', $unused));

    $this->dataset_axes = $d_axes;
    return $this->dataset_axes;
  }

  /**
   * Returns the number of Y-axes
   */
  protected function yAxisCount()
  {
    $dataset_axes = $this->getDatasetAxes();
    if(count($dataset_axes) <= 1)
      return 1;

    return count(array_unique($dataset_axes));
  }

  /**
   * Returns the number of X-axes
   */
  protected function xAxisCount()
  {
    return 1;
  }

  /**
   * Returns an x or y axis, or NULL if it does not exist
   */
  public function getAxis($axis, $num)
  {
    if($num === null)
      $num = ($axis == 'y' ? $this->main_y_axis : $this->main_x_axis);
    $axis_var = $axis == 'y' ? 'y_axes' : 'x_axes';
    if(isset($this->{$axis_var}) && isset($this->{$axis_var}[$num]))
      return $this->{$axis_var}[$num];
    return null;
  }

  /**
   * Returns the Y-axis for a dataset
   */
  protected function datasetYAxis($dataset)
  {
    $dataset_axes = $this->getDatasetAxes();
    if(isset($dataset_axes[$dataset]))
      return $dataset_axes[$dataset];
    return $this->getOption('axis_right') ? 1 : 0;
  }

  /**
   * Returns the minimum value for an axis
   */
  protected function getAxisMinValue($axis)
  {
    if($this->yAxisCount() <= 1)
      return $this->getMinValue();

    $min = [];
    $dataset_axes = $this->getDatasetAxes();
    foreach($dataset_axes as $dataset => $d_axis) {
      if($d_axis == $axis) {
        $min_val = $this->values->getMinValue($dataset);
        if($min_val !== null)
          $min[] = $min_val;
      }
    }
    return empty($min) ? null : min($min);
  }

  /**
   * Returns the maximum value for an axis
   */
  protected function getAxisMaxValue($axis)
  {
    if($this->yAxisCount() <= 1)
      return $this->getMaxValue();

    $max = [];
    $dataset_axes = $this->getDatasetAxes();
    foreach($dataset_axes as $dataset => $d_axis) {
      if($d_axis == $axis) {
        $max_val = $this->values->getMaxValue($dataset);
        if($max_val !== null)
          $max[] = $max_val;
      }
    }
    return empty($max) ? null : max($max);
  }

  /**
   * Returns fixed min and max option for an axis
   */
  protected function getFixedAxisOptions($axis, $index)
  {
    $a = $axis == 'y' ? 'v' : 'h';
    $min = $this->getOption(['axis_min_' . $a, $index]);
    $max = $this->getOption(['axis_max_' . $a, $index]);
    return [$min, $max];
  }

  /**
   * Returns an array containing the value and key axis min and max
   */
  protected function getAxisEnds()
  {
    // check guides
    if($this->guidelines === null)
      $this->calcGuidelines();

    $v_max = $v_min = $k_max = $k_min = [];
    $g_min_x = $g_min_y = $g_max_x = $g_max_y = null;

    if($this->guidelines !== null) {
      list($g_min_x, $g_min_y, $g_max_x, $g_max_y) = $this->guidelines->getMinMax();
    }
    $y_axis_count = $this->yAxisCount();
    $x_axis_count = $this->xAxisCount();

    for($i = 0; $i < $y_axis_count; ++$i) {
      list($fixed_min, $fixed_max) = $this->getFixedAxisOptions('y', $i);

      // validate
      if(is_numeric($fixed_min) && is_numeric($fixed_max) &&
        $fixed_max < $fixed_min)
        throw new \Exception('Invalid Y axis options: min > max (' .
          $fixed_min . ' > ' . $fixed_max . ')');

      if(is_numeric($fixed_min) && is_numeric($fixed_max)) {
        $v_min[] = $fixed_min;
        $v_max[] = $fixed_max;
      } else {
        $allow_zero = !$this->getOption(['log_axis_y', $i], false);
        $prefer_zero = $this->getOption(['axis_zero_y', $i], true);
        $axis_min_value = $this->getAxisMinValue($i);
        $axis_max_value = $this->getAxisMaxValue($i);
        if($g_min_y !== null && $g_min_y < $axis_min_value)
          $axis_min_value = $g_min_y;
        if($g_max_y !== null && $g_max_y > $axis_max_value)
          $axis_max_value = $g_max_y;

        $v_min[] = is_numeric($fixed_min) ? $fixed_min :
          Axis::calcMinimum($axis_min_value, $axis_max_value,
            $allow_zero, $prefer_zero);

        $v_max[] = is_numeric($fixed_max) ? $fixed_max :
          Axis::calcMaximum($axis_min_value, $axis_max_value,
            $allow_zero, $prefer_zero);
      }
      if($v_max[$i] < $v_min[$i])
        throw new \Exception('Invalid Y axis: min > max (' .
          $v_min[$i] . ' > ' . $v_max[$i] . ')');
    }

    for($i = 0; $i < $x_axis_count; ++$i) {
      list($fixed_min, $fixed_max) = $this->getFixedAxisOptions('x', $i);

      if($this->getOption('datetime_keys')) {
        // 0 is 1970-01-01, not a useful minimum
        if(empty($fixed_max)) {
          // guidelines support datetime values too
          if($g_max_x !== null)
            $k_max[] = max($this->getMaxKey(), $g_max_x);
          else
            $k_max[] = $this->getMaxKey();
        } else {
          $d = Graph::dateConvert($fixed_max);
          // subtract a sec
          if($d !== null)
            $k_max[] = $d - 1;
          else
            throw new \Exception('Could not convert [' . $fixed_max .
              '] to datetime');
        }
        if(empty($fixed_min)) {
          if($g_min_x !== null)
            $k_min[] = min($this->getMinKey(), $g_min_x);
          else
            $k_min[] = $this->getMinKey();
        } else {
          $d = Graph::dateConvert($fixed_min);
          if($d !== null)
            $k_min[] = $d;
          else
            throw new \Exception('Could not convert [' . $fixed_min .
              '] to datetime');
        }
      } else {
        // validate
        if(is_numeric($fixed_min) && is_numeric($fixed_max) &&
          $fixed_max < $fixed_min)
          throw new \Exception('Invalid X axis options: min > max (' .
            $fixed_min . ' > ' . $fixed_max . ')');

        $log_axis = $this->getOption(['log_axis_x', $i]);
        if(is_numeric($fixed_max)) {
          $k_max[] = $fixed_max;
        } elseif($log_axis) {
          $max_val = $this->getMaxKey();
          if($g_max_x !== null)
            $max_val = max($max_val, (float)$g_max_x);
          $k_max[] = $max_val;
        } else {
          $k_max[] = max(0, $this->getMaxKey(), (float)$g_max_x);
        }

        if(is_numeric($fixed_min)) {
          $k_min[] = $fixed_min;
        } elseif($log_axis) {
          $min_val = $this->getMinKey();
          if($g_min_x !== null)
            $min_val = min($min_val, (float)$g_min_x);
          $k_min[] = $min_val;
        } else {
          $k_min[] = min(0, $this->getMinKey(), (float)$g_min_x);
        }
      }
      if($k_max[$i] < $k_min[$i])
        throw new \Exception('Invalid X axis: min > max (' . $k_min[$i] .
          ' > ' . $k_max[$i] . ')');
    }
    return compact('v_max', 'v_min', 'k_max', 'k_min');
  }

  /**
   * Returns the factory for an X-axis
   */
  protected function getXAxisFactory()
  {
    $x_bar = $this->getOption('label_centre');
    return new AxisFactory($this->getOption('datetime_keys'), $this->settings,
      true, $x_bar, false);
  }

  /**
   * Returns the factory for a Y-axis
   */
  protected function getYAxisFactory()
  {
    return new AxisFactory(false, $this->settings, false, false, true);
  }

  protected function createXAxis($factory, $length, $ends, $i, $min_space, $grid_division)
  {
    $max_h = $ends['k_max'][$i];
    $min_h = $ends['k_min'][$i];
    if(!is_numeric($max_h) || !is_numeric($min_h))
      throw new \Exception('Non-numeric min/max');

    $min_unit = 1;
    $units_after = (string)$this->getOption(['units_x', $i]);
    $units_before = (string)$this->getOption(['units_before_x', $i]);
    $decimal_digits = $this->getOption(['decimal_digits_x', $i],
      'decimal_digits');
    $text_callback = $this->getOption(['axis_text_callback_x', $i],
      'axis_text_callback');
    $values = $this->multi_graph ? $this->multi_graph : $this->values;
    $log = $this->getOption(['log_axis_x', $i]);
    $log_base = $this->getOption(['log_axis_x_base', $i]);
    $levels = $this->getOption(['axis_levels_h', $i]);
    $ticks = $this->getOption('axis_ticks_x');

    return $factory->get($length, $min_h, $max_h, $min_unit,
      $min_space, $grid_division, $units_before, $units_after,
      $decimal_digits, $text_callback, $values, $log, $log_base,
      $levels, $ticks);
  }

  protected function createYAxis($factory, $length, $ends, $i, $min_space, $grid_division)
  {
    $max_v = $ends['v_max'][$i];
    $min_v = $ends['v_min'][$i];
    if(!is_numeric($max_v) || !is_numeric($min_v))
      throw new \Exception('Non-numeric min/max');

    $min_unit = $this->getOption(['minimum_units_y', $i]);
    $text_callback = $this->getOption(['axis_text_callback_y', $i],
      'axis_text_callback');
    $decimal_digits = $this->getOption(['decimal_digits_y', $i],
      'decimal_digits');
    $units_after = (string)$this->getOption(['units_y', $i]);
    $units_before = (string)$this->getOption(['units_before_y', $i]);
    $log = $this->getOption(['log_axis_y', $i]);
    $log_base = $this->getOption(['log_axis_y_base', $i]);
    $ticks = $this->getOption(['axis_ticks_y', $i]);
    $values = $levels = null;

    if($min_v == $max_v) {
      if($min_unit > 0) {
        $inc = $min_unit;
      } else {
        $fallback = $this->getOption('axis_fallback_max');
        $inc = $fallback > 0 ? $fallback : 1;
      }
      $max_v += $inc;
    }

    $y_axis = $factory->get($length, $min_v, $max_v, $min_unit,
      $min_space, $grid_division, $units_before, $units_after,
      $decimal_digits, $text_callback, $values, $log, $log_base,
      $levels, $ticks);
    $y_axis->setTightness($this->getOption(['axis_tightness_y', $i]));
    return $y_axis;
  }

  /**
   * Returns the X and Y axis class instances as a list
   */
  protected function getAxes($ends, &$x_len, &$y_len)
  {
    $this->validateAxisOptions();
    $x_axis_factory = $this->getXAxisFactory();
    $y_axis_factory = $this->getYAxisFactory();

    // at the moment there will only be 1 X axis, but allow for expansion
    $x_axes = [];
    $x_axis_count = $this->xAxisCount();
    for($i = 0; $i < $x_axis_count; ++$i) {

      $min_space = $this->getOption(['minimum_grid_spacing_h', $i],
        'minimum_grid_spacing');
      $grid_division = $this->getOption(['grid_division_h', $i]);
      if(is_numeric($grid_division)) {
        if($grid_division <= 0)
          throw new \Exception('Invalid grid division');
        // if fixed grid spacing is specified, make the min spacing 1 pixel
        $min_space = 1;
        $this->setOption('minimum_grid_spacing_h', 1);
      }

      $x_axes[] = $this->createXAxis($x_axis_factory, $x_len, $ends, $i, $min_space, $grid_division);
    }
    // double X axis adds a second axis with same ends as first
    if($x_axis_count == 1 && $this->getOption('axis_double_x'))
      $x_axes[] = $this->createXAxis($x_axis_factory, $x_len, $ends, 0, $min_space, $grid_division);

    $y_axes = [];
    $y_axis_count = $this->yAxisCount();
    for($i = 0; $i < $y_axis_count; ++$i) {

      $min_space = $this->getOption(['minimum_grid_spacing_v', $i],
        'minimum_grid_spacing');
      // make sure minimum_grid_spacing option is an array
      if(!is_array($this->getOption('minimum_grid_spacing_v')))
        $this->setOption('minimum_grid_spacing_v', []);

      $grid_division = $this->getOption(['grid_division_v', $i]);
      if(is_numeric($grid_division)) {
        if($grid_division <= 0)
          throw new \Exception('Invalid grid division');
        // if fixed grid spacing is specified, make the min spacing 1 pixel
        $this->setOption('minimum_grid_spacing_v', 1, $i);
        $min_space = 1;
      } else {
        $mgsv = $this->getOption('minimum_grid_spacing_v');
        if(!isset($mgsv[$i]))
          $this->setOption('minimum_grid_spacing_v', $min_space, $i);
      }

      $y_axes[] = $this->createYAxis($y_axis_factory, $y_len, $ends, $i, $min_space, $grid_division);
    }
    // double Y axis adds a second axis with same ends as first
    if($y_axis_count == 1 && $this->getOption('axis_double_y'))
      $y_axes[] = $this->createYAxis($y_axis_factory, $y_len, $ends, 0, $min_space, $grid_division);

    // set the main axis correctly
    if($this->getOption('axis_right') && count($y_axes) == 1) {
      $this->main_y_axis = 1;
      array_unshift($y_axes, null);
    }
    if($this->getOption('axis_top') && count($x_axes) == 1) {
      $this->main_x_axis = 1;
      array_unshift($x_axes, null);
    }
    return [$x_axes, $y_axes];
  }

  /**
   * Axis options can be complicated
   */
  protected function validateAxisOptions()
  {
    // disable units for associative keys
    if($this->values->associativeKeys()) {
      $this->setOption('units_x', null);
      $this->setOption('units_before_x', null);
    }

    // ticks are a bit tricky, could be an array or array of arrays or ...
    $ticks = $this->getOption('axis_ticks_y');
    if(is_array($ticks)) {
      $count = count($ticks);
      $nulls = $arrays = 0;
      foreach($ticks as $t) {
        if(is_array($t))
          ++$arrays;
        elseif($t === null)
          ++$nulls;
      }

      // if array of nulls, null it
      if($nulls == $count)
        $this->setOption('axis_ticks_y', null);
      // if single array, enclose it
      elseif($arrays == 0)
        $this->setOption('axis_ticks_y', [$ticks]);
    }
  }

  /**
   * Calculates the effect of axes, applying to padding
   */
  protected function calcAxes()
  {
    if($this->axes_calc_done)
      return;

    // can't have multiple invisible axes
    if(!$this->getOption('show_axes'))
      $this->setOption('dataset_axis', null);

    $ends = $this->getAxisEnds();
    if(!$this->label_adjust_done)
      $this->labelAdjustment();
    if($this->g_height === null || $this->g_width === null)
      $this->setGridDimensions();

    list($x_axes, $y_axes) = $this->getAxes($ends, $this->g_width,
      $this->g_height);

    $this->x_axes = $x_axes;
    $this->y_axes = $y_axes;
    $this->axes_calc_done = true;
  }

  /**
   * Calculates the position of grid lines
   */
  protected function calcGrid()
  {
    if($this->grid_calc_done)
      return;

    $y_axis = $this->y_axes[$this->main_y_axis];
    $x_axis = $this->x_axes[$this->main_x_axis];
    $y_axis->getGridPoints(null);
    $x_axis->getGridPoints(null);

    $this->grid_limit = 0.01 + $this->g_width;
    if($this->getOption('label_centre'))
      $this->grid_limit -= $x_axis->unit() / 2;
    $this->grid_calc_done = true;
  }

  /**
   * Returns the grid points for a Y-axis
   */
  protected function getGridPointsY($axis)
  {
    $a = $this->y_axes[$axis];
    $offset = $a->reversed() ? $this->height - $this->pad_bottom : $this->pad_top;
    return $a->getGridPoints($offset);
  }

  /**
   * Returns the grid points for an X-axis
   */
  protected function getGridPointsX($axis)
  {
    $a = $this->x_axes[$axis];
    $offset = $a->reversed() ? $this->width - $this->pad_right : $this->pad_left;
    return $a->getGridPoints($offset);
  }

  /**
   * Returns the subdivisions for a Y-axis
   */
  protected function getSubDivsY($axis)
  {
    $a = $this->y_axes[$axis];
    $offset = $a->reversed() ? $this->height - $this->pad_bottom : $this->pad_top;
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'),
      $this->getOption(['minimum_units_y', $axis]), $offset,
      $this->getOption(['subdivision_v', $axis]));
  }

  /**
   * Returns the subdivisions for an X-axis
   */
  protected function getSubDivsX($axis)
  {
    $a = $this->x_axes[$axis];
    $offset = $a->reversed() ? $this->width - $this->pad_right : $this->pad_left;
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'), 1,
      $offset, $this->getOption(['subdivision_h', $axis]));
  }

  /**
   * A function to return the DisplayAxis - subclasses should override
   * to return a different axis type
   */
  protected function getDisplayAxis($axis, $axis_no, $orientation, $type)
  {
    $var = 'main_' . $type . '_axis';
    $main = ($axis_no == $this->{$var});
    $levels = $this->getOption(['axis_levels_' . $orientation, $axis_no]);
    $class = 'Goat1000\SVGGraph\DisplayAxis';
    if(is_numeric($levels) && $levels > 1)
      $class = 'Goat1000\SVGGraph\DisplayAxisLevels';

    return new $class($this, $axis, $axis_no, $orientation, $type, $main,
      $this->getOption('label_centre'));
  }

  /**
   * Returns the location of an axis
   */
  protected function getAxisLocation($orientation, $axis_no)
  {
    $x = $this->pad_left;
    $y = $this->pad_top + $this->g_height;
    if($orientation == 'h') {
      if($axis_no == 1) {
        // top axis
        $y = $this->pad_top;
      } else {
        $y0 = $this->y_axes[$this->main_y_axis]->zero();
        if($this->getOption('show_axis_h') && $y0 >= 0 && $y0 <= $this->g_height)
          $y -= $y0;
      }
    } else {
      if($axis_no == 0) {
        $x0 = $this->x_axes[$this->main_x_axis]->zero();
        if($x0 >= 1 && $x0 < $this->g_width)
          $x += $x0;
      } else {
        $x += $this->y_axis_positions[$axis_no];
      }
      // Y axis is normally reversed
      if(!$this->y_axes[$axis_no]->reversed())
        $y = $this->pad_top;
    }
    return [$x, $y];
  }

  /**
   * Draws bar or line graph axes
   */
  protected function axes()
  {
    $this->calcGrid();
    $axes = $label_group = $axis_text = '';

    foreach($this->x_axes as $axis_no => $axis) {
      if($axis !== null) {
        $display_axis = $this->getDisplayAxis($axis, $axis_no, 'h', 'x');
        list($x, $y) = $this->getAxisLocation('h', $axis_no);
        $axes .= $display_axis->draw($x, $y, $this->pad_left, $this->pad_top,
          $this->g_width, $this->g_height);
      }
    }
    foreach($this->y_axes as $axis_no => $axis) {
      if($axis !== null) {
        $display_axis = $this->getDisplayAxis($axis, $axis_no, 'v', 'y');
        list($x, $y) = $this->getAxisLocation('v', $axis_no);
        $axes .= $display_axis->draw($x, $y, $this->pad_left, $this->pad_top,
          $this->g_width, $this->g_height);
      }
    }

    return $axes;
  }

  /**
   * Returns a set of gridlines
   */
  protected function gridLines($path, $colour, $dash, $width, $more = null)
  {
    if($path->isEmpty() || $colour == 'none')
      return '';
    $opts = ['d' => $path, 'stroke' => new Colour($this, $colour)];
    if(!empty($dash))
      $opts['stroke-dasharray'] = $dash;
    if($width && $width != 1)
      $opts['stroke-width'] = $width;
    if(is_array($more))
      $opts = array_merge($opts, $more);
    return $this->element('path', $opts);
  }

  /**
   * Returns the SVG fragment for grid background stripes
   */
  protected function getGridStripes()
  {
    if(!$this->getOption('grid_back_stripe'))
      return '';

    // use array of colours if available, otherwise stripe a single colour
    $colours = $this->getOption('grid_back_stripe_colour');
    if(!is_array($colours))
      $colours = [null, $colours];
    $opacity = $this->getOption('grid_back_stripe_opacity');

    $bars = '';
    $c = 0;
    $num_colours = count($colours);
    $rect = [
      'x' => new Number($this->pad_left),
      'width' => new Number($this->g_width),
    ];
    if($opacity != 1)
      $rect['fill-opacity'] = $opacity;
    $points = $this->getGridPointsY($this->main_y_axis);
    $first = array_shift($points);
    $last_pos = $first->position;
    foreach($points as $grid_point) {
      $cc = $colours[$c % $num_colours];
      if($cc !== null) {
        $rect['y'] = $grid_point->position;
        $rect['height'] = $last_pos - $grid_point->position;
        $rect['fill'] = new Colour($this, $cc);
        $bars .= $this->element('rect', $rect);
      }
      $last_pos = $grid_point->position;
      ++$c;
    }
    return $this->element('g', [], null, $bars);
  }

  /**
   * Returns the crosshairs code
   */
  protected function getCrossHairs()
  {
    if(!$this->getOption('crosshairs'))
      return '';

    $ch = new CrossHairs($this, $this->pad_left, $this->pad_top,
      $this->g_width, $this->g_height, $this->x_axes[$this->main_x_axis],
      $this->y_axes[$this->main_y_axis], $this->values->associativeKeys(),
      false, $this->encoding);

    if($ch->enabled())
      return $ch->getCrossHairs();
    return '';
  }

  /**
   * Draws the grid behind the bar / line graph
   */
  protected function grid()
  {
    $this->calcAxes();
    $this->calcGrid();

    // these are used quite a bit, so convert to Number now
    $left_num = new Number($this->pad_left);
    $top_num = new Number($this->pad_top);
    $width_num = new Number($this->g_width);
    $height_num = new Number($this->g_height);

    $back = $subpath = '';
    $grid_group = ['class' => 'grid'];
    $crosshairs = $this->getCrossHairs();

    // if the grid is not displayed, stop now
    if(!$this->getOption('show_grid') ||
      (!$this->getOption('show_grid_h') && !$this->getOption('show_grid_v')))
      return empty($crosshairs) ? '' :
        $this->element('g', $grid_group, null, $crosshairs);

    $back_colour = new Colour($this, $this->getOption('grid_back_colour'));
    if(!$back_colour->isNone()) {
      $rect = [
        'x' => $left_num, 'y' => $top_num,
        'width' => $width_num, 'height' => $height_num,
        'fill' => $back_colour
      ];
      if($this->getOption('grid_back_opacity') != 1)
        $rect['fill-opacity'] = $this->getOption('grid_back_opacity');
      $back = $this->element('rect', $rect);
    }
    $back .= $this->getGridStripes();

    if($this->getOption('show_grid_subdivisions')) {
      $subpath_h = new PathData();
      $subpath_v = new PathData();
      if($this->getOption('show_grid_h')) {
        $subdivs = $this->getSubDivsY($this->main_y_axis);
        foreach($subdivs as $y)
          $subpath_v->add('M', $left_num, $y->position, 'h', $width_num);
      }
      if($this->getOption('show_grid_v')){
        $subdivs = $this->getSubDivsX($this->main_x_axis);
        foreach($subdivs as $x)
          $subpath_h->add('M', $x->position, $top_num, 'v', $height_num);
      }

      if(!($subpath_h->isEmpty() && $subpath_v->isEmpty())) {
        $colour_h = $this->getOption('grid_subdivision_colour_h',
          'grid_subdivision_colour', 'grid_colour_h', 'grid_colour');
        $colour_v = $this->getOption('grid_subdivision_colour_v',
          'grid_subdivision_colour', 'grid_colour_v', 'grid_colour');
        $dash_h = $this->getOption('grid_subdivision_dash_h',
          'grid_subdivision_dash', 'grid_dash_h', 'grid_dash');
        $dash_v = $this->getOption('grid_subdivision_dash_v',
          'grid_subdivision_dash', 'grid_dash_v', 'grid_dash');
        $width_h = $this->getOption('grid_subdivision_stroke_width_h',
          'grid_subdivision_stroke_width', 'grid_stroke_width_h',
          'grid_stroke_width');
        $width_v = $this->getOption('grid_subdivision_stroke_width_v',
          'grid_subdivision_stroke_width', 'grid_stroke_width_v',
          'grid_stroke_width');

        if($dash_h == $dash_v && $colour_h == $colour_v && $width_h == $width_v) {
          $subpath_h->add($subpath_v);
          $subpath = $this->gridLines($subpath_h, $colour_h, $dash_h, $width_h);
        } else {
          $subpath = $this->gridLines($subpath_h, $colour_h, $dash_h, $width_h) .
            $this->gridLines($subpath_v, $colour_v, $dash_v, $width_v);
        }
      }
    }

    $path_v = new PathData;
    $path_h = new PathData;
    if($this->getOption('show_grid_h')) {
      $points = $this->getGridPointsY($this->main_y_axis);
      foreach($points as $y)
        $path_v->add('M', $left_num, $y->position, 'h', $width_num);
    }
    if($this->getOption('show_grid_v')) {
      $points = $this->getGridPointsX($this->main_x_axis);
      foreach($points as $x)
        $path_h->add('M', $x->position, $top_num, 'v', $height_num);
    }

    $colour_h = $this->getOption('grid_colour_h', 'grid_colour');
    $colour_v = $this->getOption('grid_colour_v', 'grid_colour');
    $dash_h = $this->getOption('grid_dash_h', 'grid_dash');
    $dash_v = $this->getOption('grid_dash_v', 'grid_dash');
    $width_h = $this->getOption('grid_stroke_width_h', 'grid_stroke_width');
    $width_v = $this->getOption('grid_stroke_width_v', 'grid_stroke_width');

    if($dash_h == $dash_v && $colour_h == $colour_v && $width_h == $width_v) {
      $path_v->add($path_h);
      $path = $this->gridLines($path_v, $colour_h, $dash_h, $width_h);
    } else {
      $path = $this->gridLines($path_h, $colour_h, $dash_h,$width_h) .
        $this->gridLines($path_v, $colour_v, $dash_v, $width_v);
    }

    return $this->element('g', $grid_group, null,
      $back . $subpath . $path . $crosshairs);
  }

  /**
   * clamps a value to the grid boundaries
   */
  protected function clampVertical($val)
  {
    return max($this->pad_top, min($this->height - $this->pad_bottom, $val));
  }

  protected function clampHorizontal($val)
  {
    return max($this->pad_left, min($this->width - $this->pad_right, $val));
  }

  /**
   * Sets the clipping path for the grid
   */
  protected function clipGrid(&$attr)
  {
    $cx = $this->getOption('grid_clip_overlap_x');
    $cy = $this->getOption('grid_clip_overlap_y');

    $clip_id = $this->gridClipPath($cx, $cy);
    $attr['clip-path'] = 'url(#' . $clip_id . ')';
  }

  /**
   * Returns the ID of the grid clipping path
   */
  public function gridClipPath($x_overlap = 0, $y_overlap = 0)
  {
    $cid = new Number($x_overlap) . '_' . new Number($y_overlap);
    if(isset($this->grid_clip_id[$cid]))
      return $this->grid_clip_id[$cid];

    $crop_top = max(0, $this->pad_top - $y_overlap);
    $crop_bottom = min($this->height, $this->height - $this->pad_bottom + $y_overlap);
    $crop_left = max(0, $this->pad_left - $x_overlap);
    $crop_right = min($this->width, $this->width - $this->pad_right + $x_overlap);
    $rect = [
      'x' => $crop_left, 'y' => $crop_top,
      'width' => $crop_right - $crop_left,
      'height' => $crop_bottom - $crop_top,
    ];
    $clip_id = $this->newID();
    $this->defs->add($this->element('clipPath', ['id' => $clip_id], null,
      $this->element('rect', $rect)));
    return ($this->grid_clip_id[$cid] = $clip_id);
  }

  /**
   * Returns the grid position for a bar or point, or NULL if not on grid
   * $item  = data item
   * $index = integer position in array
   */
  protected function gridPosition($item, $index)
  {
    $offset = $this->x_axes[$this->main_x_axis]->position($index, $item);
    $zero = -0.01; // catch values close to 0

    if($offset >= $zero && floor($offset) <= $this->grid_limit)
      return $this->pad_left + $offset;
    return null;
  }

  /**
   * Returns an X unit value as a SVG distance
   */
  public function unitsX($x, $axis_no = null)
  {
    if($axis_no === null)
      $axis_no = $this->main_x_axis;
    if(!isset($this->x_axes[$axis_no]))
      throw new \Exception('Axis x' . $axis_no . ' does not exist');
    if($this->x_axes[$axis_no] === null)
      $axis_no = $this->main_x_axis;
    $axis = $this->x_axes[$axis_no];
    return $axis->position($x);
  }

  /**
   * Returns a Y unit value as a SVG distance
   */
  public function unitsY($y, $axis_no = null)
  {
    if($axis_no === null)
      $axis_no = $this->main_y_axis;
    if(!isset($this->y_axes[$axis_no]))
      throw new \Exception('Axis y' . $axis_no . ' does not exist');
    if($this->y_axes[$axis_no] === null)
      $axis_no = $this->main_y_axis;
    $axis = $this->y_axes[$axis_no];
    return $axis->position($y);
  }

  /**
   * Returns the $x value as a grid position
   */
  public function gridX($x, $axis_no = null)
  {
    $p = $this->unitsX($x, $axis_no);
    if($p !== null)
      return $this->pad_left + $p;
    return null;
  }

  /**
   * Returns the $y value as a grid position
   */
  public function gridY($y, $axis_no = null)
  {
    $p = $this->unitsY($y, $axis_no);
    if($p === null)
      return null;
    if($axis_no === null || $this->y_axes[$axis_no] === null)
      $axis_no = $this->main_y_axis;
    $axis = $this->y_axes[$axis_no];
    return $axis->reversed() ? $this->height - $this->pad_bottom - $p :
      $this->pad_top + $p;
  }

  /**
   * Returns the location of the X axis origin
   */
  protected function originX($axis_no = null)
  {
    if($axis_no === null || $this->x_axes[$axis_no] === null)
      $axis_no = $this->main_x_axis;
    $axis = $this->x_axes[$axis_no];
    return $this->pad_left + $axis->origin();
  }

  /**
   * Returns the location of the Y axis origin
   */
  protected function originY($axis_no = null)
  {
    if($axis_no === null || $this->y_axes[$axis_no] === null)
      $axis_no = $this->main_y_axis;
    $axis = $this->y_axes[$axis_no];
    return $axis->reversed() ?
      $this->height - $this->pad_bottom - $axis->origin() :
      $this->pad_top + $axis->origin();
  }

  /**
   * Calculates the averages, storing them as guidelines
   */
  protected function calcAverages($cls = 'Goat1000\SVGGraph\Average')
  {
    $show = $this->getOption('show_average');
    if(empty($show))
      return;

    $datasets = empty($this->multi_graph) ? [0] :
      $this->multi_graph->getEnabledDatasets();

    $average = new $cls($this, $this->values, $datasets);
    $average->getGuidelines();
  }

  /**
   * Loads the guidelines from options
   */
  protected function calcGuidelines()
  {
    $this->calcAverages();
    $guidelines = $this->getOption('guideline');
    if(empty($guidelines) && $guidelines !== 0)
      return;

    $this->guidelines = new Guidelines($this, false,
      $this->values->associativeKeys(),
      $this->getOption('datetime_keys'));
  }

  public function underShapes()
  {
    $content = parent::underShapes();
    if($this->guidelines !== null)
      $content .= $this->guidelines->getBelow();
    return $content;
  }

  public function overShapes()
  {
    $content = parent::overShapes();
    if($this->guidelines !== null)
      $content .= $this->guidelines->getAbove();
    return $content;
  }
}

