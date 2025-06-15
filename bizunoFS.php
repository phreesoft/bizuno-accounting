<?php
/*
 * Bizuno Accounting - Pulls a file from the users upload folder and outputs it
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2024, PhreeSoft Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    3.x Last Update: 2024-02-01
 * @filesource /bizunoFS.php
 */

namespace bizuno;

if (!defined( 'ABSPATH' )) { die( 'No script kiddies please!' ); }

global $cleaner;
require_once(BIZBOOKS_ROOT.'model/functions.php');
bizAutoLoad (BIZBOOKS_ROOT.'locale/cleaner.php','cleaner');
bizAutoLoad (BIZBOOKS_ROOT.'model/msg.php',     'messageStack');
bizAutoLoad (BIZBOOKS_ROOT.'model/portal.php',  'portal');
bizAutoLoad (BIZBOOKS_ROOT.'model/io.php',      'io');
$msgStack= new messageStack();
$cleaner = new cleaner();

$dirData = wp_upload_dir();
$parts   = explode('/', clean('src', 'path_rel', 'get'), 2);
$bizID   = clean($parts[0], 'integer');
foreach ($GLOBALS['bizPortal'] as $dbData) { if ($bizID == $dbData['id']) { break; } }
define('BIZUNO_DATA', $dbData['data']);
$io      = new io(); // needs BIZUNO_DATA

$fBad = $eBad = $pBad = false;
if (!empty($parts[1])) {
    $fn      = BIZUNO_DATA.$parts[1];
    $path    = pathinfo($fn, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR;
    $ext     = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    $fBad    = !file_exists($fn) ? true : false;
    $pBad    = !$io->validatePath($parts[1]) ? true : false;
    $eBad    = !in_array($ext, $io->getValidExt('image')) ? true : false;
} else { $fBad = true; }
if ($eBad || $pBad || $fBad) { $fn = BIZBOOKS_ROOT.'view/images/bizuno.png'; }

header("Accept-Ranges: bytes");
header("Content-Type: "  .getMimeType($fn));
header("Content-Length: ".filesize($fn));
header("Last-Modified: " .date(DATE_RFC2822, filemtime($fn)));
readfile($fn);

function getMimeType($filename)
{
    $ext = strtolower(substr($filename, strrpos($filename, '.')+1));
    switch ($ext) {
        case "aiff":
        case "aif":  return "audio/aiff";
        case "avi":  return "video/msvideo";
        case "bmp":
        case "gif":
        case "png":
        case "tiff": return "image/$ext";
        case "css":  return "text/css";
        case "csv":  return "text/csv";
        case "doc":
        case "dot":  return "application/msword";
        case "docx": return "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
        case "dotx": return "application/vnd.openxmlformats-officedocument.wordprocessingml.template";
        case "docm": return "application/vnd.ms-word.document.macroEnabled.12";
        case "dotm": return "application/vnd.ms-word.template.macroEnabled.12";
        case "gz":
        case "gzip": return "application/x-gzip";
        case "html":
        case "htm":
        case "php":  return "text/html";
        case "jpg":
        case "jpeg":
        case "jpe":  return "image/jpg";
        case "js":   return "application/x-javascript";
        case "json": return "application/json";
        case "mp3":  return "audio/mpeg3";
        case "mov":  return "video/quicktime";
        case "mpeg":
        case "mpe":
        case "mpg":  return "video/mpeg";
        case "pdf":  return "application/pdf";
        case "pps":
        case "pot":
        case "ppa":
        case "ppt":  return "application/vnd.ms-powerpoint";
        case "pptx": return "application/vnd.openxmlformats-officedocument.presentationml.presentation";
        case "potx": return "application/vnd.openxmlformats-officedocument.presentationml.template";
        case "ppsx": return "application/vnd.openxmlformats-officedocument.presentationml.slideshow";
        case "ppam": return "application/vnd.ms-powerpoint.addin.macroEnabled.12";
        case "pptm": return "application/vnd.ms-powerpoint.presentation.macroEnabled.12";
        case "potm": return "application/vnd.ms-powerpoint.template.macroEnabled.12";
        case "ppsm": return "application/vnd.ms-powerpoint.slideshow.macroEnabled.12";
        case "rtf":  return "application/rtf";
        case "svg":  return "image/svg+xml";
        case "swf":  return "application/x-shockwave-flash";
        case "txt":  return "text/plain";
        case "tar":  return "application/x-tar";
        case "wav":  return "audio/wav";
        case "wmv":  return "video/x-ms-wmv";
        case "xla":
        case "xlc":
        case "xld":
        case "xll":
        case "xlm":
        case "xls":
        case "xlt":
        case "xlt":
        case "xlw":  return "application/vnd.ms-excel";
        case "xlsx": return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
        case "xltx": return "application/vnd.openxmlformats-officedocument.spreadsheetml.template";
        case "xlsm": return "application/vnd.ms-excel.sheet.macroEnabled.12";
        case "xltm": return "application/vnd.ms-excel.template.macroEnabled.12";
        case "xlam": return "application/vnd.ms-excel.addin.macroEnabled.12";
        case "xlsb": return "application/vnd.ms-excel.sheet.binary.macroEnabled.12";
        case "xml":  return "application/xml";
        case "zip":  return "application/zip";
        default:
            if (function_exists(__NAMESPACE__.'\mime_content_type')) { # if mime_content_type exists use it.
                $m = mime_content_type($filename);
            } else {    # if nothing left try shell
                if (strstr($_SERVER['HTTP_USER_AGENT'], "Windows")) { # Nothing to do on windows
                    return ""; # Blank mime display most files correctly especially images.
                }
                if (strstr($_SERVER['HTTP_USER_AGENT'], "Macintosh")) { $m = trim(exec('file -b --mime '.escapeshellarg($filename))); }
                else { $m = trim(exec('file -bi '.escapeshellarg($filename))); }
            }
            $m = explode(";", $m);
            return trim($m[0]);
    }
}
