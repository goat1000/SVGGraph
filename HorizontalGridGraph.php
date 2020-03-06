<?php
/**
 * Copyright (C) 2020 Graham Breach
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
   * Returns the X and Y axis class instances as a list
   */
  protected function getAxes($ends, &$x_len, &$y_len)
  {
    // disable units for associative keys
    if($this->values->associativeKeys())
      $this->units_x = $this->units_before_x = null;

    $x_axes = [];
    $x_axis_count = $this->xAxisCount();
    for($i = 0; $i < $x_axis_count; ++$i) {

      $x_min_space = $this->getOption(['minimum_grid_spacing_h', $i],
        'minimum_grid_spacing');
      $grid_division = $this->getOption(['grid_division_h', $i]);
      if(is_numeric($grid_division)) {
        if($grid_division <= 0)
          throw new \Exception('Invalid grid division');
        // if fixed grid spacing is specified, make the min spacing 1 pixel
        $this->minimum_grid_spacing_h = $x_min_space = 1;
      }

      $max_h = $ends['v_max'][$i];
      $min_h = $ends['v_min'][$i];
      if(!is_numeric($max_h) || !is_numeric($min_h))
        throw new \Exception('Non-numeric min/max');

      $x_min_unit = $this->getOption(['minimum_units_y', $i]);
      $x_units_after = (string)$this->getOption(['units_y', $i]);
      $x_units_before = (string)$this->getOption(['units_before_y', $i]);
      $x_decimal_digits = $this->getOption(['decimal_digits_y', $i],
        'decimal_digits');
      $x_text_callback = $this->getOption(['axis_text_callback_y', $i],
        'axis_text_callback');

      if($min_h == $max_h) {
        if($x_min_unit > 0) {
          $inc = $x_min_unit;
        } else {
          $fallback = $this->getOption('axis_fallback_max');
          $inc = $fallback > 0 ? $fallback : 1;
        }
        $max_h += $inc;
      }

      if($this->getOption(['log_axis_y', $i])) {
        $x_axis = new AxisLog($x_len, $max_h, $min_h, $x_min_unit, $x_min_space,
          false, $x_units_before, $x_units_after, $x_decimal_digits,
          $this->getOption(['log_axis_y_base', $i]),
          $grid_division, $x_text_callback);
      } elseif(!is_numeric($grid_division)) {
        $x_axis = new Axis($x_len, $max_h, $min_h, $x_min_unit, $x_min_space,
          false, $x_units_before, $x_units_after, $x_decimal_digits,
          $x_text_callback, false);
      } else {
        $x_axis = new AxisFixed($x_len, $max_h, $min_h, $grid_division,
          $x_units_before, $x_units_after, $x_decimal_digits, $x_text_callback,
          false);
      }
      $x_axes[] = $x_axis;
    }

    $y_axes = [];
    $y_axis_count = $this->yAxisCount();
    $y_values = $this->multi_graph ? $this->multi_graph : $this->values;
    $label_centre = $this->getOption('label_centre');

    for($i = 0; $i < $y_axis_count; ++$i) {

      $y_min_space = $this->getOption(['minimum_grid_spacing_v', $i],
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
        $y_min_space = 1;
      } elseif(!isset($this->minimum_grid_spacing_v[$i])) {
        $this->setOption('minimum_grid_spacing_v', $y_min_space, $i);
      }

      $max_v = $ends['k_max'][$i];
      $min_v = $ends['k_min'][$i];
      if(!is_numeric($max_v) || !is_numeric($min_v))
        throw new \Exception('Non-numeric min/max');

      $y_text_callback = $this->getOption(['axis_text_callback_x', $i],
        'axis_text_callback');
      $y_decimal_digits = $this->getOption(['decimal_digits_x', $i],
        'decimal_digits');
      $y_units_after = (string)$this->getOption(['units_x', $i]);
      $y_units_before = (string)$this->getOption(['units_before_x', $i]);

      if($this->datetime_keys) {
        $y_axis = new AxisDateTime($y_len, $max_v, $min_v, $y_min_space,
          $grid_division, $this->settings);
      } elseif(!is_numeric($grid_division)) {
        $y_axis = new Axis($y_len, $max_v, $min_v, 1, $y_min_space,
          true, $y_units_before, $y_units_after, $y_decimal_digits,
          $y_text_callback, $y_values);
      } else {
        $y_axis = new AxisFixed($y_len, $max_v, $min_v, $grid_division,
          $y_units_before, $y_units_after, $y_decimal_digits, $y_text_callback,
          $y_values);
      }
      if($label_centre)
        $y_axis->bar();
      $y_axis->reverse(); // because axis starts at bottom

      $y_axes[] = $y_axis;
    }

    // set the main axis correctly
    if($this->axis_right && count($y_axes) == 1) {
      $this->main_y_axis = 1;
      array_unshift($y_axes, null);
    }
    return [$x_axes, $y_axes];
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
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'), 1,
      $this->height - $this->pad_bottom,
      $this->getOption(['subdivision_v', $axis]));
  }

  /**
   * Returns the subdivisions for an X-axis
   */
  protected function getSubDivsX($axis)
  {
    $a = $this->x_axes[$axis];
    return $a->getGridSubdivisions($this->getOption('minimum_subdivision'),
      $this->getOption(['minimum_units_y', $axis]), $this->pad_left,
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
    $offset = $this->y_axes[$this->main_y_axis]->position($index, $item);
    $zero = -0.01; // catch values close to 0

    if($offset >= $zero && floor($offset) <= $this->grid_limit)
      return $this->height - $this->pad_bottom - $offset;

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
    // no guidelines?
    $guidelines = $this->getOption('guideline');
    if(empty($guidelines) && $guidelines !== 0)
      return;

    $this->guidelines = new Guidelines($this, true,
      $this->values->associativeKeys(), $this->datetime_keys);
  }
}
