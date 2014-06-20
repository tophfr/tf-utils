<?php
/**
 * @author toph <toph@toph.fr>
 *
 * This file is a part of the TfLib and ExploTf Project.
 *
 * TfLib and ExploTf are the legal property of its developers, whose names
 * may be too numerous to list here. Please refer to the COPYRIGHT file
 * distributed with this source distribution.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

if (!defined('TF_IMAGE_ERROR_IMAGE_PATH')) {
    define('TF_IMAGE_ERROR_IMAGE_PATH', dirname(dirname(dirname(__FILE__))).'/resources/error.png');
}
if (!defined('TF_IMAGE_DEFAULT_QUALITY')) {
    define('TF_IMAGE_DEFAULT_QUALITY', 85);
}

class TfUtils
{

    /** Chemin physique absolu vers l'image d'erreur */
    private static $errorImagePath = null;

    public static function setErrorImagePath($errorImagePath)
    {
        self::$errorImagePath = $errorImagePath;
    }

    public static function getErrorImagePath()
    {
        if (!self::$errorImagePath) {
            self::$errorImagePath = TF_IMAGE_ERROR_IMAGE_PATH;
        }
        return self::$errorImagePath;
    }

    /** Chemin physique absolu la racine du site hébergeant les images */
    private static $mediaSiteDir = null;

    public static function setMediaSiteDir($mediaSiteDir)
    {
        self::$mediaSiteDir = $mediaSiteDir;
    }

    public static function getMediaSiteDir()
    {
        if (!self::$mediaSiteDir) {
            self::$mediaSiteDir = '';
        }
        return self::$mediaSiteDir;
    }

    /** Chemin physique absolu la racine du site hébergeant les images */
    private static $mediaRelativePath = 'images/rsz';

    public static function setMediaRelativePath($mediaRelativePath)
    {
        self::$mediaRelativePath = $mediaRelativePath;
    }

    public static function getMediaRelativePath()
    {
        return self::$mediaRelativePath;
    }

    /** Chemin physique absolu la racine du site hébergeant les images */
    private static $logCallBack = null;

    public static function setLogCallBack($logCallBack)
    {
        self::$logCallBack = $logCallBack;
    }

    public static function getLogCallBack()
    {
        return self::$logCallBack;
    }

    /** Chemin physique absolu la racine du site hébergeant les images */
    private static $defaultMode = 0777;

    public static function setDefaultMode($defaultMode)
    {
        self::$defaultMode = $defaultMode;
    }

    public static function getDefaultMode()
    {
        return self::$defaultMode;
    }

    /** memory_limit temporaire lors du redimenssionement */
    private static $memoryLimit = null;

    public static function setMemoryLimit($memoryLimit)
    {
        self::$memoryLimit = $memoryLimit;
    }

    public static function getMemoryLimit()
    {
        return self::$memoryLimit;
    }

    /** redimensionne $img et renvoit le chemin relatif de l'image générée */
    public static function rsz($img, $options, $quality = TF_IMAGE_DEFAULT_QUALITY)
    {
        return self::image($img, 'rsz:' . $options, $quality);
    }

    /** recadre $img et renvoit le chemin relatif de l'image générée */
    public static function crp($img, $options, $quality = TF_IMAGE_DEFAULT_QUALITY)
    {
        return self::image($img, 'crp:' . $options, $quality);
    }

    /**
     * Applique les filtres sur $img et renvoit le chemin relatif de l'image générée.
     *
     * @param string $img Chemin vers l'image source.
     * @param string $options Filtres et paramètres.
     * @param bool $force Si true, écrase le fichier de destination même si plus récent. false par défaut. Si $img est une URL, aucun contrôle de date ne sera effectué.
     * @param int $quality Qualité de compression (png et jpg uniquement) en pourcentage (max 100). Prend la valeur de la constante TF_IMAGE_DEFAULT_QUALITY par défaut.
     * @param string $ext Extension du fichier de déstination (gif, png, jpg). Selon la source par défaut : bmp=>png, png=>png, gif=>gif, *=>jpg.
     * @return string            Renvoit le chemin du fichier généré.
     */
    public static function image($img, $options, $force = false, $quality = TF_IMAGE_DEFAULT_QUALITY, $ext = null)
    {

        if (!$img) return '';

        $isUrl = preg_match('/^[a-z]+:/', $img);
        $wwwDir = self::getMediaSiteDir();

        if ($wwwDir && !preg_match('|' . DIRECTORY_SEPARATOR . '$|', $wwwDir))
            $wwwDir = $wwwDir . DIRECTORY_SEPARATOR;

        if ($isUrl) {
            $imgPath = $img;
        } else {
            $imgPath = $wwwDir . str_replace('/', DIRECTORY_SEPARATOR, $img);
            if (!file_exists($imgPath) || !is_readable($imgPath)) {
                self::log("Impossible de trouver ou d'ouvrir '$imgPath'");
                $imgPath = self::getErrorImagePath();
            }
        }

        $key = array($imgPath, $options);

        if (preg_match('/\.(png|bmp)$/i', $img)) {
            $extAuto = 'png';
        } elseif (preg_match('/\.gif$/i', $img)) {
            $extAuto = 'gif';
        } else {
            $extAuto = 'jpg';
        }

        if (in_array($ext, array('png', 'gif', 'jpg'))) {
            if ($ext != $extAuto) $key[] = $ext;
        } else {
            $ext = $extAuto;
        }

        if ($quality != TF_IMAGE_DEFAULT_QUALITY && in_array($ext, array('png', 'jpg'))) {
            $key[] = $quality;
        }
        $checksum = md5(implode('|', $key));
        $file = self::getMediaRelativePath() . DIRECTORY_SEPARATOR . substr($checksum, 0, 2) . DIRECTORY_SEPARATOR . $checksum . '.' . $ext;
        $file_path = $wwwDir . $file;
        if (file_exists($file_path) && ($isUrl || @filemtime($imgPath) < @filemtime($file_path)) && !$force) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $file);
        }
        if (self::resizeimg($imgPath, $options, $file_path, $quality)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', $file);
        } else {
            return $img;
        }

    }

    /** applique les filtres sur $img et enregistre le résultat dans $out */
    public static function resizeimg($in, $options, $out = '', $quality = TF_IMAGE_DEFAULT_QUALITY)
    {

        if (!$out) {
            $out = $in;
        } else {
            self::mkdirR(dirname($out));
        }

        if (self::$memoryLimit) {
            $oldMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', self::$memoryLimit);
        } else {
            $oldMemoryLimit = false;
        }

        $return = false;

        $tf = new TfImage($in);
        if ($tf->errno > 0) {
            self::log("new TfImage($in) => TfErrno={$tf->errno}");
        } else {
            $tf->setLogCallBack(self::getLogCallBack());
            $tf->addFiltersByString($options);
            if ($tf->storeFile($out, null, $quality)) {
                $return = true;
            } else {
                self::log("TfImage::storeFile($out) => TfErrno={$tf->errno}");
            }
            $tf->free();
        }

        if ($oldMemoryLimit) {
            unset($tf);
            ini_set('memory_limit', $oldMemoryLimit);
        }

        return $return;
    }

    /** Crée le dossier $d et ses dossiers parents */
    public static function mkdirR($d, $mode = null)
    {
        if (!$mode) {
            $mode = self::getDefaultMode();
        }
        if (!file_exists($d)) {
            self::mkdirR(dirname($d), $mode);
            if (mkdir($d)) {
                chmod($d, $mode);
            }
        }
    }

    public static function log($message)
    {
        if (self::getLogCallBack()) {
            call_user_func(self::getLogCallBack(), $message);
        }
    }

    /**
     * @param string $content string to truncate
     * @param int $lmax max length of text
     * @param string $mode
     *                    'html' (keep html tags)
     *                    'unhtmlize' or 'unhtml' (convert html to text before truncating)
     *                    'strip' (strip some html tags, keep others)
     *                    'text' (default, just truncate)
     * @param string $cplt default: '...', string appened to end of content if truncated
     * @param string $encoding default: 'UTF-8'
     * @return string truncated content
     */
    public static function truncate($content, $lmax, $mode = 'text', $cplt = null, $encoding = null)
    {
        $tf = new TfTruncate();
        if (!is_null($cplt))
            $tf->setCplt($cplt);
        if (!is_null($encoding))
            $tf->setEncoding($encoding);
        switch (strtolower($mode)) {
            case 'html':
                return $tf->truncateHTML($content, $lmax);
            case 'strip':
                return $tf->truncateHTML($content, $lmax, true);
            case 'unhtmlize':
            case 'unhtml':
                return $tf->truncateText($content, $lmax, true);
            default:
                return $tf->truncateText($content, $lmax, false);
        }
    }
}
