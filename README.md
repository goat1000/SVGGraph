SVGGraph PHP SVG graph library
==============================

SVGGraph generates a variety of graphs in SVG format.
For examples, documentation, etc. please see: http://www.goat1000.com/svggraph.php

This library is released under LGPL-3.0.

Example usage:

```php
<?php
// if you are using composer you can skip this
require 'svggraph/autoloader.php';

// set some options
$options = [
  'graph_title' => 'A simple graph',
  'bar_space' => 20,
];

// set up an array of values
$values = [ 100, 200, 140, 130, 160, 150 ];

// use full namespace to get SVGGraph instance
$graph = new Goat1000\SVGGraph\SVGGraph(600, 400, $options);

// assign the values
$graph->values($values);

// render a bar graph
$graph->render('BarGraph');

```
