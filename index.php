<?php

define('METHOD', $_SERVER['REQUEST_METHOD']);
define('ORIGIN', $_SERVER['HTTP_ORIGIN'] ?? '*');
define('BASE_URL', sprintf(
  '%s://%s/',
  $_SERVER['REQUEST_SCHEME'],
  rtrim($_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']), '/'),
));

header('Access-Control-Allow-Origin: ' . ORIGIN);
header('Access-Control-Allow-Methods: OPTIONS, POST');
header('Access-Control-Allow-Headers: content-type');
header('Access-Control-Allow-Credentials: true');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

switch (METHOD) {
  case 'POST':
    // no files provided
    if (empty($_POST['bucket']) or empty($_FILES['files'])) {
      http_response_code(400);
      exit;
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
        return move_uploaded_file($file['tmp_name'], $dest) ? [
          'name' => $file['name'],
          'path' => $file['new_name'],
          'link' => BASE_URL . $file['new_name'],
          'size' => $file['size'],
        ] : null;
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
        rtrim(uuid() . '.' . strtolower(pathinfo($input['name'][$i], PATHINFO_EXTENSION)), '.'),
      ),
      'size' => $input['size'][$i],
    ];
  }
  return $data;
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
