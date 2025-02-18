<?php

define('METHOD', $_SERVER['REQUEST_METHOD']);
define('ORIGIN', $_SERVER['HTTP_ORIGIN'] ?? '*');
define('BASE_URL', sprintf(
  '%s://%s/',
  $_SERVER['REQUEST_SCHEME'],
  rtrim($_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/'),
));

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: GET,POST,DELETE,OPTIONS');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

switch (METHOD) {
  case 'POST':
    // check for errors
    if (empty($_FILES['files'])) {
      http_response_code(400);
      json([ 'error' => 'No Files Provided' ]);
    }
    // props
    $bucket = $_POST['bucket'] ?? null ?: 'untitled-bucket';
    $folder = $_POST['folder'] ?? null ?: 'untitled-folder';
    $files = $_FILES['files'];
    // prepare files
    $files = prepare_files($bucket, $folder, $files);
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
          'type'   => explode('/', $file_mime)[0],
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

function prepare_files(string $bucket, string $folder, array $files)
{
  $data = [];
  foreach (array_keys($files['name']) as $i) {
    if ($files['error'][$i]) continue;
    $data[] = [
      'name' => $files['name'][$i],
      'type' => $files['type'][$i],
      'tmp_name' => $files['tmp_name'][$i],
      'new_name' => preg_replace('#/{2,}#', '/', sprintf(
        'storage/%s/%s/%s',
        $bucket,
        trim($folder, '/'),
        rtrim(uuid() . '.' . get_extension($files['name'][$i]), '.'),
      )),
      'size' => $files['size'][$i],
    ];
  }
  return $data;
}

function get_extension($file)
{
  $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
  $ext = strtr($ext, [
    'jpeg' => 'jpg',
  ]);
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
