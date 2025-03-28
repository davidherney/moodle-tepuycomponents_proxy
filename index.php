<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Display remote contet as local content.
 *
 * @package   tepuycomponents_proxy
 * @copyright 2025 David Herney - https://bambuco.co
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');

if (empty($_SERVER['PATH_INFO'])) {
    header('HTTP/1.0 400 Bad Request');
    die('No URI provided');
}

$path = ltrim($_SERVER['PATH_INFO'], '/');
$path = explode('/', $path);
$path = array_filter($path);
$uri = base64_decode($path[0]);

$parsedurl = parse_url($uri);
$domain = $parsedurl['host'];

$authorized = get_config('tepuycomponents_proxy', 'components_proxy_authorized');
$repositories = explode("\n", $authorized);
$repositories = array_map('trim', $repositories);
$repositories = array_filter($repositories);

$valid = false;
foreach ($repositories as $repository) {
    $repositorydomain = parse_url($repository)['host'];
    if ($domain == $repositorydomain) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    header('HTTP/1.0 403 Forbidden');
    die('Invalid URI');
}

$filename = '';
if (count($path) > 1) {
    $filename = implode('/', array_slice($path, 1));
    $contenturl = $uri . $filename;
} else {
    $contenturl = $uri;
}

$filecontents = file_get_contents($contenturl);
$notreplacepath = ['imsmanifest.xml'];

if (empty($filename) || !in_array($filename, $notreplacepath)) {
    $filecontents = preg_replace_callback('/(href|src)="([^"]+)"/i', function ($matches) use ($uri) {
        $baseurl = dirname($uri) . '/!/';
        $relativeurl = $matches[2];
        if (parse_url($relativeurl, PHP_URL_SCHEME) === null) {
            $relativeurl = rtrim($baseurl, '/') . '/' . ltrim($relativeurl, '/');
        }
        return $matches[1] . '="' . $relativeurl . '"';
    }, $filecontents);
}

switch (pathinfo($contenturl, PATHINFO_EXTENSION)) {
    case 'css':
        header('Content-Type: text/css');
        break;
    case 'js':
        header('Content-Type: application/javascript');
        break;
}

echo $filecontents;
