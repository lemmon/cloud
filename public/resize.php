<?php

// init

set_exception_handler(function ($ex) {
  header('Content-Type: text/plain; charset=utf-8');
  echo 'error: ', $ex->getMessage();
  die;
});

function dd($x)
{
  header('Content-Type: text/plain; charset=utf-8');
  print_r($x);
  die;
}

const MIME = [
  'webp' => 'image/webp',
  'jpeg' => 'image/jpeg',
  'jpg'  => 'image/jpeg',
  'gif'  => 'image/gif',
  'png'  => 'image/png',
];

// main

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
preg_match("#^{$base}([^\.]+)\.((\d+)x(\d+)|(\d+)([wh]))\.(\w+)$#", $_SERVER['REQUEST_URI'], $m);

if (!$m) {
  throw new \Exception('invalid format');
}

if (!array_key_exists($m[7], MIME)) {
  throw new \Exception('unsupported image format');
}

function find_file(string $base)
{
  foreach (array_keys(MIME) as $type) {
    $file = __DIR__ . $base . '.' . $type;
    if (file_exists($file)) return $file;
  }
}

$input_file = find_file($m[1]);

if (!$input_file) {
  throw new \Exception('file not found');
}

$output_file = __DIR__ . $m[1] . '.' . $m[2] . '.' . $m[7];
$output_mime = MIME[$m[7]];

$info = getimagesize($input_file);
if (!$info) {
  throw new \Exception('not an image');
}

switch ($info['mime']) {
  case 'image/webp': $input_image = imagecreatefromwebp($input_file); break;
  case 'image/jpeg': $input_image = imagecreatefromjpeg($input_file); break;
  case 'image/gif': $input_image = imagecreatefromgif($input_file); break;
  case 'image/png': $input_image = imagecreatefrompng($input_file); break;
  default: throw new \Exception('unsupported image format');
}

$width = $m[3] ? intval($m[3]) : (($m[6] === 'w' and !empty($m[5])) ? intval($m[5]) : FALSE);
$height = $m[4] ? intval($m[4]) : (($m[6] === 'h' and !empty($m[5])) ? intval($m[5]) : FALSE);

if ($width and $height) {
  // ratio
  if ($width / $height > $info[0] / $info[1]) {
    $width_ratio = $width;
    $height_ratio = round($info[1] / $info[0] * $width);
  } else {
    $width_ratio = round($info[0] / $info[1] * $height);
    $height_ratio = $height;
  }
  // offset
  $offset_x = floor(($width_ratio - $width) / 2);
  $offset_y = floor(($height_ratio - $height) / 2);
} elseif ($width) {
  $height = round($info[1] / $info[0] * $width);
  $width_ratio = $width;
  $height_ratio = $height;
  $offset_x = 0;
  $offset_y = 0;
} elseif ($height) {
  $width = round($info[0] / $info[1] * $height);
  $width_ratio = $width;
  $height_ratio = $height;
  $offset_x = 0;
  $offset_y = 0;
} else {
  throw new \Exception('operation not supported');
}

$output_image = imagecreatetruecolor($width, $height);

imagealphablending($output_image, false);
imagesavealpha($output_image, true);
imagecopyresampled(
  $output_image,
  $input_image,
  0 - $offset_x,
  0 - $offset_y,
  0,
  0,
  $width_ratio,
  $height_ratio,
  $info[0],
  $info[1],
);

switch ($output_mime) {
  case 'image/webp': imagewebp($output_image, $output_file, 85); break;
  case 'image/jpeg': imagejpeg($output_image, $output_file, 85); break;
  case 'image/gif': imagegif($output_image, $output_file); break;
  case 'image/png': imagepng($output_image, $output_file); break;
}

imagedestroy($input_image);
imagedestroy($output_image);

header('Content-Type: ' . $output_mime);

readfile($output_file);
