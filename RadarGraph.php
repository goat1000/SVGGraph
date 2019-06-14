<?php
/**
 * Copyright (C) 2012-2019 Graham Breach
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
 * RadarGraph - a line graph that goes around in circles
 */
class RadarGraph extends LineGraph {

  protected $xc;
  protected $yc;
  protected $radius;
  protected $arad;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    // in the case of radar graphs, $label_centre means we want an axis that
    // ends at N points + 1
    $fs = [
      'label_centre' => true,
      'single_axis' => true,
    ];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  protected function draw()
  {
    $body = $this->grid() . $this->underShapes();
    $this->colourSetup($this->values->itemsCount());

    $bnum = 0;
    $cmd = 'M';
    $y_axis = $this->y_axes[$this->main_y_axis];

    $graph_line = '';
    $line_breaks = $this->getOption(['line_breaks', 0]);
    $points = [];
    $first_point = null;
    foreach($this->values[0] as $item) {
      if($line_breaks && is_null($item->value) && count($points) > 0) {
        $graph_line .= $this->drawLine(0, $points, 0, true);
        $points = [];
      } else {
        $point_pos = $this->gridPosition($item, $bnum);
        if(!is_null($item->value) && !is_null($point_pos)) {
          $val = $y_axis->position($item->value);
          $angle = $this->arad + $point_pos / $this->g_height;
          $x = $this->xc + ($val * sin($angle));
          $y = $this->yc + ($val * cos($angle));
          $points[] = [$x, $y, $item, 0, $bnum];
          if(is_null($first_point))
            $first_point = $points[0];
        }
      }
      ++$bnum;
    }

    // close graph or segment?
    if($first_point && (!$line_breaks || $first_point[4] == 0)) {
      $first_point[2] = null;
      $points[] = $first_point;
    }

    $graph_line .= $this->drawLine(0, $points, 0, true);
    $group = [];
    $this->clipGrid($group);
    if($this->semantic_classes)
      $group['class'] = 'series';

    $body .= $this->element('g', $group, null, $graph_line);
    $body .= $this->overShapes();
    $body .= $this->axes();
    $body .= $this->crossHairs();
    $body .= $this->drawMarkers();
    return $body;
  }

  /**
   * Fill from centre point
   */
  protected function fillFrom($x, $y)
  {
    return new PathData('M', $this->xc, $this->yc);
  }

  /**
   * Fill to centre point
   */
  protected function fillTo($x, $y)
  {
    return new PathData('L', $this->xc, $this->yc, 'z');
  }

  /**
   * Finds the grid position for radar graphs, returns NULL if not on graph
   */
  protected function gridPosition($item, $ikey)
  {
    $gkey = $this->values->associativeKeys() ? $ikey : $item->key;
    $axis = $this->x_axes[$this->main_x_axis];
    $offset = $axis->position($gkey);
    if($offset >= 0 && $offset < $this->g_width)
      return $this->reverse ? -$offset : $offset;
    return null;
  }

  /**
   * Returns the $x value as a grid position
   */
  public function gridX($x, $axis_no = null)
  {
    $p = $this->unitsX($x, $axis_no);
    return $p;
  }

  /**
   * Returns the $y value as a grid position
   */
  public function gridY($y, $axis_no = null)
  {
    $p = $this->unitsY($y, $axis_no);
    return $p;
  }

  /**
   * Returns a DisplayAxisRadar for the round axis
   */
  protected function getDisplayAxis($axis, $axis_no, $orientation, $type)
  {
    $var = 'main_' . $type . '_axis';
    $main = ($axis_no == $this->{$var});
    if($orientation == 'h')
      return new DisplayAxisRadar($this, $axis, $axis_no, $orientation, $type,
        $main, $this->xc, $this->yc, $this->radius);

    return new DisplayAxisRotated($this, $axis, $axis_no, $orientation, $type,
      $main, $this->arad);
  }

  /**
   * Returns the location of an axis
   */
  protected function getAxisLocation($orientation, $axis_no)
  {
    return [$this->xc, $this->yc];
  }

  /**
   * Convert X, Y in grid space to radial position
   */
  public function transformCoords($x, $y)
  {
    $angle = $x / $this->g_height;
    if($this->grid_straight) {
      // this complicates things...

      // get all the grid points, div and subdiv
      $points = array_merge($this->getGridPointsX(0), $this->getSubDivsX(0));
      $grid_angles = [];
      foreach($points as $point) {
        $grid_angles[] = $point->position / $this->radius;
      }
      sort($grid_angles);

      // find angle between (sub)divisions
      $div_angle = $grid_angles[1] - $grid_angles[0];

      // use trig to find length of Y
      $a = fmod($angle, $div_angle);
      $t = ($div_angle / 2) - $a;

      $y2 = $y * cos($div_angle / 2);
      $y = $y2 / cos($t);
    }
    $angle += $this->arad;
    $new_x = $this->xc + ($y * sin($angle));
    $new_y = $this->yc + ($y * cos($angle));

    return [$new_x, $new_y];
  }

  /**
   * Find the bounding box of the axis text for given axis lengths
   */
  protected function findAxisBBox($length_x, $length_y, $x_axes, $y_axes)
  {
    $this->xc = $length_x / 2;
    $this->yc = $length_y / 2;
    $diameter = min($length_x, $length_y);
    $length_y = $diameter / 2;
    $length_x = 2 * M_PI * $length_y;
    $this->radius = $length_y;
    foreach($x_axes as $a)
      $a->setLength($length_x);
    foreach($y_axes as $a)
      $a->setLength($length_y);

    $min_x = [$this->width];
    $min_y = [$this->height];
    $max_x = [0];
    $max_y = [0];

    $display_axis = $this->getDisplayAxis($x_axes[0], 0, 'h', 'x');
    $axis_m = $display_axis->measure();
    $min_x[] = $axis_m['x'];
    $max_x[] = $axis_m['x'] + $axis_m['width'];
    $min_y[] = $axis_m['y'];
    $max_y[] = $axis_m['y'] + $axis_m['height'];
    $display_axis = $this->getDisplayAxis($y_axes[0], 0, 'v', 'y');
    $axis_m = $display_axis->measure();
    $min_x[] = $axis_m['x'] + $this->xc;
    $max_x[] = $axis_m['x'] + $axis_m['width'] + $this->xc;
    $min_y[] = $axis_m['y'] + $this->yc;
    $max_y[] = $axis_m['y'] + $axis_m['height'] + $this->yc;

    $min_x = min($min_x);
    $min_y = min($min_y);
    $max_x = max($max_x);
    $max_y = max($max_y);

    $bbox = compact('min_x', 'min_y', 'max_x', 'max_y');
    $this->radius = null;
    return $bbox;
  }

  /**
   * Draws concentric Y grid lines
   */
  public function yGrid(&$y_points)
  {
    $path = new PathData;

    if($this->grid_straight) {
      $grid_angles = [];
      $points = array_merge($this->getGridPointsX(0), $this->getSubDivsX(0));
      foreach($points as $point) {
        $new_x = $point->position - $this->pad_left;
        $grid_angles[] = $this->arad + $new_x / $this->radius;
      }
      // put the grid angles in order
      sort($grid_angles);
      foreach($y_points as $y) {
        $y = $y->position;
        $x1 = $this->xc + $y * sin($this->arad);
        $y1 = $this->yc + $y * cos($this->arad);
        $path->add('M', $x1, $y1, 'L');
        foreach($grid_angles as $a) {
          $x1 = $this->xc + $y * sin($a);
          $y1 = $this->yc + $y * cos($a);
          $path->add($x1, $y1);
        }
        $path->add('z');
      }
    } else {
      foreach($y_points as $y) {
        $y = $y->position;
        $p1 = $this->xc - $y;
        $p2 = $this->xc + $y;
        $path->add('M', $p1, $this->yc, 'A', $y, $y, 0, 1, 1, $p2, $this->yc);
        $path->add('M', $p2, $this->yc, 'A', $y, $y, 0, 1, 1, $p1, $this->yc);
      }
    }
    return $path;
  }

  /**
   * Draws radiating X grid lines
   */
  protected function xGrid(&$x_points)
  {
    $path = new PathData;
    foreach($x_points as $x) {
      $x = $x->position - $this->pad_left;
      $angle = $this->arad + $x / $this->radius;
      $p1 = $this->radius * sin($angle);
      $p2 = $this->radius * cos($angle);
      $path->add('M', $this->xc, $this->yc, 'l', $p1, $p2);
    }
    return $path;
  }

  /**
   * Draws the grid behind the graph
   */
  protected function grid()
  {
    $this->calcAxes();
    $this->calcGrid();
    if(!$this->show_grid || (!$this->show_grid_h && !$this->show_grid_v))
      return '';

    $xc = $this->xc;
    $yc = $this->yc;
    $r = $this->radius;

    $back = $subpath = '';
    $back_colour = $this->parseColour($this->grid_back_colour, null, false,
      false, true);
    $y_points = $this->getGridPointsY(0);
    $x_points = $this->getGridPointsX(0);
    $y_subdivs = $this->getSubDivsY(0);
    $x_subdivs = $this->getSubDivsX(0);
    if(!empty($back_colour) && $back_colour != 'none') {
      // use the YGrid function to get the path
      $points = [new GridPoint($r, '', 0)];
      $bpath = [
        'd' => $this->yGrid($points),
        'fill' => $back_colour
      ];
      if($this->grid_back_opacity != 1)
        $bpath['fill-opacity'] = $this->grid_back_opacity;
      $back = $this->element('path', $bpath);
    }
    if($this->grid_back_stripe) {
      // use array of colours if available, otherwise stripe a single colour
      $colours = is_array($this->grid_back_stripe_colour) ?
        $this->grid_back_stripe_colour :
        [null, $this->grid_back_stripe_colour];
      $c = 0;
      $num_colours = count($colours);
      $num_points = count($y_points);
      while($c < $num_points - 1) {
        if(!is_null($colours[$c % $num_colours])) {
          $s_points = [$y_points[$c], $y_points[$c + 1]];
          $bpath = [
            'fill' => $this->parseColour($colours[$c % $num_colours]),
            'd' => $this->yGrid($s_points),
            'fill-rule' => 'evenodd',
          ];
          if($this->grid_back_stripe_opacity != 1)
            $bpath['fill-opacity'] = $this->grid_back_stripe_opacity;
          $back .= $this->element('path', $bpath);
        }
        ++$c;
      }
    }
    if($this->show_grid_subdivisions) {
      $subpath_h = new PathData;
      $subpath_v = new PathData;
      if($this->show_grid_h)
        $subpath_h = $this->yGrid($y_subdivs);
      if($this->show_grid_v)
        $subpath_v = $this->xGrid($x_subdivs);
      if(!($subpath_h->isEmpty() && $subpath_v->isEmpty())) {
        $colour_h = $this->getOption('grid_subdivision_colour_h',
          'grid_subdivision_colour', 'grid_colour_h', 'grid_colour');
        $colour_v = $this->getOption('grid_subdivision_colour_v',
          'grid_subdivision_colour', 'grid_colour_v', 'grid_colour');
        $dash_h = $this->getOption('grid_subdivision_dash_h',
          'grid_subdivision_dash', 'grid_dash_h', 'grid_dash');
        $dash_v = $this->getOption('grid_subdivision_dash_v',
          'grid_subdivision_dash', 'grid_dash_v', 'grid_dash');

        if($dash_h == $dash_v && $colour_h == $colour_v) {
          $subpath_h->add($subpath_v);
          $subpath = $this->gridLines($subpath_h, $colour_h,
            $dash_h, 'none');
        } else {
          $subpath = $this->gridLines($subpath_h, $colour_h, $dash_h, 'none') .
            $this->gridLines($subpath_v, $colour_v, $dash_v, 'none');
        }
      }
    }

    $path_v = new PathData;
    $path_h = new PathData;
    if($this->show_grid_h)
      $path_v = $this->yGrid($y_points);
    if($this->show_grid_v)
      $path_h = $this->xGrid($x_points);

    $colour_h = $this->getOption('grid_colour_h', 'grid_colour');
    $colour_v = $this->getOption('grid_colour_v', 'grid_colour');
    $dash_h = $this->getOption('grid_dash_h', 'grid_dash');
    $dash_v = $this->getOption('grid_dash_v', 'grid_dash');

    if($dash_h == $dash_v && $colour_h == $colour_v) {
      $path_v->add($path_h);
      $path = $this->gridLines($path_v, $colour_h, $dash_h, 'none');
    } else {
      $path = $this->gridLines($path_h, $colour_h, $dash_h, 'none') .
        $this->gridLines($path_v, $colour_v, $dash_v, 'none');
    }

    return $back . $subpath . $path;
  }

  /**
   * Sets the grid size as circumference x radius
   */
  protected function setGridDimensions()
  {
    if(is_null($this->radius)) {
      $w = $this->width - $this->pad_left - $this->pad_right;
      $h = $this->height - $this->pad_top - $this->pad_bottom;
      $this->xc = $this->pad_left + $w / 2;
      $this->yc = $this->pad_top + $h / 2;
      $this->radius = min($w, $h) / 2;
    }
    $this->g_height = $this->radius;
    $this->g_width = 2 * M_PI * $this->radius;
  }

  /**
   * Calculate the extra details for radar axes
   */
  protected function calcAxes()
  {
    $this->arad = (90 + $this->start_angle) * M_PI / 180;
    $this->axis_right = false;
    parent::calcAxes();
  }

  /**
   * Returns the grid points for a Y-axis
   */
  protected function getGridPointsY($axis)
  {
    $points = $this->y_axes[$axis]->getGridPoints(0);
    foreach($points as $k => $p)
      $points[$k]->position = -$p->position;
    return $points;
  }

  /**
   * Returns the subdivisions for a Y-axis
   */
  protected function getSubDivsY($axis)
  {
    $points = $this->y_axes[$axis]->getGridSubdivisions(
      $this->minimum_subdivision,
      $this->getOption(['minimum_units_y', $axis]), 0,
      $this->getOption(['subdivision_v', $axis]));
    foreach($points as $k => $p)
      $points[$k]->position = -$p->position;
    return $points;
  }

  /**
   * Calculates guidelines
   */
  protected function calcGuidelines($g = null)
  {
    // in the case of radar graphs, prevents junk guidelines being drawn
  }
}

