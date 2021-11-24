<?php

define('METHOD', $_SERVER['REQUEST_METHOD']);
define('ORIGIN', $_SERVER['HTTP_ORIGIN'] ?? '*');
define('BASE_URL', sprintf(
  '%s://%s/',
  $_SERVER['REQUEST_SCHEME'],
  rtrim($_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']), '/'),
));

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: GET,POST,DELETE,OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

switch (METHOD) {
  case 'POST':
    // check for errors
    if (empty($_POST['bucket'])) {
      http_response_code(400);
      json([ 'error' => 'No bucket name provided.' ]);
    }
    if (empty($_FILES['files'])) {
      http_response_code(400);
      json([ 'error' => 'No files provieded.' ]);
    }
    // prepare files
    $files = prepare_files($_POST['bucket'], $_FILES['files']);
    // upload files
    $files = array_filter(array_map(function ($file) {
      try {
        $dest = __DIR__ . '/' . $file['new_name'];
        if ($dir = dirname($dest) and !is_dir($dir) and !mkdir($dir, 0777, true)) {
          throw new Exception('destination directory does not exists or is not writeable');
        }
        if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
        $img_imfo = getimagesize($dest);
        $file_mime = image_type_to_mime_type($img_imfo[2]);
        return [
          'url'    => BASE_URL . $file['new_name'],
          // 'path'   => $file['new_name'],
          'name'   => $file['name'],
          'mime'   => $file_mime,
          'size'   => $file['size'],
          'width'  => $img_imfo[0],
          'height' => $img_imfo[1],
        ];
      } catch (Exception $e) {
        return false;
      }
    }, $files));
    // return response
    json([
      'files' => $files,
    ]);
    break;
  case 'OPTIONS':
    http_response_code(200);
    exit;
  default:
    http_response_code(405);
    exit;
}

function prepare_files(string $bucket, array $input)
{
  $data = [];
  foreach ($input['name'] as $i => $file) {
    if ($input['error'][$i]) continue;
    $data[] = [
      'name' => $input['name'][$i],
      'type' => $input['type'][$i],
      'tmp_name' => $input['tmp_name'][$i],
      'new_name' => sprintf(
        'data/%s/%s/%s',
        $bucket,
        date('Ym'),
        rtrim(uuid() . '.' . get_extension($input['name'][$i]), '.'),
      ),
      'size' => $input['size'][$i],
    ];
  }
  return $data;
}

function get_extension($file)
{
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $ext = str_replace(['jpeg'], ['jpg'], $ext);
  return $ext;
}

function uuid()
{
  return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
  );
}

function json($data)
{
  header('Content-type: application/json; charset=utf-8');
  echo json_encode($data);
  exit;
}
