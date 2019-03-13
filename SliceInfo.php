<?php
/**
 * Copyright (C) 2019 Graham Breach
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
 * Class for details of each pie slice
 */
class SliceInfo {
  public $start_angle;
  public $end_angle;
  public $radius_x;
  public $radius_y;

  public function __construct($start, $end, $rx, $ry)
  {
    $this->start_angle = $start;
    $this->end_angle = $end;
    $this->radius_x = $rx;
    $this->radius_y = $ry;
  }

  /**
   * Calculates the middle angle of the slice
   */
  public function midAngle()
  {
    return $this->start_angle + ($this->end_angle - $this->start_angle) / 2;
  }

  /**
   * Returns the slice angle in degrees
   */
  public function degrees()
  {
    return rad2deg($this->end_angle - $this->start_angle);
  }

  /**
   * Returns the bounding box for the slice, radius from 0,0
   * @return array($x1, $y1, $x2, $y2)
   */
  public function boundingBox($reverse)
  {
    $x1 = $y1 = $x2 = $y2 = 0;
    $angle = fmod($this->end_angle - $this->start_angle, 2 * M_PI);
    $right_angle = M_PI * 0.5;

    $rx = $this->radius_x;
    $ry = $this->radius_y;
    $a1 = fmod($this->start_angle, 2 * M_PI);
    $a2 = $a1 + $angle;
    $start_sector = floor($a1 / $right_angle);
    $end_sector = floor($a2 / $right_angle);

    switch($end_sector - $start_sector) {

    case 0:
      // slice all in one sector
      $x = max(abs(cos($a1)), abs(cos($a2))) * $rx;
      $y = max(abs(sin($a1)), abs(sin($a2))) * $ry;
      switch($start_sector) {
      case 0:
        $x2 = $x;
        $y2 = $y;
        break;
      case 1:
        $x1 = -$x;
        $y2 = $y;
        break;
      case 2:
        $x1 = -$x;
        $y1 = -$y;
        break;
      case 3:
        $x2 = $x;
        $y1 = -$y;
        break;
      }
      break;

    case 1:
      // slice across two sectors
      switch($start_sector) {
      case 0:
        $x1 = cos($a2) * $rx;
        $x2 = cos($a1) * $rx;
        $y2 = $ry;
        break;
      case 1:
        $x1 = -$rx;
        $y1 = sin($a2) * $ry;
        $y2 = sin($a1) * $ry;
        break;
      case 2:
        $x1 = cos($a1) * $rx;
        $x2 = cos($a2) * $rx;
        $y1 = -$ry;
        break;
      case 3:
        $x2 = $rx;
        $y1 = sin($a1) * $ry;
        $y2 = sin($a2) * $ry;
        break;
      }
      break;

    case 2:
      // slice across three sectors
      $x1 = -$rx;
      $y1 = -$ry;
      $x2 = $rx;
      $y2 = $ry;
      switch($start_sector) {
      case 0:
        $y1 = sin($a2) * $ry;
        $x2 = cos($a1) * $rx;
        break;
      case 1:
        $x2 = cos($a2) * $rx;
        $y2 = sin($a1) * $ry;
        break;
      case 2:
        $x1 = cos($a1) * $rx;
        $y2 = sin($a2) * $ry;
        break;
      case 3:
        $x1 = cos($a2) * $rx;
        $y1 = sin($a1) * $ry;
        break;
      }
      break;

    case 3:
      // slice across four sectors
      $x = max(abs(cos($a1)), abs(cos($a2))) * $rx;
      $y = max(abs(sin($a1)), abs(sin($a2))) * $ry;
      $x1 = -$rx;
      $y1 = -$ry;
      $x2 = $rx;
      $y2 = $ry;
      switch($start_sector) {
      case 0: $x2 = $x; break;
      case 1: $y2 = $y; break;
      case 2: $x1 = -$x; break;
      case 3: $y1 = -$y; break;
      }
      break;

    case 4:
      // slice is > 270 degrees and both ends in one sector
      $x1 = -$rx;
      $y1 = -$ry;
      $x2 = $rx;
      $y2 = $ry;
      break;
    }

    if($reverse) {
      // swap Y around origin
      $y = -$y1;
      $y1 = -$y2;
      $y2 = $y;
    }
    return [$x1, $y1, $x2, $y2];
  }
}

