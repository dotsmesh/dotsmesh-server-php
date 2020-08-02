<?php

namespace X;

use BearFramework\App;

class Utilities
{
    static private $idCounter = 0;
    static private $queuedPushNotifications = [];

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
            throw new \Exception();
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Dots Mesh Server'); // todo
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // temp
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // temp
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode === 200) {
        } else {
            $error = curl_error($ch);
        }
        curl_close($ch);
        // echo '!!!!' . "\n";
        // echo $result;
        // exit;
        $result = json_decode($result, true);
        if (is_array($result)) {
            if (isset($result['status'])) {
                if ($result['status'] === 'ok') {
                    return isset($result['result']) ? $result['result'] : null;
                } else if ($result['status'] === 'error') {
                    // todo error
                }
            }
        }
        // todo unknown error
    }

    static function parseID(string $id): ?array
    {
        $parts = explode('.', $id, 2);
        if (sizeof($parts) === 2) {
            $key = strtolower($parts[0]);
            if (preg_match('/^[a-z0-9]$/', $key) === false) {
                return null;
            }
            $host = strtolower($parts[1]);
            if (filter_var('http://' . $host . '/', FILTER_VALIDATE_URL) === false) {
                return null;
            }
            return [
                'host' => $host,
                'key' => $key
            ];
        }
        return null;
    }

    static function getPropertyDataPrefix(string $id): string
    {
        return 'p/' . $id . '/';
    }

    static function createProperty(string $id, string $type, string $propertyKey): int
    {
        if ($type === 'u' || $type === 'g') {
            $app = App::get();
            $lockKey = 'create-property-' . $id;
            $app->locks->acquire($lockKey, ['timeout', 10]);
            $dataKeyPrefix = self::getPropertyDataPrefix($id);
            if ($app->data->exists($dataKeyPrefix . 'x')) {
                $app->locks->release($lockKey);
                return 2; // exists
            }
            $app->data->setValue($dataKeyPrefix . 'x', json_encode([
                'd' => time(),
                't' => $type,
                //'k' => $propertyKey
            ]));
            self::setPropertyKeyPropertyID($propertyKey, $id);
            $app->locks->release($lockKey);
            return 1; // ok
        }
        throw new \Exception();
    }

    static function propertyExists(string $id, string $type = null): bool
    {
        $app = App::get();
        $data = $app->data->getValue(self::getPropertyDataPrefix($id) . 'x');
        if ($data !== null) {
            $data = json_decode($data, true);
            if (is_array($data) && isset($data['t']) && ($type === null || $data['t'] === $type)) {
                return true;
            }
        }
        return false;
    }

    static function userExists(string $id): bool
    {
        return self::propertyExists($id, 'u');
    }

    static function groupExists(string $id): bool
    {
        return self::propertyExists($id, 'g');
    }

    static function getMilliseconds()
    {
        if (strlen(PHP_INT_MAX) <= 15) {
            throw new \Exception('Working with big ints is not available to this machine! Maybe update to 64 bits?');
        }
        $parts = explode(' ', microtime(false));
        return (int) $parts[1] . str_pad(substr($parts[0], 2, 3), 3, '0', STR_PAD_LEFT);
    }

    static function getDateID($milliseconds, $precision = 0) // 0 - milliseconds, 1 - seconds, 2 - days
    {
        if ($precision === 0) {
            return str_pad(base_convert($milliseconds, 10, 36), 9, '0', STR_PAD_LEFT); // max Apr 22 5188
        } else if ($precision === 1) {
            return str_pad(base_convert(floor($milliseconds / 1000), 10, 36), 7, '0', STR_PAD_LEFT); // max Apr 05 4453
        } else if ($precision === 2) {
            return str_pad(base_convert(floor($milliseconds / 1000 / 86400), 10, 36), 4, '0', STR_PAD_LEFT); // max Aug 18 6568
        }
        throw new \Exception('');
    }

    static function generateDateBasedID(): string // fixed length 16, must be the same on the client
    {
        self::$idCounter++;
        $temp = base_convert(self::$idCounter, 10, 36);
        return self::getDateID(self::getMilliseconds()) . $temp . substr(base_convert(bin2hex(random_bytes(10)), 16, 36), 0, 7 - strlen($temp));
    }

    static function generateRandomBase36String(int $length): string
    {
        $chars = array_flip(str_split('qwertyuiopasdfghjklzxcvbnm0123456789'));
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = array_rand($chars);
        }
        return implode($result);
    }

    static function generateRandomBase62String(int $length): string
    {
        $chars = array_flip(str_split('qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM0123456789'));
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = array_rand($chars);
        }
        return implode($result);
    }

    static function getUserPushSubscriptions(string $userID): array
    {
        $app = App::get();
        $dataPrefix = self::getPropertyDataPrefix($userID);
        $list = $app->data->getList()->filterBy('key', $dataPrefix . 'e/', 'startWith');
        $result = [];
        foreach ($list as $item) {
            $value = json_decode($item->value, true);
            if (isset($value['p']) && strlen($value['p']) > 0) {
                $subscription = json_decode($value['p'], true);
                if (is_array($subscription)) {
                    $result[] = $subscription;
                }
            }
        }
        return $result;
    }

    static function queuePushNotifications(string $userID)
    {
        self::$queuedPushNotifications[] = $userID;
    }

    static function sendQueuedPushNotifications()
    {
        $userIDs = array_unique(self::$queuedPushNotifications);
        foreach ($userIDs as $userID) {
            self::sendPushNotifications($userID);
        }
    }

    static function sendPushNotifications(string $userID)
    {
        $subscriptions = Utilities::getUserPushSubscriptions($userID);
        if (empty($subscriptions)) {
            return [];
        }
        $app = App::get();
        $auth = [
            'VAPID' => [
                'subject' => 'example.com',
                'publicKey' => $app->data->getValue('vapidpublic'),
                'privateKey' => $app->data->getValue('vapidprivate')
            ],
        ];
        $webPush = new \Minishlink\WebPush\WebPush($auth);
        foreach ($subscriptions as $subscription) {
            $webPush->sendNotification(
                \Minishlink\WebPush\Subscription::create($subscription),
                json_encode(['date' => time()]),
                false
            );
        }
        foreach ($webPush->flush() as $report) {
            //$endpoint = $report->getRequest()->getUri()->__toString();
            $statusCode = $report->getResponse()->getStatusCode();
            $success = (int) $report->isSuccess();
            $app->logs->log('push', $userID . ' - ' . $success . ' - ' . $statusCode);

            // if ($report->isSuccess()) {
            //     echo "[v] Message sent successfully for subscription {$endpoint}.";
            // } else {
            //     echo "[x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}";
            // }
            // echo "\n\n";
        }
    }

    static function isAlphanumeric(string $value, int $maxLength): bool
    {
        return strlen($value) !== 0 && strlen($value) <= $maxLength && preg_match('/^[0-9a-z]*$/', $value) === 1;
    }

    static function isKey(string $value, int $maxLength): bool
    {
        return strlen($value) !== 0 && strlen($value) <= $maxLength && preg_match('/^[0-9a-z\-]*$/', $value) === 1 && strlen(trim($value . '-')) !== strlen($value);
    }

    static function isPropertyID(string $id): bool
    {
        $data = self::parseID($id);
        return $data !== null && Utilities::isKey($data['key'], 40) && strlen($data['key']) >= 3;
    }

    static function createPropertyKey(string $host, string $type): string
    {
        if ($type === 'u' || $type === 'g') {
            $app = App::get();
            for ($i = 0; $i < 1000; $i++) {
                $key = self::generateRandomBase36String(rand(11, 15)) . $type;
                $dataKey = 'k/' . $host . '.' . $key;
                if (!$app->data->exists($dataKey)) {
                    $app->data->setValue($dataKey, json_encode(['d' => time()]));
                    return $host . ':' . $key;
                }
            }
        }
        throw new \Exception();
    }

    static function validatePropertyKey(string $key, string $type): bool
    {
        if (substr($key, -1) === $type) {
            $app = App::get();
            $dataKey = 'k/' . str_replace(':', '.', $key);
            $value = $app->data->validate($dataKey) ? $app->data->getValue($dataKey) : null;
            if ($value !== null) {
                $data = json_decode($value, true);
                if (is_array($data) && isset($data['d']) && !isset($data['i'])) {
                    return true;
                }
            }
        }
        return false;

        // $result = self::makeServerManagerRequest('POST', DOTSMESH_SERVER_SERVER_MANAGER_URL . '?api&secret=' . DOTSMESH_SERVER_SERVER_MANAGER_SECRET, ['action' => 'validate', 'key' => $key, 'context' => $context, 'id' => $propertyID]);
        // return $result === 'valid';
    }

    static private function setPropertyKeyPropertyID(string $key, string $id): void
    {
        $app = App::get();
        $dataKey = 'k/' . str_replace(':', '.', $key);
        $value = $app->data->getValue($dataKey);
        if ($value !== null) {
            $data = json_decode($value, true);
            if (is_array($data) && isset($data['d']) && !isset($data['i'])) {
                $data['i'] = $id;
                $app->data->setValue($dataKey, json_encode($data));
            }
        }
    }

    static function deletePropertyKey(string $key): string
    {
        $app = App::get();
        $dataKey = 'k/' . str_replace(':', '.', $key);
        $value = $app->data->getValue($dataKey);
        if ($value !== null) {
            $data = json_decode($value, true);
            if (is_array($data) && isset($data['d']) && !isset($data['i'])) { // prevent deleting used key
                $app->data->delete($dataKey);
                return 'success';
            } else {
                return 'active';
            }
        }
        return 'notFound';

        // $result = self::makeServerManagerRequest('POST', DOTSMESH_SERVER_SERVER_MANAGER_URL . '?api&secret=' . DOTSMESH_SERVER_SERVER_MANAGER_SECRET, ['action' => 'retire', 'key' => $key, 'id' => $propertyID]);
        // return $result === 'ok';

        //return;
        // $dataKey = str_replace(':', '.', $invitationCode);
        // $app = App::get();
        // $data = $app->data->getValue('invitationcodes/active/' . $dataKey);
        // if ($data !== null) {
        //     $data = strlen($data) > 0 ? json_decode($data, true) : [];
        //     $data['p'] = $property;
        //     $app->data->setValue('invitationcodes/used/' . self::getDateID(self::getMilliseconds()) . '.' . $dataKey, json_encode($data));
        //     $app->data->delete('invitationcodes/active/' . $dataKey);
        // }
    }

    static function getKeyDetails(string $key): ?array
    {
        $app = App::get();
        $dataKey = 'k/' . str_replace(':', '.', $key);
        $value = $app->data->getValue($dataKey);
        if ($value !== null) {
            $data = json_decode($value, true);
            return $data;
        }
        return null;
    }

    static function getPropertiesKeys(string $host): array
    {
        $app = App::get();
        $list = $app->data->getList()->filterBy('key', 'k/', 'startWith');
        $result = [];
        foreach ($list as $item) {
            $key = substr($item->key, 2);
            $lastDotIndex = strrpos($key, '.');
            $keyHost = substr($key, 0, $lastDotIndex);
            if ($keyHost !== $host) {
                continue;
            }
            $key = $keyHost . ':' . substr($key,  $lastDotIndex + 1);
            $result[$key] = json_decode($item->value, true);
            $result[$key]['t'] = substr($key, -1);
        }
        return $result;
    }

    static function getHash($type, $value)
    {
        if ($type === 'SHA-512') {
            return '0:' . base64_encode(hash('sha512', $value, true));
        } else if ($type === 'SHA-256') {
            return '1:' . base64_encode(hash('sha256', $value, true));
        } else if ($type === 'SHA-512-10') {
            return '2' . substr(base64_encode(hash('sha512', $value, true)), 0, 9); // -1 because of the prefix
        }
    }

    static function pack(string $name, $value): string
    {
        return $name . ':' . json_encode($value);
    }

    static function unpack($value): array
    {
        $parts = explode(':', $value, 2);
        return ['name' => $parts[0], 'value' => json_decode($parts[1], true)];
    }

    static function getChanges(int $age, array $keys)
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

    static function announceChanges(string $propertyID, array $keys)
    {
        $parsedID = self::parseID($propertyID);
        if ($parsedID !== null) {
            $app = App::get();
            $log = '';
            foreach ($keys as $key) {
                $log .= time() . ":" . $key . "\n";
            }
            $app->data->append('c/l/' . self::getDateID(self::getMilliseconds(), 2), $log);
            $keysDataKey = 'c/s/k';
            $data = $app->data->getValue($keysDataKey);
            $data = $data === null ? [] : json_decode($data, true);
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
                    'host' => $parsedID['host'], // the property host
                    'keys' => $changedKeys
                ];
                $result = self::makeServerRequest('POST', 'https://dotsmesh.' . $hostToNotify . '/?host&api', ['method' => 'host.changes.notify', 'args' => $args, 'options' => []]);
                $app->logs->log('notify', $hostToNotify . ' - ' . $hostToNotify . ' - ' . $result);
            }
        }
    }

    static function notifyChangesObservers(string $host, array $keys)
    {
        $app = App::get();
        $hostDataKey = 'c/o/h/' . $host;
        $hostData = $app->data->getValue($hostDataKey);
        $hostData = $hostData === null ? [] : json_decode($hostData, true);
        $usersToNotify = [];
        foreach ($keys as $key) {
            if (isset($hostData[$key])) {
                foreach ($hostData[$key] as $userID) {
                    if (!isset($usersToNotify[$userID])) {
                        $usersToNotify[$userID] = [];
                    }
                    $usersToNotify[$userID][] = $key;
                }
            }
        }
        foreach ($usersToNotify as $userID => $userKeys) {
            foreach ($userKeys as $userKey) {
                $messageID = '6' . md5($userKey);
                $dataKey = self::getPropertyDataPrefix($userID) . '/d/p/i/d/' . $messageID;
                $app->data->setValue($dataKey, Utilities::pack('1', [$userKey, Utilities::generateRandomBase62String(15)]));
            }
            Utilities::queuePushNotifications($userID);
        }
    }

    static function modifyChangesSubscription(string $host, array $keysToAdd, array $keysToRemove)
    {
        // todo lock
        $app = App::get();
        $keysDataKey = 'c/s/k';
        $data = $app->data->getValue($keysDataKey);
        $data = $data === null ? [] : json_decode($data, true);
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
            if (empty($data)) {
                $app->data->delete($keysDataKey);
            } else {
                $app->data->setValue($keysDataKey, json_encode($data));
            }
        }
    }

    static function updateChangesObserver(string $userID)
    {
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
        $appliedUserKeysDataKey = 'c/o/u/' . $userID;
        $appliedUserKeys = $app->data->getValue($appliedUserKeysDataKey);
        $appliedUserKeys = $appliedUserKeys === null ? [] : json_decode($appliedUserKeys, true);
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
                $hostDataKey = 'c/o/h/' . $hostToChange;
                $hostData = $app->data->getValue($hostDataKey);
                $hostData = $hostData === null ? [] : json_decode($hostData, true);
                if (isset($addedKeys[$hostToChange])) {
                    foreach ($addedKeys[$hostToChange] as $addedKey) {
                        if (!isset($hostData[$addedKey])) {
                            $hostData[$addedKey] = [];
                            if (!isset($notifyAddedKeys[$hostToChange])) {
                                $notifyAddedKeys[$hostToChange] = [];
                            }
                            $notifyAddedKeys[$hostToChange][] = $addedKey;
                        }
                        if (array_search($userID, $hostData[$addedKey]) === false) {
                            $hostData[$addedKey][] = $userID;
                            $hasChange = true;
                        }
                    }
                }
                if (isset($removedKeys[$hostToChange])) {
                    foreach ($removedKeys[$hostToChange] as $removedKey) {
                        if (isset($hostData[$removedKey])) {
                            $index = array_search($userID, $hostData[$removedKey]);
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
                    if (empty($hostData)) {
                        $app->data->delete($hostDataKey);
                    } else {
                        $app->data->setValue($hostDataKey, json_encode($hostData));
                    }
                }
            }
        }

        if (empty($userKeys)) {
            $app->data->delete($appliedUserKeysDataKey);
        } else {
            $app->data->setValue($appliedUserKeysDataKey, json_encode($userKeys));
        }

        if (!empty($notifyAddedKeys) || !empty($notifyRemovedKeys)) {
            $parsedID = self::parseID($userID);
            if ($parsedID !== null) {
                $userHost = $parsedID['host'];
                $hostsToNotify = array_unique(array_merge(array_keys($notifyAddedKeys), array_keys($notifyRemovedKeys)));
                foreach ($hostsToNotify as $hostToNotify) {
                    $args = [
                        'host' => $userHost,
                        'keysToAdd' => isset($notifyAddedKeys[$hostToNotify]) ? $notifyAddedKeys[$hostToNotify] : [],
                        'keysToRemove' => isset($notifyRemovedKeys[$hostToNotify]) ? $notifyRemovedKeys[$hostToNotify] : []
                    ];
                    $result = self::makeServerRequest('POST', 'https://dotsmesh.' . $hostToNotify . '/?host&api', ['method' => 'host.changes.subscription', 'args' => $args, 'options' => []]);
                    $app->logs->log('subscribe', $userID . ' - ' . $hostToNotify . ' - ' . $result);
                }
            } else {
                // should not get here, but just in case
            }
        }
    }

    static function getHTMLFileContent(string $name, array $localVariables = []): string
    {
        extract($localVariables);
        ob_start();
        include __DIR__ . '/../html/' . $name . '.php';
        return ob_get_clean();
    }

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

    static function loginAdmin(string $host, string $password, App\Response $response): bool
    {
        $app = App::get();
        if (self::validateAdminPassword($host, $password)) {
            $sessionKey = Utilities::generateRandomBase62String(rand(60, 70));
            $app->data->setValue('.temp/as', Utilities::pack('0', [$sessionKey, time()]));
            $cookie = $response->cookies->make('as', $sessionKey);
            $cookie->httpOnly = true;
            $response->cookies->set($cookie);
            return true;
        }
        return false;
    }

    static function logoutAdmin(App\Response $response): void
    {
        $app = App::get();
        $app->data->delete('.temp/as');
        $cookie = $response->cookies->make('as', '');
        $cookie->expire = 0;
        $cookie->httpOnly = true;
        $response->cookies->set($cookie);
    }

    static function setAdminPassword(string $host, string $password)
    {
        $app = App::get();
        $app->data->setValue('a/p/' . $host, Utilities::pack('0', password_hash($password, PASSWORD_DEFAULT)));
    }

    static private function validateAdminPassword(string $host, string $password)
    {
        $app = App::get();
        $data = $app->data->getValue('a/p/' . $host);
        if ($data !== null) {
            $data = Utilities::unpack($data);
            if ($data['name'] === '0') {
                return password_verify($password, $data['value']);
            }
        }
        return false;
    }

    static function getPropertiesList(string $host): array
    {
        $app = App::get();
        $propertiesIDs = scandir($app->data->getFilename('p'));
        $result = [];
        foreach ($propertiesIDs as $propertyID) {
            if ($propertyID !== '.' && $propertyID !== '..') {
                $propertyHost = substr($propertyID, strpos($propertyID, '.') + 1);
                if ($propertyHost !== $host) {
                    continue;
                }
                $data = $app->data->getValue('p/' . $propertyID . '/x');
                if ($data !== null) {
                    $result[$propertyID] = json_decode($data, true);
                }
            }
        }
        return $result;
    }
}
