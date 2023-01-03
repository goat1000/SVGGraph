<?php
/**
 * Copyright (C) 2010-2022 Graham Breach
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
 * Abstract base class for graphs which use markers
 */
abstract class PointGraph extends GridGraph {

  protected $markers = [];
  protected $marker_ids = [];
  protected $marker_link_ids = [];
  protected $marker_types = [];

  private $x_offset = null;

  public function __construct($width, $height, array $settings, array $fixed_settings = [])
  {
    $fs = [];
    if(isset($settings['block_position_markers']) && $settings['block_position_markers'])
      $fs['label_centre'] = true;
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($width, $height, $settings, $fs);
  }

  /**
   * Adds a marker to the list
   */
  public function addMarker($x, $y, $item, $extra = null, $set = 0,
    $legend = true)
  {
    $m = new Marker($x, $y, $item, $extra);
    if($this->specialMarker($set, $item))
      $m->id = $this->createSingleMarker($set, $item);

    $this->markers[$set][] = $m;
    $index = count($this->markers[$set]) - 1;

    if($legend) {
      $legend_info = ['dataset' => $set, 'index' => $index];
      $this->setLegendEntry($set, $index, $item, $legend_info);
    }
  }

  /**
   * Draws (linked) markers on the graph
   */
  public function drawMarkers()
  {
    if($this->getOption('marker_size') == 0 || count($this->markers) == 0)
      return '';

    $this->createMarkers();

    $markers = '';
    foreach($this->markers as $set => $data) {
      if($this->marker_ids[$set] && count($data))
        $markers .= $this->drawMarkerSet($set, $data);
    }

    $group = [];
    if($this->getOption('semantic_classes'))
      $group['class'] = 'series';
    $shadow_id = $this->defs->getShadow();
    if($shadow_id !== null)
      $group['filter'] = 'url(#' . $shadow_id . ')';
    if(!empty($group))
      $markers = $this->element('g', $group, null, $markers);
    return $markers;
  }

  /**
   * Draws a single set of markers
   */
  protected function drawMarkerSet($set, &$marker_data)
  {
    $markers = '';
    foreach($marker_data as $m)
      $markers .= $this->getMarker($m, $set);
    return $markers;
  }


  /**
   * Returns a marker element
   */
  protected function getMarker($marker, $set)
  {
    $id = isset($marker->id) ? $marker->id : $this->marker_ids[$set];
    $use = ['x' => $marker->x, 'y' => $marker->y];

    if(is_array($marker->extra))
      $use = array_merge($marker->extra, $use);
    if($this->getOption('semantic_classes'))
      $use['class'] = 'series' . $set;
    if($this->getOption('show_tooltips'))
      $this->setTooltip($use, $marker->item, $set, $marker->key, $marker->value);
    if($this->getOption('show_context_menu'))
      $this->setContextMenu($use, $set, $marker->item);

    if($this->getLinkURL($marker->item, $marker->key)) {
      $id = $this->marker_link_ids[$id];
      $element = $this->getLink($marker->item, $marker->key,
        $this->defs->useSymbol($id, $use));
    } else {
      $element = $this->defs->useSymbol($id, $use);
    }

    return $element;
  }

  /**
   * Return a centred marker for the given set
   */
  public function drawLegendEntry($x, $y, $w, $h, $entry)
  {
    if(!isset($entry->style['dataset']))
      return '';

    $dataset = $entry->style['dataset'];
    $index = $entry->style['index'];
    $marker = $this->markers[$dataset][$index];
    if(isset($marker->id))
      $id = $marker->id;
    elseif(isset($this->marker_ids[$dataset]))
      $id = $this->marker_ids[$dataset];
    else
      return ''; // no marker!

    // if the standard marker is unused, must be a link marker
    if(!$this->defs->symbolUseCount($id))
      $id = $this->marker_link_ids[$id];

    // use data stored with legend to look up marker
    $m = ['x' => $x + $w/2, 'y' => $y + $h/2];
    return $this->defs->useSymbol($id, $m);
  }

  /**
   * Creates a single marker element and its link version
   */
  protected function createMarker($type, $size, $fill, $stroke_width,
    $stroke_colour, $opacity, $angle)
  {
    $m_key = md5(implode('|', func_get_args()));
    if(isset($this->marker_types[$m_key]))
      return $this->marker_types[$m_key];

    $markers = new Markers($this);
    $extra = ['cursor' => 'crosshair'];
    $id = $markers->create($type, $size, $fill, $stroke_width,
      $stroke_colour, $opacity, $angle, $extra);

    // add link version
    $link_id = $markers->create($type, $size, $fill, $stroke_width,
      $stroke_colour, $opacity, $angle);
    $this->marker_link_ids[$id] = $link_id;

    // save this marker style for reuse
    $this->marker_types[$m_key] = $id;
    return $id;
  }

  /**
   * Returns true if a marker is different to others in its set
   */
  protected function specialMarker($set, &$item)
  {
    $null_item = null;
    if($this->getItemOption('marker_colour', $set, $item, 'colour') !=
      $this->getItemOption('marker_colour', $set, $null_item))
      return true;

    $vlist = ['marker_type', 'marker_size', 'marker_stroke_width',
      'marker_stroke_colour', 'marker_angle', 'marker_opacity'];
    foreach($vlist as $value)
      if($this->getItemOption($value, $set, $item) !=
        $this->getItemOption($value, $set, $null_item))
        return true;
    return false;
  }

  /**
   * Creates a single marker for the data set
   */
  protected function createSingleMarker($set, &$item = null)
  {
    $type = $this->getItemOption('marker_type', $set, $item);
    $size = $this->getItemOption('marker_size', $set, $item);
    $angle = $this->getItemOption('marker_angle', $set, $item);
    $opacity = $this->getItemOption('marker_opacity', $set, $item);

    // support gradients/patterns?
    $gpat = !($this->getOption('marker_solid', true));
    $mcolour = $this->getItemOption('marker_colour', $set, $item, 'colour');
    if(empty($mcolour)) {
      $fill = $this->getColour(null, 0, $set, $gpat, $gpat);
    } else {
      // support fill and fillColour
      $cg = new ColourGroup($this, $item, 0, $set, 'marker_colour', null, 'colour');
      $fill = $cg->stroke();

      // impose marker_solid option
      if(!$gpat)
        $fill = new Colour($this, $fill, false, false);
    }

    // stroke colour is related to the marker fill colour unless it is 'none'
    $cg = new ColourGroup($this, $item, 0, $set, 'marker_stroke_colour',
      $fill->isNone() ? null : $fill);
    $stroke_colour = $cg->stroke();

    $stroke_width = $stroke_colour === null || $stroke_colour->isNone() ? '' :
      $this->getItemOption('marker_stroke_width', $set, $item);

    return $this->createMarker($type, $size, $fill, $stroke_width,
      $stroke_colour, $opacity, $angle);
  }

  /**
   * Creates the marker types
   */
  protected function createMarkers()
  {
    foreach(array_keys($this->markers) as $set) {
      // set the ID for this data set to use
      $this->marker_ids[$set] = $this->createSingleMarker($set);
    }
  }

  /**
   * Returns the position for a data label
   */
  public function dataLabelPosition($dataset, $index, &$item, $x, $y, $w, $h,
    $label_w, $label_h)
  {
    list($pos, $target) = parent::dataLabelPosition($dataset, $index, $item,
      $x, $y, $w, $h, $label_w, $label_h);

    // labels don't fit inside markers
    $pos = str_replace(['inner','inside'], '', $pos);
    if(strpos($pos, 'middle') !== false && strpos($pos, 'right') === false &&
      strpos($pos, 'left') === false)
      $pos = str_replace('middle', 'top', $pos);
    if(strpos($pos, 'centre') !== false && strpos($pos, 'top') === false &&
      strpos($pos, 'bottom') === false)
      $pos = str_replace('centre', 'top', $pos);
    $pos = 'outside ' . $pos;
    return [$pos, $target];
  }

  /**
   * Add a marker label
   */
  public function markerLabel($dataset, $index, &$item, $x, $y)
  {
    if(!$this->getOption(['show_data_labels', $dataset]))
      return false;
    $s = $this->getItemOption('marker_size', 0, $item);
    $s2 = $s / 2;
    $dummy = [];
    $label = $this->addDataLabel($dataset, $index, $dummy, $item,
      $x - $s2, $y - $s2, $s, $s, null);

    if(isset($dummy['id']))
      return $dummy['id'];

    return null;
  }

  /**
   * Returns a pair of best fit lines, above and below
   */
  public function bestFitLines()
  {
    $bbox = new BoundingBox(0, 0, $this->g_width, $this->g_height);
    $bbox->offset($this->pad_left, $this->pad_top);
    $bf = new BestFit($this, $bbox);

    foreach($this->markers as $dataset => $mset) {
      $points = [];

      // create points positioned relative to bottom-left of grid
      foreach($this->markers[$dataset] as $k => $v)
        $points[] = new Point($v->x - $bbox->x1, $bbox->y2 - $v->y);

      $bf->add($dataset, $points);
    }
    return [ $bf->getAbove(), $bf->getBelow() ];
  }

  /**
   * Override to show key and value
   */
  protected function formatTooltip(&$item, $dataset, $key, $value)
  {
    if($this->getOption('datetime_keys')) {
      $number_key = new Number($key);
      $dt = new \DateTime('@' . $number_key);
      $axis = $this->x_axes[$this->main_x_axis];
      $text = $axis->format($dt, $this->getOption('tooltip_datetime_format'));
    } elseif(is_numeric($key)) {
      $num = new Number($key, $this->getOption('units_tooltip_key'),
        $this->getOption('units_before_tooltip_key'));
      $text = $num->format();
    } else {
      $text = $key;
    }

    $num = new Number($value, $this->getOption('units_tooltip'),
      $this->getOption('units_before_tooltip'));
    $text .= ', ' . $num->format();
    return $text;
  }

  /**
   * Returns TRUE if the item is visible on the graph
   */
  public function isVisible($item, $dataset = 0)
  {
    // non-null values should be visible
    return ($item->value !== null);
  }

  /**
   * Override to handle offset caused by block position option
   */
  protected function gridPosition($item, $index)
  {
    $gp = parent::gridPosition($item, $index);
    if($gp === null)
      return null;

    if($this->x_offset === null) {
      $this->x_offset = 0;
      if($this->getOption('block_position_markers'))
        $this->x_offset = parent::gridPosition(null, 1)
          - parent::gridPosition(null, 0.5);
    }
    return $this->x_offset + $gp;
  }
}

