<?php
/**
 * Copyright (C) 2016 Graham Breach
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

require_once 'SVGGraphAxis.php';

/**
 * Class for calculating date/time axis measurements
 */
class AxisDateTime extends Axis {

  protected $grid_space;
  protected $grid_split = 0;
  protected $start = 0;
  protected $end = 0;
  protected $label_callback;
  protected $axis_text_format = 'Y-m-d';
  protected $div = NULL;
  protected $division = NULL;

  protected static $week_start = 'monday';
  protected static $weekdays = array(
    'sunday' => 0,
    'monday' => 1,
    'tuesday' => 2,
    'wednesday' => 3,
    'thursday' => 4,
    'friday' => 5,
    'saturday' => 6
  );

  /**
   * The list of possible divisions. Fields are:
   * 0 - division unit
   * 1 - number of units in duration
   * 2 - array of division indices for subdivision
   */
  protected static $divisions = array(
    // the indices are numbered for clarity
    0  =>  array('second', 1),
    1  =>  array('second', 2, array(0)),
    2  =>  array('second', 5, array(0)),
    3  =>  array('second', 10, array(0, 1, 2)),
    4  =>  array('second', 15, array(0, 2)),
    5  =>  array('second', 20, array(0, 1, 2, 3)),
    6  =>  array('second', 30, array(0, 1, 2, 3, 4)),
    7  =>  array('minute', 1, array(3, 4, 5, 6)),
    8  =>  array('minute', 2, array(6, 7)),
    9  =>  array('minute', 5, array(7)),
    10 =>  array('minute', 10, array(7, 8, 9)),
    11 =>  array('minute', 15, array(7, 9)),
    12 =>  array('minute', 20, array(7, 8, 9, 10)),
    13 =>  array('minute', 30, array(8, 9, 10, 11)),
    14 =>  array('hour', 1, array(9, 10, 11, 12, 13)),
    15 =>  array('hour', 2, array(11, 13, 14)),
    16 =>  array('hour', 3, array(13, 14)),
    17 =>  array('hour', 4, array(13, 14, 15)),
    18 =>  array('hour', 6, array(14, 15, 16)),
    19 =>  array('hour', 8, array(14, 15, 17)),
    20 =>  array('hour', 12, array(14, 15, 16, 17, 18, 19)),
    21 =>  array('day', 1, array(14, 18, 20)),
    22 =>  array('day', 7, array(21)),
    23 =>  array('day', 14, array(21, 22)),
    24 =>  array('month', 1, array(21)),
    25 =>  array('month', 2, array(21, 24)),
    26 =>  array('month', 3, array(24)),
    27 =>  array('month', 6, array(24, 25, 26)),
    28 =>  array('year', 1, array(24, 25, 26, 27)),
    29 =>  array('year', 2, array(27, 28)),
    30 =>  array('year', 5, array(28)),
    31 =>  array('year', 10, array(28, 29, 30)),
    32 =>  array('year', 20, array(28, 29, 30, 31)),
    33 =>  array('year', 50, array(30, 31)),
    34 =>  array('year', 100, array(31, 32, 33)),
    35 =>  array('year', 500, array(34)),
    36 =>  array('year', 1000, array(34, 35)),
    37 =>  array('year', 10000),
    38 =>  array('year', 100000),
    39 =>  array('year', 1000000),
  );

  /**
   * The size of each unit in seconds
   */
  protected static $unit_sizes = array(
    'second' => 1,
    'minute' => 60,
    'hour' => 3600,
    'day' => 86400,
    'month' => 2629800, // avg year / 12
    'year' => 31557600  // avg year = 365.25 days (ignoring leap centuries)
  );

  /**
   * Default format strings for each unit size
   */
  protected static $formats = array(
    'second' => 'Y-m-d H:i:s',
    'minute' => 'Y-m-d H:i',
    'hour' => 'Y-m-d H:i',
    'day' => 'Y-m-d',
    'month' => 'Y-m',
    'year' => 'Y'
  );

  public function __construct($length, $max_val, $min_val, $min_space,
    $fixed_division, $options)
  {
    if($max_val < $min_val)
      throw new Exception('Zero length axis (min >= max)');
    $this->length = $length;
    // if $min_space > $length, use $length instead
    $this->min_space = $min_space = min($length, $min_space);
    $this->uneven = false;

    // convert actual min/max to start/end times
    $start_date = new DateTime('@' . $min_val);
    $end_date = new DateTime('@' . $max_val);

    if(!empty($fixed_division)) {
      list($units, $count) = AxisDateTime::ParseFixedDivisions($fixed_division,
        $min_val, $max_val, $length);
      $start = AxisDateTime::StartTime($start_date, $units, $count);
      $end = AxisDateTime::EndTime($end_date, $units, $count, $start);

      $this->start = $start->format('U');
      $this->end = $end->format('U');
      $this->duration = ($this->end - $this->start) + 1;
      $this->grid_units = $units;
      $this->grid_unit_count = $count;

      // set the division number (if it is a standard division)
      $this->division = 0;
      foreach(AxisDateTime::$divisions as $key => $div) {
        if($div[0] == $units && $div[1] == $count)
          $this->division = $key;
      }

    } else {
      // set the week start day before finding divisions
      if(isset($options['datetime_week_start']) &&
        isset(AxisDateTime::$weekdays[$options['datetime_week_start']]))
        AxisDateTime::$week_start = $options['datetime_week_start'];

      // find a sensible division
      $div = AxisDateTime::FindDivision($start_date, $end_date, $length,
        $min_space);
      $this->div = $div;
      $this->start = $div[0]->format('U');
      $this->end = $div[1]->format('U');
      $this->duration = ($this->end - $this->start) + 1;

      $this->division = $div[2];
      $this->grid_units = AxisDateTime::$divisions[$this->division][0];
      $this->grid_unit_count = AxisDateTime::$divisions[$this->division][1];
    }
    $this->label_callback = array($this, 'DateText');

    // get the axis text format from the options, or use default
    $text_format = NULL;
    if(isset($options['datetime_text_format'])) {
      if(is_array($options['datetime_text_format'])) {
        if(isset($options['datetime_text_format'][$this->grid_units])) {
          $text_format = $options['datetime_text_format'][$this->grid_units];
        }
      } elseif(!empty($options['datetime_text_format'])) {
        $text_format = $options['datetime_text_format'];
      }
    }

    $this->axis_text_format = is_null($text_format) ?
      AxisDateTime::$formats[$this->grid_units] : $text_format;
  }

  /**
   * Finds the best division for the given start and end date/time
   * @param DateTime $start
   * @param DateTime $end
   * @param number $length
   * @param number $min_space
   * @param number $subdivision
   * Returns array($start, $end, $div_index, $div_count) or NULL if there is no
   *  subdivision possible
   */
  private static function FindDivision($start, $end, $length, $min_space,
    $subdivision = false)
  {
    $max_divisions = floor($length / $min_space);
    $duration_s = $end->format('U') - $start->format('U');
    $avg_duration = (int)ceil($duration_s / $max_divisions);

    $choice = NULL;
    $divisions = 1;
    $subdivide = false;
    if($subdivision === false) {
      $d_list = array_keys(AxisDateTime::$divisions);
    } else {
      // give up now if this can't be subdivided
      if(!isset(AxisDateTime::$divisions[$subdivision][2]))
        return NULL;
      $d_list = AxisDateTime::$divisions[$subdivision][2];
      $subdivide = true;
    }

    foreach($d_list as $i) {
      $d = AxisDateTime::$divisions[$i];
      $div_duration = $d[1] * AxisDateTime::$unit_sizes[$d[0]];

      if($div_duration >= $avg_duration) {
        $divisions = (int)floor($duration_s / $div_duration);
        $unit = $d[0];
        $nunits = $d[1];

        // get the updated start and end times to fit with the spacing
        $new_start = AxisDateTime::StartTime($start, $unit, $nunits);
        $new_end = AxisDateTime::EndTime($end, $unit, $nunits, $new_start);
        $new_duration = $new_end->format('U') - $new_start->format('U');
        $new_divisions = (int)floor($new_duration / $div_duration);
        $new_avg_duration = (int)ceil($new_duration / $max_divisions);

        if($div_duration >= $new_avg_duration) {
          $choice = $d;
          break;
        }
      }
    }
    if(is_null($choice)) {
      if($subdivide)
        return NULL;
      throw new Exception('Unable to find divisions for DateTime axis');
    }

    return array($new_start, $new_end, $i, $divisions);
  }

  /**
   * Returns the start of the current $n $units of $time
   */
  private static function StartTime($time, $unit, $n)
  {
    $formats = array(
      'year' => '00:00:00 January 1',
      'month' => '00:00:00 first day of',
      'day' => '00:00:00',
    );
    $datetime = clone $time;
    if($n == 1 && isset($formats[$unit])) {
      $datetime->modify($formats[$unit]);

    } else {
      switch($unit) {
      case 'year':
        $y = $time->format('Y');
        $y -= $y % $n;
        $datetime->setDate($y, 1, 1);
        break;

      case 'month':
        $datetime->modify($formats['month']);
        break;

      case 'day':
        $day = $datetime->format('w'); // 0-6, Sun-Sat
        $dow = AxisDateTime::$weekdays[AxisDateTime::$week_start];

        // always start on the right weekday
        if($day == $dow) {
          $datetime->modify('00:00:00');
        } else {
          $datetime->modify('00:00:00 last ' . AxisDateTime::$week_start);
        }
        break;

      case 'hour':
        $h = $datetime->format('H');
        if($n > 1)
          $h = $h - ($h % $n);
        $newtime = sprintf('%02d:00:00', $h);
        $datetime->modify($newtime);
        break;

      case 'minute':
        $m = $datetime->format('i');
        if($n > 1)
          $m = $m - ($m % $n);
        $newtime = $datetime->format(sprintf('H:%02d:00', $m));
        $datetime->modify($newtime);
        break;

      case 'second':
        $s = $datetime->format('s');
        if($n > 1)
          $s = $s - ($s % $n);
        $newtime = $datetime->format(sprintf('H:i:%02d', $s));
        $datetime->modify($newtime);
        break;
      }
    }
    return $datetime;
  }

  /**
   * Returns the end of the current $n $units of $time, started at $start
   */
  private static function EndTime($time, $unit, $n, $start)
  {
    $formats = array(
      'year' => '23:59:59 December 31',
      'month' => '23:59:59 last day of',
      'day' => '23:59:59',
    );
    $datetime = clone $time;
    if($n == 1 && isset($formats[$unit])) {
      $datetime->modify($formats[$unit]);

    } else {
      switch($unit) {
      case 'year':
        $y = $time->format('Y');
        $new_y = $y - ($y % $n) + $n - 1;
        $datetime->modify("$new_y-12-31 23:59:59");
        break;

      case 'month':
        $datetime->modify('00:00:00 first day of');
        $diff = $datetime->diff($start);
        $months = ($diff->y * 12) + $diff->m;
        $new_months = $months - ($months % $n) + $n - 1;
        $datetime = clone $start;
        $datetime->modify("+{$new_months} month 23:59:59 last day of");
        break;

      case 'day':
        $datetime->modify('00:00:00');
        $diff = $datetime->diff($start);
        $days = $diff->days - ($diff->days % $n) + $n - 1;
        $datetime = clone $start;
        $datetime->modify("+{$days} day 23:59:59");
        break;

      case 'hour':
        if($n > 1) {
          $diff = $datetime->diff($start);
          $hours = ($diff->days * 24) + $diff->h;
          $hours = $hours - ($hours % $n) + $n - 1;
          $datetime = clone $start;
          $datetime->modify("+{$hours} hour 59 minute 59 second");
        } else {
          $h = $datetime->format('H');
          $newtime = sprintf('%02d:59:59', $h);
          $datetime->modify($newtime);
        }
        break;

      case 'minute':
        if($n > 1) {
          $diff = $datetime->diff($start);
          $minutes = ((($diff->days * 24) + $diff->h) * 60) + $diff->i;
          $minutes = $minutes - ($minutes % $n) + $n - 1;
          $datetime = clone $start;
          $datetime->modify("+{$minutes} minute 59 second");
        } else {
          $m = $datetime->format('i');
          $newtime = $datetime->format(sprintf('H:%02d:59', $m));
          $datetime->modify($newtime);
        }
        break;

      case 'second':
        if($n > 1) {
          $diff = $datetime->diff($start);
          $seconds = ($diff->days * 86400) + ($diff->h * 3600) +
            ($diff->i * 60) + $diff->s;
          $seconds = $seconds - ($seconds % $n) + $n - 1;
          $datetime = clone $start;
          $datetime->modify("+{$seconds} second");
        } else {
          $s = $datetime->format('s');
          $newtime = $datetime->format(sprintf('H:i:%02d', $s));
          $datetime->modify($newtime);
        }
        break;
      }
    }
    return $datetime;
  }

  /**
   * Returns the position of a value on the axis
   */
  public function Position($index, $item = NULL)
  {
    $value = is_null($item) ? $index : $item->key;
    return $this->length * ($value - $this->start) / $this->duration;
  }

  /**
   * Returns the value at a position on the axis
   */
  public function Value($position)
  {
    return $this->start + $position * $this->duration / $this->length;
  }

  /**
   * Returns the position of the origin
   */
  public function Origin()
  {
    // time started before whatever date the graph starts with
    return 0;
  }

  /**
   * Returns the unit size
   */
  public function Unit()
  {
    $u = AxisDateTime::$unit_sizes[$this->grid_units];
    $w = $this->length * $u / $this->duration;
    return max(1, $w);
  }

  /**
   * Not actually 0, but the position of the axis
   */
  public function Zero()
  {
    return 0;
  }

  /**
   * Returns the grid points as an array of GridPoints
   */
  public function GetGridPoints($start)
  {
    $c = $pos = 0;
    $dlength = $this->length + 1; // allow 1 pixel overflow

    $units = $this->grid_units;
    $unit_count = $this->grid_unit_count;
    $div = $this->div;
    $value = $this->start;

    // prevent too many grid points if something goes wrong
    $limit = 1000;

    $points = array();
    while(floor($pos) < $dlength && ++$c < $limit) {

      $text = $this->GetText($value);
      $position = $start + ($pos * $this->direction);
      $points[] = new GridPoint($position, $text, $value);

      $datetime = new DateTime('@' . $this->start);
      $offset = $c * $unit_count;
      $datetime->modify("+{$offset} {$units}");
      $value = $datetime->format('U');
      $pos = $this->Position($value);
    }

    return $points;
  }

  /**
   * Returns the grid subdivision points as an array
   */
  public function GetGridSubdivisions($min_space, $min_unit, $start, $fixed)
  {
    $subdivs = array();
    if(!empty($fixed)) {
      list($units, $unit_count) = AxisDateTime::ParseFixedDivisions($fixed,
        $this->start, $this->end, $this->length);

    } else {
      // if the main division is the lowest level, there is no subdivision
      if($this->division == 0)
        return $subdivs;

      $start_date = new DateTime('@' . $this->start);
      $end_date = new DateTime('@' . $this->end);

      $div = AxisDateTime::FindDivision($start_date, $end_date,
        $this->length, $min_space, $this->division);

      // if no divisions found, stop now
      if(is_null($div))
        return $subdivs;
      $division = $div[2];

      $units = AxisDateTime::$divisions[$division][0];
      $unit_count = AxisDateTime::$divisions[$division][1];
    }
    $value = $this->start;

    // get the main divisions, turn them into a map of where not to put a
    // subdivision
    $main_divisions = $this->GetGridPoints($start);
    $not_here = array();
    foreach($main_divisions as $d) {
      $not_here[floor($d->position)] = $d->value;
    }

    // prevent too many grid points if something goes wrong
    $limit = 1000;

    $c = $pos = 0;
    $dlength = $this->length + 1; // allow 1 pixel overflow
    $text = '';
    while(floor($pos) < $dlength && ++$c < $limit) {

      $position = $start + ($pos * $this->direction);
      if(!isset($not_here[floor($position)]) &&
        !isset($not_here[ceil($position)]))
        $subdivs[] = new GridPoint($position, $text, $value);

      $datetime = new DateTime('@' . $this->start);
      $offset = $c * $unit_count;
      $datetime->modify("+{$offset} {$units}");
      $value = $datetime->format('U');
      $pos = $this->Position($value);
    }

    return $subdivs;
  }

  /**
   * Converts a fixed division option to a unit size and count.
   * $start_time and $end_time are unix timestamps
   * Returns array($unit, $count)
   */
  private static function ParseFixedDivisions($fixed_opt, $start_time,
    $end_time, $axis_length)
  {
    if(strpos($fixed_opt, ' ') !== FALSE) {
      // number and unit
      list($unit_count, $units) = explode(' ', $fixed_opt);

    } elseif(is_numeric($fixed_opt)) {
      // number without units
      $unit_count = $fixed_opt * 1;
      // make a guess at the units to use
      $min_interval = ($end_time - $start_time) / $axis_length;
      foreach(AxisDateTime::$unit_sizes as $unit => $size) {
        if($size > $min_interval)
          break;
      }
      $units = $unit;

    } else {
      // unit without number
      $unit_count = 1;
      $units = $fixed_opt;
    }

    $units = rtrim($units, 's');
    if(!isset(AxisDateTime::$unit_sizes[$units]))
      throw new Exception("Unrecognized datetime units [{$units}]");
    if(!is_numeric($unit_count) || $unit_count < 1)
      $unit_count = 1;

    return array($units, $unit_count);
  }

  /**
   * Formats the axis text
   */
  public function DateText($f)
  {
    $dt = new DateTime('@' . $f);
    return $dt->format($this->axis_text_format);
  }
}

