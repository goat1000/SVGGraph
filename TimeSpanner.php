<?php
/**
 * Copyright (C) 2022 Graham Breach
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
 * Class to make start/end times match units
 */
class TimeSpanner {

  private $start_func = 'start_days';
  private $end_func = 'end_days';
  private $start_time = 0;

  public function __construct($units)
  {
    switch($units) {
    case 'minute' :
      $this->start_func = 'start_minutes';
      $this->end_func = 'end_minutes';
      break;
    case 'hour' :
      $this->start_func = 'start_hours';
      $this->end_func = 'end_hours';
      break;
    }
  }

  /**
   * Returns the start time clamped to unit
   * $d = unix timestamp
   */
  public function start($d)
  {
    $e = new \DateTime('@' . $d);
    $this->{$this->start_func}($e);
    $this->start_time = $e->format('U');
    return $this->start_time;
  }

  /**
   * Returns the end time clamped to at least one unit after start
   * $d = unix timestamp
   */
  public function end($d)
  {
    if($d < $this->start_time)
      $d = $this->start_time;

    $e = new \DateTime('@' . $d);
    $this->{$this->end_func}($e, $d - $this->start_time);
    return $e->format('U');
  }

  public function start_minutes(\DateTime $e)
  {
    $h = (int)$e->format('H');
    $m = (int)$e->format('i');
    $e->setTime($h, $m, 0);
  }

  public function end_minutes(\DateTime $e, $diff)
  {
    if($diff < 60)
      $e->modify('+1 minute');
    $h = (int)$e->format('H');
    $m = (int)$e->format('i');
    $e->setTime($h, $m, 0);

    // prevent ending at 00:00 on next day
    if($h == 0 && $m == 0)
      $e->modify('-1 second');
  }

  public function start_hours(\DateTime $e)
  {
    $h = (int)$e->format('H');
    $e->setTime($h, 0, 0);
  }

  public function end_hours(\DateTime $e, $diff)
  {
    if($diff < 3600)
      $e->modify('+1 hour');
    $h = (int)$e->format('H');
    $e->setTime($h, 0, 0);

    // prevent ending at 00:00 on next day
    if($h == 0)
      $e->modify('-1 second');
  }

  public function start_days(\DateTime $e)
  {
    $e->setTime(0, 0, 0);
  }

  public function end_days(\DateTime $e, $diff)
  {
    $e->setTime(23, 59, 59);
  }

}
