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

/**
 * Class for calculating a best-fit line
 */
class BestFitLine {

  protected $graph;
  protected $points;
  protected $line;
  protected $projection;

  public function __construct(&$graph, $points)
  {
    $this->graph =& $graph;
    $this->line = new PathData;
    $this->projection = new PathData;
    $this->points = $points;
  }

  /**
   * Calculates the line and projection
   */
  public function calculate(BoundingBox $area, $limit_start, $limit_end,
    $project_start, $project_end)
  {
    // can't draw a line through fewer than 2 points
    if(count($this->points) < 2)
      return false;

    $sum_x = $sum_y = $sum_x2 = $sum_xy = 0;
    foreach($this->points as $p) {
      $sum_x += $p->x;
      $sum_y += $p->y;
      $sum_x2 += pow($p->x, 2);
      $sum_xy += $p->x * $p->y;
    }

    $mean_x = $sum_x / count($this->points);
    $mean_y = $sum_y / count($this->points);

    if($sum_x2 == $sum_x * $mean_x) {
      // vertical line
      $slope = null;
      $y_int = $mean_x;
    } else {
      $slope = ($sum_xy - $sum_x * $mean_y) / ($sum_x2 - $sum_x * $mean_x);
      $y_int = $mean_y - $slope * $mean_x;
    }
    $this->buildPaths($slope, $y_int, $area, $limit_start, $limit_end,
      $project_start, $project_end);
  }

  /**
   * Builds the line and projection paths.
   * For vertical lines, $slope = null and $y_int = $x
   */
  protected function buildPaths($slope, $y_int, $area, $limit_start, $limit_end,
    $project_start, $project_end)
  {
    // initialize min and max points of line
    $x_min = $limit_start === null ? 0 : max($limit_start, 0);
    $x_max = $limit_end === null ? $area->width() : min($limit_end, $area->width());
    $y_min = 0;
    $y_max = $area->height();
    $line = new PathData;
    $projection = new PathData;

    if($slope === null) {
      // line is vertical!
      $coords = [
        'x1' => $y_int, 'x2' => $y_int,
        'y1' => $y_min, 'y2' => $y_max
      ];
    } else {
      $coords = $this->boxLine($x_min, $x_max, $y_min, $y_max, $slope, $y_int);

      if($project_end) {
        $pcoords = $this->boxLine($coords['x2'], $area->width(), $y_min, $y_max,
          $slope, $y_int);
        if($pcoords !== null) {
          $x1 = $pcoords['x1'] + $area->x1;
          $x2 = $pcoords['x2'] + $area->x1;
          $y1 = $area->y2 - $pcoords['y1'];
          $y2 = $area->y2 - $pcoords['y2'];
          $projection->add('M', $x1, $y1, 'L', $x2, $y2);
        }
      }
      if($project_start) {
        $pcoords = $this->boxLine(0, $coords['x1'], $y_min, $y_max,
          $slope, $y_int);
        if($pcoords !== null) {
          $x1 = $pcoords['x1'] + $area->x1;
          $x2 = $pcoords['x2'] + $area->x1;
          $y1 = $area->y2 - $pcoords['y1'];
          $y2 = $area->y2 - $pcoords['y2'];
          $projection->add('M', $x1, $y1, 'L', $x2, $y2);
        }
      }
    }
    $x1 = $coords['x1'] + $area->x1;
    $x2 = $coords['x2'] + $area->x1;
    $y1 = $area->y2 - $coords['y1'];
    $y2 = $area->y2 - $coords['y2'];
    $line->add('M', $x1, $y1, 'L', $x2, $y2);

    $this->projection = $projection;
    $this->line = $line;
    return true;
  }

  /**
   * Returns the best-fit line as PathData
   */
  public function getLine()
  {
    return $this->line;
  }

  /**
   * Returns the projection line(s) as PathData
   */
  public function getProjection()
  {
    return $this->projection;
  }

  /**
   * Returns the coordinates of a line that passes through a box
   */
  public static function boxLine($x_min, $x_max, $y_min, $y_max, $slope, $y_int)
  {
    $x1 = $x_min;
    $y1 = $slope * $x1 + $y_int;
    $x2 = $x_max;
    $y2 = $slope * $x2 + $y_int;

    if($slope != 0) {
      if($y1 < 0) {
        $x1 = -$y_int / $slope;
        $y1 = $y_min;
      } elseif($y1 > $y_max) {
        $x1 = ($y_max - $y_int) / $slope;
        $y1 = $y_max;
      }

      if($y2 < 0) {
        $x2 = - $y_int / $slope;
        $y2 = $y_min;
      } elseif($y2 > $y_max) {
        $x2 = ($y_max - $y_int) / $slope;
        $y2 = $y_max;
      }
    }
    if($x1 == $x2 && $y1 == $y2)
      return null;
    return compact('x1','y1','x2','y2');
  }
}

