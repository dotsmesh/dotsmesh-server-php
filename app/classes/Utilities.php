<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

namespace X;

use BearFramework\App;

class Utilities
{
    /**
     * 
     * @var integer
     */
    static private $idCounter = 0;

    /**
     * 
     * @var array
     */
    static private $queuedPushNotifications = [];

    /**
     * 
     * @var array
     */
    static private $cache = [];

    /**
     * 
     * @var integer
     */
    static private $propertyIDKeyPartMaxLength = 60;

    /**
     * 
     * @param string $method
     * @param string $url
     * @param array $data
     * @return mixed
     */
    static function makeServerRequest(string $method, string $url, array $data)
    {
        $ch = curl_init();
        if ($method === 'POST') {
            $json = json_encode($data);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($json)]);
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        } else {
            throw new \Exception('Unsupported method (' . $method . ')!');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Dots Mesh Server');
        if (DOTSMESH_SERVER_DEV_MODE) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 200) {
            curl_close($ch);
            $resultData = json_decode($result, true);
            if (is_array($resultData) && isset($resultData['status'])) {
                if ($resultData['status'] === 'ok') {
                    return isset($resultData['result']) ? $resultData['result'] : null;
                } else if ($resultData['status'] === 'error') {
                    // todo error
                }
            }
            throw new \Exception('Response error: ' . $result);
        } else {
            $exceptionMessage = $httpCode . ', ' . curl_error($ch);
            curl_close($ch);
            throw new \Exception($exceptionMessage);
        }
    }

    /**
     * 
     * @param string $id
     * @return array|null
     */
    static function parseID(string $id): ?array
    {
        $cacheKey = 'id-' . $id;
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }
        $result = null;
        $parts = explode('.', $id, 2);
        if (sizeof($parts) === 2) {
            $key = strtolower($parts[0]);
            if (preg_match('/^[a-z0-9\-]$/', $key) !== false) {
                $keyLength = strlen($key);
                if (strlen(trim($key, '-')) === $keyLength) { // Cant start or end with "-"
                    if ($keyLength >= 1 && $keyLength <= self::$propertyIDKeyPartMaxLength) {
                        $host = strtolower($parts[1]);
                        if (strlen($host) > 0 && filter_var('http://' . $host . '/', FILTER_VALIDATE_URL) !== false) {
                            $result = [
                                'host' => $host,
                                'key' => $key
                            ];
                        }
                    }
                }
            }
        }
        self::$cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * 
     * @param string $id
     * @return string
     */
    static function getPropertyDataPrefix(string $id): string
    {
        $parts = self::parseID($id);
        return 'p/' . $parts['key'] . '/';
    }

    /**
     * 
     * @param string $id
     * @param string $type
     * @param string $propertyKey
     * @return integer Returns 1 if success, 2 if property already exists
     */
    static function createProperty(string $id, string $type, string $propertyKey): int
    {
        $id = strtolower($id);
        $parts = self::parseID($id);
        if ($parts === null) {
            throw new \Exception('Invalid ID provided!');
        }
        if (DOTSMESH_SERVER_HOST_INTERNAL !== $parts['host']) {
            throw new \Exception('The ID host does not match the app host!');
        }
        if (strlen($parts['key']) < DOTSMESH_SERVER_ID_KEY_MIN_LENGTH) {
            throw new \Exception('The key part for the ID must be at least ' . DOTSMESH_SERVER_ID_KEY_MIN_LENGTH . ' characters!');
        }
        if ($type === 'u' || $type === 'g') {
            $app = App::get();
            $lockKey = 'create-property-' . $id;
            $app->locks->acquire($lockKey, ['timeout', 10]);
            $dataKeyPrefix = self::getPropertyDataPrefix($id);
            if ($app->data->exists($dataKeyPrefix . 'x')) {
                $app->locks->release($lockKey);
                return 2; // exists
            }
            $app->data->setValue($dataKeyPrefix . 'x', self::pack('w', [
                'i' => $id, // Added in v1.2
                'd' => time(),
                't' => $type
            ]));
            self::setPropertyKeyPropertyID($propertyKey, $id);
            $app->locks->release($lockKey);
            return 1; // ok
        }
        throw new \Exception('Invalid type provided!');
    }

    /**
     * 
     * @param string $data
     * @return array|null
     */
    static function parsePropertyData(string $data): ?array
    {
        if (substr($data, 0, 1) === '{') { // Old format used in <= v1.1
            return json_decode($data, true);
        } else {
            $data = Utilities::unpack($data);
            if ($data['name'] === 'w') {
                return $data['value'];
            }
        }
        return null;
    }

    /**
     * 
     * @param string $id
     * @param string $type
     * @return boolean
     */
    static function propertyExists(string $id, string $type = null): bool
    {
        $app = App::get();
        $data = $app->data->getValue(self::getPropertyDataPrefix($id) . 'x');
        if ($data !== null) {
            $data = self::parsePropertyData($data);
            if (is_array($data) && isset($data['t']) && ($type === null || $data['t'] === $type)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 
     * @param string $id
     * @return boolean
     */
    static function userExists(string $id): bool
    {
        return self::propertyExists($id, 'u');
    }

    /**
     * 
     * @param string $id
     * @return boolean
     */
    static function groupExists(string $id): bool
    {
        return self::propertyExists($id, 'g');
    }

    /**
     * 
     * @return int
     */
    static function getMilliseconds(): int
    {
        if (strlen(PHP_INT_MAX) <= 15) {
            throw new \Exception('Working with big ints is not available to this machine! Maybe update to 64 bits?');
        }
        $parts = explode(' ', microtime(false));
        return (int) ($parts[1] . str_pad(substr($parts[0], 2, 3), 3, '0', STR_PAD_LEFT));
    }

    /**
     * 
     * @param int $milliseconds
     * @param int $precision
     * @return string
     */
    static function getDateID(int $milliseconds, int $precision = 0): string // 0 - milliseconds, 1 - seconds, 2 - days
    {
        if ($precision === 0) {
            return str_pad(base_convert($milliseconds, 10, 36), 9, '0', STR_PAD_LEFT); // max Apr 22 5188
        } else if ($precision === 1) {
            return str_pad(base_convert(floor($milliseconds / 1000), 10, 36), 7, '0', STR_PAD_LEFT); // max Apr 05 4453
        } else if ($precision === 2) {
            return str_pad(base_convert(floor($milliseconds / 1000 / 86400), 10, 36), 4, '0', STR_PAD_LEFT); // max Aug 18 6568
        }
        throw new \Exception('Unsupported precision!');
    }

    /**
     * 
     * @param string $dateID
     * @return integer
     */
    static function parseDateID(string $dateID): int
    {
        $length = strlen($dateID);
        if ($length === 9) {
            return base_convert($dateID, 36, 10);
        } else if ($length === 7) {
            return base_convert($dateID,  36, 10) * 1000;
        } else if ($length === 4) {
            return base_convert($dateID,  36, 10) * 1000 * 86400;
        }
        throw new \Exception('Unsupported dateID format!');
    }

    /**
     * 
     * @return string
     */
    static function generateDateBasedID(): string // fixed length 16, must be the same on the client
    {
        self::$idCounter++;
        $temp = base_convert(self::$idCounter, 10, 36);
        return self::getDateID(self::getMilliseconds()) . $temp . substr(base_convert(bin2hex(random_bytes(10)), 16, 36), 0, 7 - strlen($temp));
    }

    /**
     * 
     * @param integer $length
     * @return string
     */
    static function generateRandomBase36String(int $length): string
    {
        $chars = array_flip(str_split('qwertyuiopasdfghjklzxcvbnm0123456789'));
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = array_rand($chars);
        }
        return implode($result);
    }

    /**
     * 
     * @param integer $length
     * @return string
     */
    static function generateRandomBase62String(int $length): string
    {
        $chars = array_flip(str_split('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM0123456789'));
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = array_rand($chars);
        }
        return implode($result);
    }

    /**
     * 
     * @param string $data
     * @return array|null
     */
    static function parseUserSessionData(string $data): ?array
    {
        if (substr($data, 0, 1) === '{') { // Old format used in <= v1.1
            return json_decode($data, true);
        } else {
            $data = Utilities::unpack($data);
            if ($data['name'] === 'q') {
                return $data['value'];
            }
        }
        return null;
    }

    /**
     * 
     * @param string $userID
     * @return array
     */
    static function getUserPushSubscriptions(string $userID): array
    {
        $app = App::get();
        $dataPrefix = self::getPropertyDataPrefix($userID);
        $list = $app->data->getList()->filterBy('key', $dataPrefix . 'e/', 'startWith');
        $result = [];
        foreach ($list as $item) {
            $data = self::parseUserSessionData($item->value);
            if (is_array($data) && isset($data['p']) && strlen($data['p']) > 0) {
                $result[] = $data['p'];
            }
        }
        return $result;
    }

    /**
     * Add a user id to the push notifications queue.
     *
     * @param string $userID The user id to add.
     * @param mixed $payload The payload to send.
     * @return void
     */
    static function queuePushNotification(string $userID, $payload = null): void
    {
        self::$queuedPushNotifications[] = [$userID, $payload];
    }

    /**
     * Send the queued push notifications.
     * 
     * @return void
     */
    static function sendQueuedPushNotifications(): void
    {
        if (empty(self::$queuedPushNotifications)) {
            return;
        }
        foreach (self::$queuedPushNotifications as $queuedPushNotificationData) {
            $userID = $queuedPushNotificationData[0];
            $payload = $queuedPushNotificationData[1];
            $subscriptions = Utilities::getUserPushSubscriptions($userID);
            foreach ($subscriptions as $sessionID => $subscription) {
                $subscription = self::unpack($subscription);
                if ($subscription['name'] === 'q') {
                    $data = $subscription['value']; // 0 - subscription, 1 - vapid public key, 2 - vapid private key
                    if (isset($data[0], $data[1], $data[2]) && is_array($data[0]) && is_string($data[1]) && is_string($data[2])) {
                        $webPush = new \Minishlink\WebPush\WebPush([
                            'VAPID' => [
                                'subject' => 'dotsmesh.' . DOTSMESH_SERVER_HOST_INTERNAL,
                                'publicKey' => $data[1],
                                'privateKey' => $data[2]
                            ]
                        ]);
                        $result = $webPush->sendOneNotification(\Minishlink\WebPush\Subscription::create($data[0]), $payload !== null ? self::pack('', $payload) : null);
                        self::log('user-push-notification', $userID . ' ' . ($result->isSuccess() ? 'success' : 'fail') . ' ' . $result->getReason());
                        if ($result->isSubscriptionExpired()) {
                            //self::deleteUserPushSubscription($userID, $sessionID);
                        }
                    }
                }
            }
        }
        self::$queuedPushNotifications = [];
    }

    /**
     * 
     * @param string $id
     * @return boolean
     */
    static function isPropertyID(string $id): bool
    {
        $data = self::parseID($id);
        return $data !== null;
    }

    /**
     * 
     * @param string $data
     * @return array|null
     */
    static function parsePropertyKeyData(string $data): ?array
    {
        if (substr($data, 0, 1) === '{') { // Old format used in <= v1.1
            return json_decode($data, true);
        } else {
            $data = Utilities::unpack($data);
            if ($data['name'] === 'e') {
                return $data['value'];
            }
        }
        return null;
    }

    /**
     * 
     * @param string $key
     * @param array $data
     * @return void
     */
    static function setPropertyKeyData(string $key, array $data): void
    {
        $app = App::get();
        $app->data->setValue('k/' . $key, self::pack('e', $data));
    }

    /**
     * 
     * @param string $key
     * @return array|null
     */
    static function getPropertyKeyData(string $key): ?array
    {
        $app = App::get();
        $value = $app->data->getValue('k/' . $key);
        return $value !== null ? self::parsePropertyKeyData($value) : null;
    }

    /**
     * 
     * @param string $type
     * @return string
     */
    static function createPropertyKey(string $type): string
    {
        if ($type === 'u' || $type === 'g') {
            for ($i = 0; $i < 1000; $i++) {
                $key = self::generateRandomBase36String(rand(11, 15)) . $type;
                if (self::getPropertyKeyData($key) === null) {
                    self::setPropertyKeyData($key, ['d' => time()]);
                    return DOTSMESH_SERVER_HOST_INTERNAL . ':' . $key;
                }
            }
            throw new \Exception('Cannot create property key now!');
        }
        throw new \Exception('Invalid property type!');
    }

    /**
     * 
     * @param string $key
     * @return array
     */
    static function parsePropertyKey(string $key): array
    {
        $parts = explode(':', $key, 2);
        if (sizeof($parts) === 2 && $parts[0] === DOTSMESH_SERVER_HOST_INTERNAL && preg_match('/^[a-z0-9]$/', $parts[1]) !== false) {
            return [
                'host' => $parts[0],
                'key' => $parts[1]
            ];
        }
        return null;
    }

    /**
     * 
     * @param string $key
     * @param string $type
     * @return boolean
     */
    static function validatePropertyKey(string $key, string $type): bool
    {
        $key = strtolower($key);
        if (substr($key, -1) === $type) {
            $parts = self::parsePropertyKey($key);
            if ($parts !== null) {
                $data = self::getPropertyKeyData($parts['key']);
                if (is_array($data) && isset($data['d']) && !isset($data['i'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 
     * @param string $key
     * @param string $id
     * @return void
     */
    static private function setPropertyKeyPropertyID(string $key, string $id): void
    {
        $key = strtolower($key);
        $parts = self::parsePropertyKey($key);
        if ($parts !== null) {
            $data = self::getPropertyKeyData($parts['key']);
            if (is_array($data) && isset($data['d']) && !isset($data['i'])) {
                $data['i'] = $id;
                self::setPropertyKeyData($parts['key'], $data);
            }
        }
    }

    /**
     * 
     * @param string $key
     * @return string
     */
    static function deletePropertyKey(string $key): string
    {
        $key = strtolower($key);
        $app = App::get();
        $parts = self::parsePropertyKey($key);
        if ($parts !== null) {
            $data = self::getPropertyKeyData($parts['key']);
            if (is_array($data) && isset($data['d']) && !isset($data['i'])) { // prevent deleting used key
                $app->data->delete('k/' . $parts['key']);
                return 'success';
            } else {
                return 'active';
            }
        }
        return 'notFound';
    }

    /**
     * 
     * @param string $key
     * @return array|null
     */
    static function getPropertyKeyDetails(string $key): ?array
    {
        $key = strtolower($key);
        $parts = self::parsePropertyKey($key);
        if ($parts !== null) {
            return self::getPropertyKeyData($parts['key']);
        }
        return null;
    }

    /**
     * 
     * @return array
     */
    static function getPropertyKeys(): array
    {
        $app = App::get();
        $list = $app->data->getList()->filterBy('key', 'k/', 'startWith');
        $result = [];
        foreach ($list as $item) {
            $key = DOTSMESH_SERVER_HOST_INTERNAL . ':' . substr($item->key, 2);
            $result[$key] = self::parsePropertyKeyData($item->value);
            $result[$key]['t'] = substr($key, -1);
        }
        return $result;
    }

    /**
     * 
     * @param string $type
     * @param mixed $value
     * @return string
     */
    static function getHash(string $type, $value): string
    {
        if ($type === 'SHA-512') {
            return '0:' . base64_encode(hash('sha512', $value, true));
        } else if ($type === 'SHA-256') {
            return '1:' . base64_encode(hash('sha256', $value, true));
        } else if ($type === 'SHA-512-10') {
            return '2' . substr(base64_encode(hash('sha512', $value, true)), 0, 9); // -1 because of the prefix
        }
        throw new \Exception('Unsupported type!');
    }

    /**
     * 
     * @param string $name
     * @param string $value
     * @return string
     */
    static function pack(string $name, $value): string
    {
        return $name . ':' . json_encode($value);
    }

    /**
     * 
     * @param string $value
     * @return array
     */
    static function unpack(string $value): array
    {
        $parts = explode(':', $value, 2);
        return ['name' => isset($parts[0], $parts[1]) ? $parts[0] : null, 'value' => isset($parts[1]) ? json_decode($parts[1], true) : null];
    }

    /**
     * Parses the changes log and returns a list of the changed keys for the time specified.
     * 
     * @param integer $age
     * @param array $keys
     * @return array
     */
    static function getChanges(int $age, array $keys): array
    {
        $app = App::get();
        $daysCount = ceil($age / 86400);
        if ($daysCount > 90) {
            $daysCount = 90;
        }
        if ($daysCount === 0) {
            return [];
        }
        $result = [];
        $currentTime = time();
        for ($i = 0; $i < $daysCount; $i++) {
            $dataKey = 'c/l/' . self::getDateID(($currentTime - $i * 86400) * 1000, 2);
            $content = $app->data->getValue($dataKey);
            if ($content !== null) {
                foreach ($keys as $key) { // todo validate key
                    if (isset($result[$key])) {
                        continue;
                    }
                    $index = strrpos($content, $key);
                    if ($index !== false) {
                        $startIndex = strrpos(substr($content, 0, $index), "\n");
                        if ($startIndex === false) {
                            $startIndex = 0;
                        } else {
                            $startIndex += 1;
                        }
                        $time = (int) substr($content, $startIndex, $index - $startIndex - 1);
                        if ($time >= $currentTime - $age) {
                            $result[$key] = self::getDateID($time * 1000, 1);
                        } else {
                            $result[$key] = -1;
                        }
                    }
                }
                if (sizeof($keys) === sizeof($result)) {
                    break;
                }
            }
        }
        $result = array_filter($result, function ($v) {
            return $v !== -1;
        });
        return $result;
    }

    /**
     * Sends notifications to the hosts that have subscribed to the keys specified.
     * 
     * @param array $keys
     * @return void
     */
    static function announceChanges(array $keys)
    {
        $app = App::get();
        $log = '';
        foreach ($keys as $key) {
            $log .= time() . ":" . $key . "\n";
        }
        $app->data->append('c/l/' . self::getDateID(self::getMilliseconds(), 2), $log);
        $data = self::getChangesSubscribersData();
        $hostsToNotify = [];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                foreach ($data[$key] as $hostToNotify) {
                    if (!isset($hostsToNotify[$hostToNotify])) {
                        $hostsToNotify[$hostToNotify] = [];
                    }
                    $hostsToNotify[$hostToNotify][] = $key;
                }
            }
        }
        foreach ($hostsToNotify as $hostToNotify => $changedKeys) {
            $args = [
                'host' => DOTSMESH_SERVER_HOST_INTERNAL,
                'keys' => $changedKeys
            ];
            if ($hostToNotify === DOTSMESH_SERVER_HOST_INTERNAL) {
                Utilities::notifyChangesObservers($hostToNotify, $keys);
                $result = 'internal call';
            } else {
                try {
                    $result = self::makeServerRequest('POST', 'https://dotsmesh.' . $hostToNotify . '/?host&api', ['method' => 'host.changes.notify', 'args' => $args, 'options' => []]);
                } catch (\Exception $e) {
                    $result = $e->getMessage();
                }
            }
            self::log('host-changes-notify', $hostToNotify . ' ' . json_encode($args) . ' ' . json_encode($result));
        }
    }

    /**
     * 
     * @param string $host
     * @return string
     */
    static private function getObserverHostDataKey(string $host): string
    {
        return 'c/o/h/' . md5($host);
    }

    /**
     * 
     * @param string $host
     * @return array
     */
    static function getObserverHostData(string $host): array
    {
        $app = App::get();
        $data = $app->data->getValue(self::getObserverHostDataKey($host));
        if ($data !== null) {
            if (substr($data, 0, 1) === '{') { // Old format used in <= v1.1
                $data = json_decode($data, true);
                if (is_array($data)) {
                    $result = [];
                    foreach ($data as $host => $userIDs) {
                        if (is_array($userIDs)) {
                            foreach ($userIDs as $userID) {
                                if (!isset($result[$host])) {
                                    $result[$host] = [];
                                }
                                $userIDParts = self::parseID($userID);
                                if ($userIDParts !== null && $userIDParts['host'] === DOTSMESH_SERVER_HOST_INTERNAL) {
                                    $result[$host][] = $userIDParts['key'];
                                }
                            }
                        }
                    }
                    return $result;
                }
            } else {
                $data = Utilities::unpack($data);
                if ($data['name'] === 'u') {
                    return $data['value'];
                } else {
                    throw new \Exception('');
                }
            }
        }
        return [];
    }

    /**
     * 
     * @param string $host
     * @param array $data
     * @return void
     */
    static function setObserverHostData(string $host, array $data)
    {
        $app = App::get();
        $dataKey = self::getObserverHostDataKey($host);
        if (empty($data)) {
            $app->data->delete($dataKey);
        } else {
            $app->data->setValue($dataKey, self::pack('u', $data));
        }
    }

    /**
     * 
     * @param string $userID
     * @return string
     */
    static private function getObserverUserKeysDataKey(string $userID): string
    {
        return 'c/o/u/' . md5($userID);
    }

    /**
     * 
     * @param string $userID
     * @return array
     */
    static function getObserverUserKeysData(string $userID): array
    {
        $app = App::get();
        $data = $app->data->getValue(self::getObserverUserKeysDataKey($userID));
        if ($data !== null) {
            if (substr($data, 0, 1) === '{') { // Old format used in <= v1.1
                return json_decode($data, true);
            } else {
                $data = Utilities::unpack($data);
                if ($data['name'] === 'i') {
                    return $data['value'];
                } else {
                    throw new \Exception('');
                }
            }
        }
        return [];
    }

    /**
     * 
     * @param string $userID
     * @param array $data
     * @return void
     */
    static function setObserverUserKeysData(string $userID, array $data)
    {
        $app = App::get();
        $dataKey = self::getObserverUserKeysDataKey($userID);
        if (empty($data)) {
            $app->data->delete($dataKey);
        } else {
            $app->data->setValue($dataKey, self::pack('i', $data));
        }
    }

    /**
     * Notify users about changes from a host.
     * 
     * @param string $host
     * @param array $keys
     * @return void
     */
    static function notifyChangesObservers(string $host, array $keys)
    {
        $app = App::get();
        $hostData = self::getObserverHostData($host);
        $usersToNotify = [];
        foreach ($keys as $key) {
            if (isset($hostData[$key])) {
                foreach ($hostData[$key] as $userIDKey) {
                    if (!isset($usersToNotify[$userIDKey])) {
                        $usersToNotify[$userIDKey] = [];
                    }
                    $usersToNotify[$userIDKey][] = $key;
                }
            }
        }
        foreach ($usersToNotify as $userIDKey => $userKeys) {
            $userID = $userIDKey . '.' . DOTSMESH_SERVER_HOST_INTERNAL;
            $dataKeyPrefix = self::getPropertyDataPrefix($userID) . '/d/p/i/d/';
            foreach ($userKeys as $userKey) {
                $messageID = '6' . md5($userKey);
                $app->data->setValue($dataKeyPrefix . $messageID, Utilities::pack('1', [$userKey, Utilities::generateRandomBase62String(15)]));
            }
            Utilities::queuePushNotification($userID);
        }
    }

    /**
     * 
     * @return array
     */
    static function getChangesSubscribersData(): array
    {
        $app = App::get();
        $keysDataKey = 'c/s/k';
        $data = $app->data->getValue($keysDataKey);
        if ($data !== null) {
            if (substr($data, 0, 1) === '{') { // Old format used in <= v1.1
                return json_decode($data, true);
            } else {
                $data = Utilities::unpack($data);
                if ($data['name'] === 'y') {
                    return $data['value'];
                } else {
                    throw new \Exception('');
                }
            }
        }
        return [];
    }

    /**
     * 
     * @param array $data
     * @return void
     */
    static function setChangesSubscribersData(array $data)
    {
        $app = App::get();
        $keysDataKey = 'c/s/k';
        if (empty($data)) {
            $app->data->delete($keysDataKey);
        } else {
            $app->data->setValue($keysDataKey, self::pack('y', $data));
        }
    }

    /**
     * 
     * @param string $host
     * @param array $keysToAdd
     * @param array $keysToRemove
     * @return void
     */
    static function modifyChangesSubscription(string $host, array $keysToAdd, array $keysToRemove)
    {
        // todo lock
        $data = self::getChangesSubscribersData();
        $hasChange = false;
        foreach ($keysToAdd as $keyToAdd) {
            if (!isset($data[$keyToAdd])) {
                $data[$keyToAdd] = [];
            }
            if (array_search($host, $data[$keyToAdd]) === false) {
                $data[$keyToAdd][] = $host;
                $hasChange = true;
            }
        }
        foreach ($keysToRemove as $keyToRemove) {
            if (isset($data[$keyToRemove])) {
                $index = array_search($host, $data[$keyToRemove]);
                if ($index !== false) {
                    $hasChange = true;
                    unset($data[$keyToRemove][$index]);
                    if (empty($data[$keyToRemove])) {
                        unset($data[$keyToRemove]);
                    } else {
                        $data[$keyToRemove] = array_values($data[$keyToRemove]);
                    }
                }
            }
        }
        if ($hasChange) {
            self::setChangesSubscribersData($data);
        }
    }

    /**
     * Updates the changes observer with the latest user data.
     * 
     * @param string $userID
     * @return void
     */
    static function updateChangesSubscriptions(string $userID)
    {
        $userIDParts = self::parseID($userID);
        if ($userIDParts === null) {
            return;
        }
        $userIDKey = $userIDParts['key'];
        // todo validate userid
        // todo locks
        $app = App::get();
        $dataKey = self::getPropertyDataPrefix($userID) . '/d/p/o/h';
        $data = $app->data->getValue($dataKey);
        $userKeys = [];
        if ($data !== null) {
            $data = Utilities::unpack($data);
            if ($data['name'] === '') {
                $userKeys = $data['value'];
            } else {
                // not supported format
            }
        }
        $appliedUserKeys = self::getObserverUserKeysData($userID);
        $flattenKeys = function ($keysData) {
            $result = [];
            foreach ($keysData as $host => $keys) {
                foreach ($keys as $key) {
                    $result[] = $host . ':' . $key;
                }
            }
            return $result;
        };
        $unflattenKeys = function ($flattenKeys) {
            $result = [];
            foreach ($flattenKeys as $flattenKey) {
                $parts = explode(':', $flattenKey, 2);
                if (!isset($result[$parts[0]])) {
                    $result[$parts[0]] = [];
                }
                $result[$parts[0]][] = $parts[1];
            }
            return $result;
        };
        $flattenUserKeys = $flattenKeys($userKeys);
        $flattenAppliedUserKeys = $flattenKeys($appliedUserKeys);
        $addedKeys = $unflattenKeys(array_diff($flattenUserKeys, $flattenAppliedUserKeys));
        $removedKeys = $unflattenKeys(array_diff($flattenAppliedUserKeys, $flattenUserKeys));
        $notifyAddedKeys = [];
        $notifyRemovedKeys = [];
        if (!empty($addedKeys) || !empty($removedKeys)) {
            $hostsToChange = array_unique(array_merge(array_keys($addedKeys), array_keys($removedKeys)));
            foreach ($hostsToChange as $hostToChange) {
                $hasChange = false;
                // todo validate $hostToChange
                // todo host lock
                $hostData = self::getObserverHostData($hostToChange);
                if (isset($addedKeys[$hostToChange])) {
                    foreach ($addedKeys[$hostToChange] as $addedKey) {
                        if (!isset($hostData[$addedKey])) {
                            $hostData[$addedKey] = [];
                            if (!isset($notifyAddedKeys[$hostToChange])) {
                                $notifyAddedKeys[$hostToChange] = [];
                            }
                            $notifyAddedKeys[$hostToChange][] = $addedKey;
                        }
                        if (array_search($userIDKey, $hostData[$addedKey]) === false) {
                            $hostData[$addedKey][] = $userIDKey;
                            $hasChange = true;
                        }
                    }
                }
                if (isset($removedKeys[$hostToChange])) {
                    foreach ($removedKeys[$hostToChange] as $removedKey) {
                        if (isset($hostData[$removedKey])) {
                            $index = array_search($userIDKey, $hostData[$removedKey]);
                            if ($index !== false) {
                                $hasChange = true;
                                unset($hostData[$removedKey][$index]);
                                $hostData[$removedKey] = array_values($hostData[$removedKey]);
                            }
                            if (empty($hostData[$removedKey])) {
                                if (!isset($notifyRemovedKeys[$hostToChange])) {
                                    $notifyRemovedKeys[$hostToChange] = [];
                                }
                                $notifyRemovedKeys[$hostToChange][] = $removedKey;
                                unset($hostData[$removedKey]);
                            }
                        }
                    }
                }
                if ($hasChange) {
                    self::setObserverHostData($hostToChange, $hostData);
                }
            }
        }

        self::setObserverUserKeysData($userID, $userKeys);

        if (!empty($notifyAddedKeys) || !empty($notifyRemovedKeys)) {
            $hosts = array_unique(array_merge(array_keys($notifyAddedKeys), array_keys($notifyRemovedKeys)));
            foreach ($hosts as $host) {
                $keysToAdd = isset($notifyAddedKeys[$host]) ? $notifyAddedKeys[$host] : [];
                $keysToRemove = isset($notifyRemovedKeys[$host]) ? $notifyRemovedKeys[$host] : [];
                if ($host === DOTSMESH_SERVER_HOST_INTERNAL) {
                    Utilities::modifyChangesSubscription($host, $keysToAdd, $keysToRemove);
                    $result = 'internal call';
                } else {
                    $args = [
                        'host' => DOTSMESH_SERVER_HOST_INTERNAL,
                        'keysToAdd' => $keysToAdd,
                        'keysToRemove' => $keysToRemove
                    ];
                    try {
                        $result = self::makeServerRequest('POST', 'https://dotsmesh.' . $host . '/?host&api', ['method' => 'host.changes.subscription', 'args' => $args, 'options' => []]);
                    } catch (\Exception $e) {
                        $result = $e->getMessage();
                    }
                }
                self::log('host-changes-subscription', $userID . ' ' . $host . ' ' . json_encode($keysToAdd) . ' ' . json_encode($keysToRemove) . ' ' . json_encode($result));
            }
        }
    }

    /**
     * 
     * @param string $name
     * @param array $localVariables
     * @return string
     */
    static function getHTMLFileContent(string $name, array $localVariables = []): string
    {
        extract($localVariables);
        ob_start();
        include __DIR__ . '/../html/' . $name . '.php';
        return ob_get_clean();
    }

    /**
     * 
     * @param App\Request $request
     * @param boolean $extendSession
     * @return boolean
     */
    static function hasLoggedInAdmin(App\Request $request, bool $extendSession = false)
    {
        $app = App::get();
        $sessionKey = $request->cookies->getValue('as'); // admin session
        if (strlen($sessionKey) > 0) {
            $sessionData = $app->data->getValue('.temp/as');
            if ($sessionData !== null) {
                $sessionData = Utilities::unpack($sessionData);
                if ($sessionData['name'] === '0') {
                    $currentTime = time();
                    if ($sessionData['value'][0] === $sessionKey && $sessionData['value'][1] + 60 * 60 > $currentTime) {
                        if ($extendSession) {
                            $sessionData['value'][1] = $currentTime;
                            $app->data->setValue('.temp/as', Utilities::pack('0', $sessionData['value']));
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * 
     * @param string $password
     * @param App\Response $response
     * @return boolean
     */
    static function loginAdmin(string $password, App\Response $response): bool
    {
        $app = App::get();
        if (self::validateAdminPassword($password)) {
            $sessionKey = Utilities::generateRandomBase62String(rand(60, 70));
            $app->data->setValue('.temp/as', Utilities::pack('0', [$sessionKey, time()]));
            $cookie = $response->cookies->make('as', $sessionKey);
            $cookie->httpOnly = true;
            $response->cookies->set($cookie);
            return true;
        }
        return false;
    }

    /**
     * 
     * @param App\Response $response
     * @return void
     */
    static function logoutAdmin(App\Response $response): void
    {
        $app = App::get();
        $app->data->delete('.temp/as');
        $cookie = $response->cookies->make('as', '');
        $cookie->expire = 0;
        $cookie->httpOnly = true;
        $response->cookies->set($cookie);
    }

    /**
     * 
     * @param string $password
     * @return void
     */
    static function setAdminPassword(string $password)
    {
        $app = App::get();
        $app->data->setValue('a/pd', Utilities::pack('0', password_hash($password, PASSWORD_DEFAULT)));
    }

    /**
     * 
     * @param string $password
     * @return void
     */
    static private function validateAdminPassword(string $password)
    {
        $app = App::get();
        $data = $app->data->getValue('a/pd');
        if ($data !== null) {
            $data = Utilities::unpack($data);
            if ($data['name'] === '0') {
                return password_verify($password, $data['value']);
            }
        }
        return false;
    }

    /**
     * 
     * @return array
     */
    static function getPropertiesList(): array
    {
        $app = App::get();
        $propertiesIDKeys = scandir($app->data->getFilename('p'));
        $result = [];
        foreach ($propertiesIDKeys as $propertyIDKey) {
            if ($propertyIDKey !== '.' && $propertyIDKey !== '..') {
                $data = $app->data->getValue('p/' . $propertyIDKey . '/x');
                if ($data !== null) {
                    $result[$propertyIDKey . '.' . DOTSMESH_SERVER_HOST_INTERNAL] = self::parsePropertyData($data);
                }
            }
        }
        return $result;
    }

    /**
     * 
     * @param string $value
     * @param array $namesFilter
     * @param string $order
     * @param integer $limit
     * @return array
     */
    static function parseLog(string $value, array $namesFilter = null, string $order = 'asc', int $limit = null): array
    {
        $lines = explode("\n", $value);
        if ($order === 'desc') {
            $lines = array_reverse($lines);
        }
        $result = [];
        $linesCount = sizeof($lines);
        for ($i = 0; $i < $linesCount; $i++) {
            $line = trim($lines[$i]);;
            if (strlen($line) > 0) {
                $index = strpos($line, ':');
                $date = substr($line, 0, $index);
                $data = self::unpack(substr($line, $index + 1));
                if ($namesFilter !== null && array_search($data['name'], $namesFilter) === false) {
                    continue;
                }
                $result[] = [
                    'date' => self::parseDateID($date),
                    'name' => $data['name'],
                    'data' => $data['value']
                ];
                if ($limit !== null) {
                    if (sizeof($result) === $limit) {
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 
     * @param string $type
     * @param string $text
     * @return void
     */
    static function log(string $type, string $text)
    {
        if (self::isLogEnabled($type)) {
            $app = App::get();
            $app->logs->log($type, DOTSMESH_SERVER_HOST_INTERNAL . ' | ' . $text);
        }
    }

    /**
     * 
     * @param string $type
     * @return boolean
     */
    static function isLogEnabled(string $type): bool
    {
        return array_search($type, DOTSMESH_SERVER_LOG_TYPES) !== false;
    }
}
