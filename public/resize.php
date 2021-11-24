<?php

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
preg_match("#^${base}([^\.]+)\.((\d+)x(\d+)|(\d+)([wh]))\.(\w+)$#", $_SERVER['REQUEST_URI'], $m);

if (!$m) {
  throw new \Exception('invalid format');
}

$input_file = __DIR__ . $m[1] . '.' . $m[7];
$output_file = __DIR__ . $m[1] . '.' . $m[2] . '.' . $m[7];

if (!file_exists($input_file)) {
  throw new \Exception('file not found');
}

$info = getimagesize($input_file);
if (!$info) {
  throw new \Exception('not an image');
}

switch ($info['mime']) {
  case 'image/jpeg': $input_image = imagecreatefromjpeg($input_file); break;
  case 'image/gif': $input_image = imagecreatefromgif($input_file); break;
  case 'image/png': $input_image = imagecreatefrompng($input_file); break;
  default: throw new \Exception('unsupported image format');
}

$width = $m[3] ? intval($m[3]) : FALSE;
$height = $m[4] ? intval($m[4]) : FALSE;

if ($width and $height) {
  // ratio
  if ($width / $height > $info[0] / $info[1]) {
    $width_ratio = $width;
    $height_ratio = round($info[1] / $info[0] * $width);
  } else {
    $width_ratio = round($info[0] / $info[1] * $height);
    $height_ratio = $height;
  }
  // crop image
  $output_image = imagecreatetruecolor($width, $height);
  imagealphablending($output_image, TRUE);
  imagecopyresampled(
    $output_image,
    $input_image,
    0 - floor(($width_ratio - $width) / 2),
    0 - floor(($height_ratio - $height) / 2),
    0,
    0,
    $width_ratio,
    $height_ratio,
    $info[0],
    $info[1],
  );
} else {
  throw new \Exception('operation not supported');
}

switch ($info['mime']) {
  case 'image/jpeg': imagejpeg($output_image, $output_file); break;
  case 'image/gif': imagegif($output_image, $output_file); break;
  case 'image/png': imagepng($output_image, $output_file); break;
}

imagedestroy($input_image);
imagedestroy($output_image);

header('Content-Type: ' . $info['mime']);

readfile($output_file);
