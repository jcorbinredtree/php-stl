<?php

/**
 * PHPSTLDiskCache class definition
 *
 * PHP version 5
 *
 * LICENSE: The contents of this file are subject to the Mozilla Public License Version 1.1
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 *
 * The Original Code is Red Tree Systems Code.
 *
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.6
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * PHPSTLDiskCache
 *
 * Abstract base class for caching compiled templates
 *
 * For a sensible default implementation see PHPSTLDiskCashe
 *
 * TODO this may make more sense as an abstract base class with a default
 *      implementation in something like PHPSTLDiskCache
 *
 * Caches compiled templates
 */
class PHPSTLDiskCache extends PHPSTLTemplateCache
{
    /**
     * Where to cache templates
     *
     * @var string path to a directory
     */
    private $directory;

    /**
     * Whether to hash cachenames
     *
     * If true, templates will be cached as CACHEDIR/xx/x{38}.php, where x is a hex character.
     *
     * If false, templates will be cached as CACHEDIR/{template}.php where
     * "{template}" is the string representation of the template
     *
     * @var boolean
     */
    private $hashed;

    /**
     * Constructor
     *
     * Honors the following php-stl named options:
     *   diskcache_directory sting, defaults to sys_get_temp_dir()/php-stl-cache
     *   diskcache_hashed boolean, default true
     *
     * @param pstl PHPSTL
     */
    public function __construct(PHPSTL $pstl)
    {
        parent::__construct($pstl);

        $this->directory = $this->pstl->getOption('diskcache_directory',
            sys_get_temp_dir().'/php-stl-cache'
        );

        $this->hashed = $this->pstl->getOption('diskcache_hashed', true);

        if (! is_dir($this->directory)) {
            $this->createDirectory($this->directory);
        }

        if ($this->hashed) {
            for ($i=0; $i<256; $i++) {
                $hdir = sprintf('%s/%02x', $this->directory, $i);
                if (! is_dir($hdir)) {
                    $this->createDirectory($hdir);
                }
            }
        }
    }

    /**
     * Wraps mkdir and throws a RuntimeException on failure
     */
    private function createDirectory($dir, $mode=0777)
    {
        ob_start();
        if (! mkdir($dir, $mode, true)) {
            $mess = ob_get_clean();
            throw new RuntimeException("failed to create $dir: $mess");
        }
        ob_end_flush();
    }

    /**
     * If hashing is off, returns a string like "{template}"
     * If hashing is on, returns the 40-character hex encoded SHA1 hash of the
     * same string.
     *
     * Also note, if hashing is off, additional processing is done to remove
     * non alphanumeric characters from the name.
     *
     * @param template PHPSTLTemplate
     * @return string
     * @see $hashed, PHPSTLTemplateCache::cacheName
     */
    public function cacheName(PHPSTLTemplate $template)
    {
        $name = (string) $template;
        if ($this->hashed) {
            $name = strtolower(sha1($name));
        } else {
            $name = preg_replace('/[^a-zA-Z0-9]+/', '_', $name);
        }
        return $name;
    }

    /**
     * Returns the cacheFile, see $hashed for details
     *
     * @return two element array containing meta and content paths
     * @see $hashed, $directory, cacheName
     */
    private function cacheFile(PHPSTLTemplate $template)
    {
        $name = $this->cacheName($template);
        if ($this->hashed) {
            $file = implode('/', array(
                $this->directory,
                substr($name, 0, 2),
                substr($name, 2)
            ));
        } else {
            $file = "$this->directory/$name";
        }
        return array("$file.meta", "$file.php");
    }

    /**
     * @param template PHPSTLTemplate
     * @return boolean
     * @see PHPSTLTemplateCache::exists
     */
    public function exists(PHPSTLTemplate $template)
    {
        list($meta, $content) = $this->cacheFile($template);
        return file_exists($meta) && file_exists($content);
    }


    /**
     * @param template PHPSTLTemplate
     * @return boolean
     * @see PHPSTLTemplateCache::isCached
     */
    public function isCached(PHPSTLTemplate $template)
    {
        list($meta, $content) = $this->cacheFile($template);
        if (file_exists($meta) && file_exists($content)) {
            $cachemtime = min(filemtime($meta), filemtime($content));
            return $template->getLastModified() < $cachemtime;
        } else {
            return false;
        }
    }


    /**
     * Fetches a template from the cache
     *
     * If exists would return fales for this template, this should throw a
     * RuntimeException
     *
     * @param PHPSTLTemplate $template
     * @return array as in store
     * @see PHPSTLTemplateCache::fetch
     */
    public function fetch(PHPSTLTemplate $template)
    {
        list($meta, $content) = $this->cacheFile($template);
        if (! file_exists($meta)) {
            throw new RuntimeException('no cached meta data');
        }
        if (! file_exists($content)) {
            throw new RuntimeException('no cached content');
        }
        return array($meta, $content);
    }

    /**
     * Stores a template in the cache
     *
     * @param PHPSTLTemplate $template
     * @param array $meta
     * @param string $content the compiled content to cache
     * @return array containing two paths:
     * array($metaCache, $contentCache)
     * - $metaCache: path to a serialized associative array containing meta data
     * - $contentCache: path to the compiled php
     * @see PHPSTLTemplateCache::store
     */
    public function store(PHPSTLTemplate $template, $meta, $content)
    {
        list($metaFile, $contentFile) = $this->cacheFile($template);

        if (! @file_put_contents($metaFile, serialize($meta))) {
            throw new RuntimeException(
                "could not write meta data to $metaFile"
            );
        }
        if (! @file_put_contents($contentFile, $content)) {
            throw new RuntimeException(
                "could not write content to $contentFile"
            );
        }

        return array($metaFile, $contentFile);
    }

    /**
     * Clears the cache completely
     *
     * @return void
     * @see PHPSTLTemplateCache::clear
     */
    public function clear()
    {
        if ($this->hashed) {
            for ($i=0; $i<256; $i++) {
                $this->clearDirectory(sprintf('%s/%02x', $this->directory, $i));
            }
        } else {
            $this->clearDirectory($this->directory);
        }
    }

    /**
     * Removes all files inside a directory
     */
    private function clearDirectory($dir)
    {
        ob_start();
        $dh = opendir($dir);
        if (! $dh) {
            $mess = ob_get_clean();
            throw new RuntimeException("Failed to opendir $dir: $mess");
        }
        while (($ent = readdir($dh)) !== false) {
            $f = "$dir/$ent";
            if (is_file($f)) {
                if (! unlink($f)) {
                    $mess = ob_get_clean();
                    throw new RuntimeException("Failed to unlink $f: $mess");
                }
            }
        }
        closedir($dh);
        ob_end_flush();
    }
}

?>
