<?php
/**
 * Copyright (C) 2009-2020 Graham Breach
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

class Bar3DGraph extends ThreeDGraph {

  use BarGraphTrait {
    barGroup as traitBarGroup;
    setBarWidth as traitSetBarWidth;
  }

  protected $bx;
  protected $by;
  protected $top_id;
  protected $tx;
  protected $ty;

  public function __construct($w, $h, array $settings, array $fixed_settings = [])
  {
    $fs = ['label_centre' => true];
    $fs = array_merge($fs, $fixed_settings);
    parent::__construct($w, $h, $settings, $fs);
  }

  /**
   * Returns the bar top id
   */
  protected function barTop()
  {
    $bw = $this->calculated_bar_width;
    $skew = $this->getOption('skew_top', true);

    $top = ['stroke' => 'none'];
    if($skew) {
      $sc = abs($this->by / $bw);
      $a = 90 - $this->project_angle;
      $path = new PathData('M', 0, 0, 'l', 0, -$bw,
        'l', $bw, 0, 'l', 0, $bw, 'z');
      $xform = new Transform;
      $xform->skewX(-$a);
      $xform->scale(1, $sc);
      $top['transform'] = $xform;
    } else {
      $path = new PathData('M', 0, 0, 'l', $bw, 0,
        'l', $this->bx, $this->by, 'l', -$bw, 0, 'z');
    }
    $top['d'] = $path;
    $bar_top = $this->element('path', $top);
    return $this->defs->defineSymbol($bar_top);
  }

  /**
   * Returns the SVG code for a 3D bar
   */
  protected function bar3D($item, &$bar, $top, $index, $dataset = null,
    $start = null, $axis = null)
  {
    $pos = $this->barY($item->value, $tmp_bar, $start, $axis);
    if($pos === null || $pos > $this->height - $this->pad_bottom)
      return '';

    $side_overlay = min(1, max(0, $this->getOption('bar_side_overlay_opacity')));
    $top_overlay = min(1, max(0, $this->getOption('bar_top_overlay_opacity')));
    $front_overlay = min(1, max(0, $this->getOption('bar_front_overlay_opacity')));

    $bar_side = $bar_top = '';
    $bw = $this->calculated_bar_width;
    $bh = $bar['height'];
    $side_x = $bar['x'] + $bw;
    if($this->skew_side) {
      $sc = $this->bx / $bw;
      $a = $this->project_angle;
      $path = new PathData('M', 0, 0, 'L', $bw, 0,
        'l', 0, $bh, 'l', -$bw, 0, 'z');
      $xform = new Transform;
      $xform->translate($side_x, $bar['y']);
      $xform->skewY(-$a);
      $xform->scale($sc, 1);
    } else {
      $path = new PathData('M', 0, 0, 'l', $this->bx, $this->by,
        'l', 0, $bh, 'l', -$this->bx, -$this->by, 'z');
      $xform = new Transform;
      $xform->translate($side_x, $bar['y']);
    }
    $side = [
      'd' => $path,
      'transform' => $xform,
      'stroke' => 'none',
    ];
    $bar_side = $this->element('path', $side);

    if($side_overlay) {
      $side['fill-opacity'] = $side_overlay;
      $side['fill'] = new Colour($this, $this->getOption('bar_side_overlay_colour'));
      $bar_side .= $this->element('path', $side);
    }

    if($top) {
      $xform = new Transform;
      $xform->translate($bar['x'], $bar['y']);
      $top = ['transform' => $xform];
      $skew = $this->getOption('skew_top', true);
      $top['fill'] = $this->getColour($item, $index, $dataset, $skew, $skew);
      $bar_top = $this->defs->useSymbol($this->top_id, $top);

      if($top_overlay) {
        $top['fill-opacity'] = $top_overlay;
        $top['fill'] = new Colour($this, $this->getOption('bar_top_overlay_colour'));
        $bar_top .= $this->defs->useSymbol($this->top_id, $top);
      }
    }

    $bar['stroke'] = 'none';
    $rect = $this->element('rect', $bar);

    if($front_overlay) {
      $obar = $bar;
      $obar['fill-opacity'] = $front_overlay;
      $obar['fill'] = new Colour($this, $this->getOption('bar_front_overlay_colour'));
      $rect .= $this->element('rect', $obar);
    }

    // to prevent sharp corners, draw the outline over the top
    if($bar['height'] > 0) {
      $path = new PathData(
        // surround
        'M', $bar['x'], $bar['y'] + $bar['height'],
        'l', 0, -$bar['height'],
        $this->bx, $this->by,
        $bar['width'], 0,
        0, $bar['height'],
        -$this->bx, -$this->by, 'z',

        // vertical
        'M', $bar['x'] + $bar['width'], $bar['y'], 'v', $bar['height'],

        // top front and side
        'M', $bar['x'], $bar['y'], 'l', $bar['width'], 0, $this->bx, $this->by
      );
    } else {
      // no height, just a parallelogram
      $path = new PathData(
        'M', $bar['x'], $bar['y'],
        'l', $this->bx, $this->by,
        $bar['width'], 0,
        -$this->bx, -$this->by, 'z'
      );
    }
    $edges = $this->element('path', ['d' => $path, 'fill' => 'none']);
    return $rect . $bar_top . $bar_side . $edges;
  }

  /**
   * Set the bar width and space, create the top
   */
  protected function setBarWidth($width, $space)
  {
    $this->traitSetBarWidth($width, $space);

    // make the top parallelogram, set it as a symbol
    list($this->bx, $this->by) = $this->project(0, 0, $width);
    list($this->tx, $this->ty) = $this->project(0, 0, $space);
    $this->top_id = $this->barTop();
  }

  /**
   * Add the translation to the bar group
   */
  protected function barGroup()
  {
    $group = $this->traitBarGroup();
    if($this->tx || $this->ty) {
      $xform = new Transform;
      $xform->translate($this->tx, $this->ty);
      $group['transform'] = $xform;
    }
    return $group;
  }

  /**
   * Returns the SVG code for a bar
   */
  protected function drawBar(DataItem $item, $index, $start = 0, $axis = null,
    $dataset = 0, $options = [])
  {
    if($item->value === null)
      return '';

    $bar = $this->barDimensions($item, $index, $start, $axis, $dataset);
    if(empty($bar))
      return '';

    // is the bar top drawn?
    $top = !isset($options['top']) || $options['top'];

    // if the bar is empty and no legend or labels to show give up now
    if(!$top && (string)$bar['height'] == '0' && !$this->legend_show_empty &&
      !$this->show_data_labels)
      return '';

    // fill and stroke the group
    $group = ['fill' => $this->getColour($item, $index, $dataset)];
    $this->setStroke($group, $item, $index, $dataset);

    if($this->semantic_classes)
      $group['class'] = 'series' . $dataset;

    $label_shown = $this->addDataLabel($dataset, $index, $group, $item,
      $bar['x'], $bar['y'], $bar['width'], $bar['height']);

    if($this->show_tooltips)
      $this->setTooltip($group, $item, $dataset, $item->key, $item->value,
        $label_shown);
    if($this->show_context_menu)
      $this->setContextMenu($group, $dataset, $item, $label_shown);

    $bar_part = $this->element('g', $group, null,
      $this->bar3D($item, $bar, $top, $index, $dataset, $start, $axis));
    return $this->getLink($item, $item->key, $bar_part);
  }
}

