<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X;

use BearFramework\App;

class DataMigration
{

    /**
     * 
     * @param string $text
     * @return void
     */
    static private function log(string $text)
    {
        $app = App::get();
        $app->logs->log('migration', $text);
        //echo $text . "\n";
    }

    /**
     * 
     * @param string $dir
     * @return array
     */
    static private function getFiles(string $dir): array
    {
        if (is_dir($dir)) {
            $result = scandir($dir);
            unset($result[0]);
            unset($result[1]);
            return array_values($result);
        }
        return [];
    }

    /**
     * 
     * @param string $dir
     * @return void
     */
    static private function makeDir(string $dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
            self::log('Make dir ' . $dir);
        }
    }

    /**
     * 
     * @param string $dir
     * @return void
     */
    static private function deleteDirIfEmpty(string $dir)
    {
        if (is_dir($dir) && sizeof(scandir($dir)) === 2) {
            rmdir($dir);
        }
    }

    /**
     * 
     * @param string $source
     * @param string $target
     * @return void
     */
    static private function rename(string $source, string $target)
    {
        $targetDir = pathinfo($target, PATHINFO_DIRNAME);
        self::makeDir($targetDir);
        self::log('Rename ' . $source . ' => ' . $target);
        rename($source, $target);
    }

    /**
     * 
     * @param string $source
     * @param string $target
     * @return void
     */
    static private function copy(string $source, string $target)
    {
        $targetDir = pathinfo($target, PATHINFO_DIRNAME);
        self::makeDir($targetDir);
        self::log('Copy ' . $source . ' => ' . $target);
        copy($source, $target);
    }

    /**
     * 
     * @param string $filename
     * @param string $content
     * @return void
     */
    static private function setContent(string $filename, string $content)
    {
        $targetDir = pathinfo($filename, PATHINFO_DIRNAME);
        self::makeDir($targetDir);
        file_put_contents($filename, $content);
    }

    /**
     * 
     * @return string
     */
    static function migrate(): string
    {
        $result = "up to date";
        $app = App::get();
        $app->locks->acquire('migration');
        $checkFilename = DOTSMESH_SERVER_DATA_DIR . '/.migrations/done/1.2';
        if (!is_file($checkFilename)) {
            self::log('Start migrate to v1.2');
            $supportedHosts = DOTSMESH_SERVER_HOSTS;
            if (sizeof($supportedHosts) > 0) {
                $firstHost = $supportedHosts[0];
                $sourceDir = DOTSMESH_SERVER_DATA_DIR;
                $recycleBinDir = DOTSMESH_SERVER_DATA_DIR . '/.migrations/recyclebin/' . time();
                $firstHostDir = DOTSMESH_SERVER_DATA_DIR . '/' . md5($firstHost);

                // Move all data to the first host data dir
                if (is_dir($sourceDir . '/objects')) {
                    if (is_dir($firstHostDir)) {
                        self::rename($firstHostDir, $recycleBinDir . '/objects');
                    }
                    self::makeDir($firstHostDir);
                    self::rename($sourceDir . '/objects', $firstHostDir . '/objects');
                }

                // Update the property IDs (objects/p/key.host => md5(host)/objects/p/key)
                $propertiesDir = $firstHostDir . '/objects/p';
                $propertyIDs = self::getFiles($propertiesDir);
                foreach ($propertyIDs as $propertyID) {
                    $dotIndex = strpos($propertyID, '.');
                    if ($dotIndex !== false) {
                        $propertyIDKey = substr($propertyID, 0, $dotIndex);
                        $propertyIDHost = substr($propertyID, $dotIndex + 1);
                        self::rename($propertiesDir . '/' . $propertyID, DOTSMESH_SERVER_DATA_DIR . '/' . md5($propertyIDHost) . '/objects/p/' . $propertyIDKey);
                    }
                }

                // Update the admin passwords (objects/a/p/host => md5(host)/objects/a/pd)
                $adminPasswordsDir = $firstHostDir . '/objects/a/p';
                $adminHosts = self::getFiles($adminPasswordsDir);
                foreach ($adminHosts as $adminHost) {
                    $dotIndex = strpos($propertyID, '.');
                    self::rename($adminPasswordsDir . '/' . $adminHost, DOTSMESH_SERVER_DATA_DIR . '/' . md5($adminHost) . '/objects/a/pd');
                }
                self::deleteDirIfEmpty($adminPasswordsDir);

                // Update the property keys (objects/k/host.key => md5(host)/objects/k/key)
                $keysDir = $firstHostDir . '/objects/k';
                $keys = self::getFiles($keysDir);
                foreach ($keys as $key) {
                    $dotIndex = strrpos($key, '.');
                    if ($dotIndex !== false) {
                        $keyHost = substr($key, 0, $dotIndex);
                        $keyKey = substr($key, $dotIndex + 1);
                        self::rename($keysDir . '/' . $key, DOTSMESH_SERVER_DATA_DIR . '/' . md5($keyHost) . '/objects/k/' . $keyKey);
                    }
                }

                // Remove vapid files
                $filename = $firstHostDir . '/objects/vapidprivate';
                if (is_file($filename)) {
                    self::rename($filename, $recycleBinDir . '/vapidprivate');
                }
                $filename = $firstHostDir . '/objects/vapidpublic';
                if (is_file($filename)) {
                    self::rename($filename, $recycleBinDir . '/vapidpublic');
                }

                // Update the observer hosts (objects/c/o/h/host => md5(host)/objects/c/o/h/md5(host))
                $observerHostsDir = $firstHostDir . '/objects/c/o/h';
                $observerHosts = self::getFiles($observerHostsDir);
                foreach ($observerHosts as $observerHost) {
                    if (strpos($observerHost, '.') !== false) {
                        self::rename($observerHostsDir . '/' . $observerHost, $observerHostsDir . '/' . md5($observerHost));
                    }
                }

                // Update the observer users (objects/c/o/u/id => md5(host)/objects/c/o/u/md5(id))
                $observerUsersDir = $firstHostDir . '/objects/c/o/u';
                $observerUsers = self::getFiles($observerUsersDir);
                foreach ($observerUsers as $observerUserID) {
                    if (strpos($observerUserID, '.') !== false) {
                        $parts = explode('.', $observerUserID, 2);
                        $observerUserIDHost = $parts[1];
                        $targetObserverHostsDir = DOTSMESH_SERVER_DATA_DIR . '/' . md5($observerUserIDHost) . '/objects/c/o/u';
                        self::rename($observerUsersDir . '/' . $observerUserID, $targetObserverHostsDir . '/' . md5($observerUserID));
                    }
                }

                // Copy files from the first host to ALL other hosts that have users (we do not know which user announces the changes in the file)
                foreach ($supportedHosts as $host) {
                    if ($host !== $firstHost) {
                        $hostDir = DOTSMESH_SERVER_DATA_DIR . '/' . md5($host);
                        $files = self::getFiles($hostDir . '/objects/p');
                        if (!empty($files)) {
                            // Copy c/s/k
                            $changesSubscribersKey = $firstHostDir . '/objects/c/s/k';
                            if (is_file($changesSubscribersKey)) {
                                $targetFile = $hostDir . '/objects/c/s/k';
                                if (is_file($targetFile)) {
                                    self::log($targetFile . ' already exists (this is ok if the migration is ran second time)');
                                } else {
                                    self::copy($changesSubscribersKey, $targetFile);
                                }
                            }
                            // Copy c/l/*
                            $changesSubscribersLogsDir = $firstHostDir . '/objects/c/l';
                            $files = self::getFiles($changesSubscribersLogsDir);
                            foreach ($files as $file) {
                                $targetFile = $hostDir . '/objects/c/l/' . $file;
                                if (is_file($targetFile)) {
                                    self::log($targetFile . ' already exists (this is ok if the migration is ran second time)');
                                } else {
                                    self::copy($changesSubscribersLogsDir . '/' . $file, $targetFile);
                                }
                            }
                            // Copy c/o/h/*
                            $changesObserverHostDir = $firstHostDir . '/objects/c/o/h';
                            $files = self::getFiles($changesObserverHostDir);
                            foreach ($files as $file) {
                                $targetFile = $hostDir . '/objects/c/o/h/' . $file;
                                if (is_file($targetFile)) {
                                    self::log($targetFile . ' already exists (this is ok if the migration is ran second time)');
                                } else {
                                    self::copy($changesObserverHostDir . '/' . $file, $targetFile);
                                }
                            }
                        }
                    }
                }
            }
            self::setContent($checkFilename, time());
            self::log('End migrate to v1.2');
            $result = "ok";
        }
        $app->locks->release('migration');
        return $result;
    }
}
