<?php
/**
 * Copyright (C) 2020-2022 Graham Breach
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

abstract class HorizontalGridGraph extends GridGraph {

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    // convert _x and _y labels to _v and _h
    if(!empty($settings['label_x']) && empty($settings['label_v']))
      $settings['label_v'] = $settings['label_x'];
    if(!empty($settings['label_y']) && empty($settings['label_h']))
      $settings['label_h'] = $settings['label_y'];

    // unset these so GridGraph doesn't use them
    unset($settings['label_x'], $settings['label_y']);

    parent::__construct($w, $h, $settings, $fixed_settings);
  }

  /**
   * Swap the axis returned
   */
  protected function getDisplayAxis($axis, $axis_no, $orientation, $type)
  {
    return parent::getDisplayAxis($axis, $axis_no, $orientation,
      $type === 'x' ? 'y' : 'x');
  }

  /**
   * Returns the factory for an X-axis
   */
  protected function getXAxisFactory()
  {
    return new AxisFactory(false, $this->settings, false, false, false);
  }

  /**
   * Returns the factory for a Y-axis
   */
  protected function getYAxisFactory()
  {
    $y_bar = $this->getOption('label_centre');
    return new AxisFactory($this->getOption('datetime_keys'), $this->settings,
      true, $y_bar, true);
  }

  /**
   * Creates and returns an X-axis
   */
  protected function createXAxis($factory, $length, $ends, $i, $min_space, $grid_division)
  {
    $max_h = $ends['v_max'][$i];
    $min_h = $ends['v_min'][$i];
    if(!is_numeric($max_h) || !is_numeric($min_h))
      throw new \Exception('Non-numeric min/max');

    $min_unit = $this->getOption(['minimum_units_y', $i]);
    $units_after = (string)$this->getOption(['units_y', $i]);
    $units_before = (string)$this->getOption(['units_before_y', $i]);
    $decimal_digits = $this->getOption(['decimal_digits_y', $i],
      'decimal_digits');
    $text_callback = $this->getOption(['axis_text_callback_y', $i],
      'axis_text_callback');
    $log = $this->getOption(['log_axis_y', $i]);
    $log_base = $this->getOption(['log_axis_y_base', $i]);
    $ticks = $this->getOption(['axis_ticks_y', $i]);
    $values = $levels = null;

    if($min_h == $max_h) {
      if($min_unit > 0) {
        $inc = $min_unit;
      } else {
        $fallback = $this->getOption('axis_fallback_max');
        $inc = $fallback > 0 ? $fallback : 1;
      }
      $max_h += $inc;
    }

    $x_axis = $factory->get($length, $min_h, $max_h, $min_unit,
      $min_space, $grid_division, $units_before, $units_after,
      $decimal_digits, $text_callback, $values, $log, $log_base, $levels, $ticks);
    $x_axis->setTightness($this->getOption(['axis_tightness_y', $i]));
    return $x_axis;
  }

  /**
   * Creates and returns a Y-axis
   */
  protected function createYAxis($factory, $length, $ends, $i, $min_space, $grid_division)
  {
    $max_v = $ends['k_max'][$i];
    $min_v = $ends['k_min'][$i];
    if(!is_numeric($max_v) || !is_numeric($min_v))
      throw new \Exception('Non-numeric min/max');

    $min_unit = 1;
    $text_callback = $this->getOption(['axis_text_callback_x', $i],
      'axis_text_callback');
    $decimal_digits = $this->getOption(['decimal_digits_x', $i],
      'decimal_digits');
    $units_after = (string)$this->getOption(['units_x', $i]);
    $units_before = (string)$this->getOption(['units_before_x', $i]);
    $values = $this->multi_graph ? $this->multi_graph : $this->values;
    $levels = $this->getOption(['axis_levels_h', $i]);
    $log = $this->getOption(['log_axis_x', $i]);
    $log_base = $this->getOption(['log_axis_x_base', $i]);
    $ticks = $this->getOption('axis_ticks_x');

    return $factory->get($length, $min_v, $max_v, $min_unit,
      $min_space, $grid_division, $units_before, $units_after,
      $decimal_digits, $text_callback, $values, $log, $log_base, $levels, $ticks);
  }

  /**
   * Calculates the position of grid lines
   */
  protected function calcGrid()
  {
    parent::calcGrid();

    $this->grid_limit = 0.01 + $this->g_height;
    if($this->getOption('label_centre')) {
      $y_axis = $this->y_axes[$this->main_y_axis];
      $this->grid_limit -= $y_axis->unit() / 2;
    }
  }

  /**
   * Returns the subdivisions for a Y-axis
   */
  protected function getSubDivsY($axis)
  {
    $a = $this->y_axes[$axis];
    $offset = $a->reversed() ? $this->height - $this->pad_bottom : $this->pad_top;
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'), 1,
      $offset, $this->getOption(['subdivision_v', $axis]));
  }

  /**
   * Returns the subdivisions for an X-axis
   */
  protected function getSubDivsX($axis)
  {
    $a = $this->x_axes[$axis];
    $offset = $a->reversed() ? $this->width - $this->pad_right : $this->pad_left;
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'),
      $this->getOption(['minimum_units_y', $axis]), $offset,
      $this->getOption(['subdivision_h', $axis]));
  }

  /**
   * Returns fixed min and max option for an axis
   */
  protected function getFixedAxisOptions($axis, $index)
  {
    $a = $axis == 'y' ? 'h' : 'v';
    $min = $this->getOption(['axis_min_' . $a, $index]);
    $max = $this->getOption(['axis_max_' . $a, $index]);
    return [$min, $max];
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
      true, $this->encoding);

    if($ch->enabled())
      return $ch->getCrossHairs();
    return '';
  }

  /**
   * Returns the grid position for a bar or point, or NULL if not on grid
   * $item  = data item
   * $index = integer position in array
   */
  protected function gridPosition($item, $index)
  {
    $axis = $this->y_axes[$this->main_y_axis];
    $offset = $axis->position($index, $item);
    $zero = -0.01; // catch values close to 0

    if($offset >= $zero && floor($offset) <= $this->grid_limit)
      return $axis->reversed() ? $this->height - $this->pad_bottom - $offset :
        $this->pad_top + $offset;

    return null;
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
      'y' => new Number($this->pad_top),
      'height' => new Number($this->g_height),
    ];
    if($opacity != 1)
      $rect['fill-opacity'] = $opacity;
    $points = $this->getGridPointsX($this->main_x_axis);
    $first = array_shift($points);
    $last_pos = $first->position;
    foreach($points as $grid_point) {
      $cc = $colours[$c % $num_colours];
      if($cc !== null) {
        $rect['x'] = $last_pos;
        $rect['width'] = $grid_point->position - $last_pos;
        $rect['fill'] = new Colour($this, $cc);
        $bars .= $this->element('rect', $rect);
      }
      $last_pos = $grid_point->position;
      ++$c;
    }
    return $this->element('g', [], null, $bars);
  }

  /**
   * Converts guideline options to more useful member variables
   */
  protected function calcGuidelines()
  {
    $this->calcAverages();
    $guidelines = $this->getOption('guideline');
    if(empty($guidelines) && $guidelines !== 0)
      return;

    $this->guidelines = new Guidelines($this, true,
      $this->values->associativeKeys(), $this->getOption('datetime_keys'));
  }
}
