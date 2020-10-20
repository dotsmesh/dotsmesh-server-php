<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

/**
Host data structure
a/ - admin
a/pd - admin password
p/ - properties
c/ - changes
c/l - changes log
c/s/k - changes subscribers
c/o/h/[host] - changes subscribed host data
c/o/u/[id] - changes subscribed user data
k/ - property keys
 */

use BearFramework\App;
use X\API;
use X\API\EndpointError;
use X\Utilities;

if (!defined('DOTSMESH_SERVER_DEV_MODE')) {
    define('DOTSMESH_SERVER_DEV_MODE', false);
}

if (!defined('DOTSMESH_SERVER_LOG_TYPES')) {
    define('DOTSMESH_SERVER_LOG_TYPES', []); // 'request', 'user-push-notification', 'host-changes-notify', 'host-changes-subscription'
}

if (!defined('DOTSMESH_SERVER_ID_KEY_MIN_LENGTH')) {
    define('DOTSMESH_SERVER_ID_KEY_MIN_LENGTH', 1);
}

if (!defined('DOTSMESH_SERVER_DATA_DIR')) {
    http_response_code(503);
    echo 'The DOTSMESH_SERVER_DATA_DIR constant is required!';
    exit;
}

if (!defined('DOTSMESH_SERVER_LOGS_DIR')) {
    http_response_code(503);
    echo 'The DOTSMESH_SERVER_LOGS_DIR constant is required!';
    exit;
}

if (!defined('DOTSMESH_SERVER_HOSTS')) {
    http_response_code(503);
    echo 'The DOTSMESH_SERVER_HOSTS constant is required!';
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

$app = new App();

$host = $app->request->host;
$host = substr($host, 0, 9) === 'dotsmesh.' ? strtolower(substr($host, 9)) : null;

if (array_search($host, DOTSMESH_SERVER_HOSTS) === false) {
    http_response_code(503);
    echo 'Unsupported host!';
    exit;
}

define('DOTSMESH_SERVER_HOST_INTERNAL', $host);

$app->enableErrorHandler(['logErrors' => true, 'displayErrors' => DOTSMESH_SERVER_DEV_MODE]);

$dataDir = DOTSMESH_SERVER_DATA_DIR . '/' . md5($host);
if (!is_dir($dataDir)) {
    mkdir($dataDir);
}
$app->data->useFileDriver($dataDir);

$app->logs->useFileLogger(DOTSMESH_SERVER_LOGS_DIR);

$app->addons
    ->add('ivopetkov/locks-bearframework-addon');

$app->classes
    ->add('X\API\*', __DIR__ . '/classes/API/*.php')
    ->add('X\DataMigration', __DIR__ . '/classes/DataMigration.php')
    ->add('X\Utilities', __DIR__ . '/classes/Utilities.php')
    ->add('X\Utilities\*', __DIR__ . '/classes/Utilities/*.php');

$app->routes
    ->add('/', function (App\Request $request) use ($app, $host) {
        if ($request->query->exists('host')) {
            if ($request->query->exists('heartbeat')) {
                $response = new App\Response\JSON(json_encode(['status' => 'ok', 'time' => time()]));
                $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                $response->headers->set($response->headers->make('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0'));
                return $response;
            } elseif ($request->query->exists('admin')) {
                $hasAPISecret = defined('DOTSMESH_SERVER_ADMIN_API_SECRET') && strlen(DOTSMESH_SERVER_ADMIN_API_SECRET) > 0;
                if (!$hasAPISecret) {
                    $hasLoggedInAdmin = Utilities::hasLoggedInAdmin($request, true);
                    $templateHTML = Utilities::getHTMLFileContent('admin-template', ['host' => $host, 'hasLoggedInAdmin' => $hasLoggedInAdmin]);
                    if ($hasLoggedInAdmin) {
                        $contentHTML = Utilities::getHTMLFileContent('admin-dashboard', ['host' => $host]);
                    } else {
                        $contentHTML = Utilities::getHTMLFileContent('admin-home', ['host' => $host]);
                    }
                    $response = new App\Response\HTML(str_replace('{{content}}', $contentHTML, $templateHTML));
                    $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                    $response->headers->set($response->headers->make('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0'));
                    return $response;
                }
            } elseif ($request->query->exists('setadminpassword')) {
                if (DOTSMESH_SERVER_DEV_MODE) {
                    Utilities::setAdminPassword($request->query->getValue('setadminpassword'));
                    $response = new App\Response\Text('ok');
                    $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                    $response->headers->set($response->headers->make('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0'));
                    return $response;
                }
            } elseif ($request->query->exists('viewdata')) {
                if (DOTSMESH_SERVER_DEV_MODE) {
                    $keys = [];
                    $list = $app->data->getList()->sliceProperties(['key']);
                    foreach ($list as $item) {
                        $keys[] = $item->key;
                    }
                    $response = new App\Response\Text(implode("\n", $keys));
                    $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                    $response->headers->set($response->headers->make('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0'));
                    return $response;
                }
            } elseif ($request->query->exists('migrate')) {
                $response = new App\Response\Text(\X\DataMigration::migrate());
                $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                $response->headers->set($response->headers->make('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0'));
                return $response;
            }
        }
    })
    ->add('OPTIONS /', function (App\Request $request) {
        $method = strtoupper($request->headers->getValue('Access-Control-Request-Method'));
        if ($method === 'POST') {
            $response = new App\Response();
            $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
            $response->headers->set($response->headers->make('Access-Control-Allow-Methods', 'POST,GET,OPTIONS'));
            $response->headers->set($response->headers->make('Access-Control-Allow-Headers', 'Content-Type,Cache-Control,Accept'));
            $response->headers->set($response->headers->make('Access-Control-Max-Age', '864000'));
            $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
            return $response;
        }
    })
    ->add('POST /', function (App\Request $request) {
        if ($request->query->exists('host')) {
            if ($request->query->exists('api')) {
                $response = null;
                $body = file_get_contents('php://input');
                $requestData = json_decode($body, true);
                if (is_array($requestData) && isset($requestData['method'], $requestData['args'], $requestData['options']) && is_string($requestData['method']) && is_array($requestData['args']) && is_array($requestData['options'])) {
                    try {
                        $methods = [
                            'user.autoLogin' => API\Endpoints\UserAutoLogin::class,
                            'user.dataStorage' => API\Endpoints\UserDataStorage::class,
                            'user.inbox' => API\Endpoints\UserInbox::class,
                            'user.login' => API\Endpoints\UserLogin::class,
                            'user.logout' => API\Endpoints\UserLogout::class,
                            'user.setPushSubscription' => API\Endpoints\UserSetPushSubscription::class,
                            'user.signup' => API\Endpoints\UserSignup::class,
                            'user.updateAuth' => API\Endpoints\UserUpdateAuth::class,
                            'user.changes.announce' => API\Endpoints\UserChangesAnnounce::class,
                            'group.invitations.add' => API\Endpoints\GroupInvitationsAdd::class,
                            'group.invitations.get' => API\Endpoints\GroupInvitationsGet::class,
                            'group.invitations.delete' => API\Endpoints\GroupInvitationsDelete::class,
                            'group.create' => API\Endpoints\GroupCreate::class,
                            'group.dataStorage' => API\Endpoints\GroupDataStorage::class,
                            'group.members.approve' => API\Endpoints\GroupMembersApprove::class,
                            'group.members.join' => API\Endpoints\GroupMembersJoin::class,
                            'group.members.leave' => API\Endpoints\GroupMembersLeave::class,
                            'group.members.delete' => API\Endpoints\GroupMembersDelete::class,
                            'group.posts.add' => API\Endpoints\GroupPostsAdd::class,
                            'group.posts.edit' => API\Endpoints\GroupPostsEdit::class,
                            'group.posts.delete' => API\Endpoints\GroupPostsDelete::class,
                            'group.posts.addPostReaction' => API\Endpoints\GroupPostsAddPostReaction::class,
                            'host.validatePropertyKey' => API\Endpoints\HostValidatePropertyKey::class,
                            'host.validatePropertyID' => API\Endpoints\HostValidatePropertyID::class,
                            'host.changes.get' => API\Endpoints\HostChangesGet::class,
                            'host.changes.notify' => API\Endpoints\HostChangesNotify::class,
                            'host.changes.subscription' => API\Endpoints\HostChangesSubscription::class,
                            'utilities.getPushKeys' => API\Endpoints\UtilitiesGetPushKeys::class
                        ];
                        $method = $requestData['method'];
                        if (isset($methods[$method])) {
                            $requestLogEnabled = Utilities::isLogEnabled('request');
                            $class = $methods[$method];
                            if ($requestLogEnabled) {
                                $startTime = microtime(true);
                            }
                            $result = (new $class($requestData['args'], $requestData['options']))->run();
                            if ($requestLogEnabled) {
                                $totalTime = microtime(true) - $startTime;
                                Utilities::log('request', $method . ' ' . $totalTime);
                            }
                            $response = new App\Response\JSON(json_encode([
                                'status' => 'ok',
                                'result' => $result
                            ]));
                        } else {
                            $response = new App\Response\JSON(json_encode([
                                'status' => 'error',
                                'code' => 'invalidEndpoint',
                                'message' => 'Invalid method!'
                            ]));
                        }
                    } catch (EndpointError $e) {
                        $response = new App\Response\JSON(json_encode([
                            'status' => 'error',
                            'code' => $e->code,
                            'message' => $e->message
                        ]));
                    }
                } else {
                    $response = new App\Response\JSON(json_encode([
                        'status' => 'invalidRequestData'
                    ]));
                }
                if ($response !== null) {
                    $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
                    $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                    return $response;
                }
            } elseif ($request->query->exists('admin')) {
                $response = null;
                $body = file_get_contents('php://input');
                $requestData = json_decode($body, true);
                if (is_array($requestData) && isset($requestData['action'], $requestData['args']) && is_string($requestData['action']) && is_array($requestData['args'])) {
                    $args = $requestData['args'];

                    $hasAPISecret = defined('DOTSMESH_SERVER_ADMIN_API_SECRET') && strlen(DOTSMESH_SERVER_ADMIN_API_SECRET) > 0;

                    $isAuthenticatedRequest = function () use ($request, $hasAPISecret) {
                        if ($hasAPISecret && $request->query->getValue('apiSecret') === DOTSMESH_SERVER_ADMIN_API_SECRET) {
                            return true;
                        }
                        if (Utilities::hasLoggedInAdmin($request, true)) {
                            return true;
                        }
                        return false;
                    };

                    switch ($requestData['action']) {
                        case 'login':
                            if (!$hasAPISecret) {
                                // todo rate limit
                                $response = new App\Response\JSON();
                                $password = isset($args['password']) && is_string($args['password']) ? $args['password'] : '';
                                $response->content = Utilities::loginAdmin($password, $response) ? 'ok' : 'invalid';
                            }
                            break;
                        case 'logout':
                            if (!$hasAPISecret) {
                                $response = new App\Response\JSON();
                                Utilities::logoutAdmin($response);
                                $response->content = 'ok';
                            }
                            break;
                        case 'makeKey':
                            if ($isAuthenticatedRequest()) {
                                $response = new App\Response\JSON();
                                $type = isset($args['type']) && is_string($args['type']) ? $args['type'] : '';
                                $key = Utilities::createPropertyKey($type);
                                $response->content = json_encode(['status' => 'ok', 'result' => ['key' => $key]]);
                            }
                            break;
                        case 'deleteKey':
                            if ($isAuthenticatedRequest()) {
                                $response = new App\Response\JSON();
                                $key = isset($args['key']) && is_string($args['key']) ? $args['key'] : '';
                                $result = Utilities::deletePropertyKey($key);
                                $response->content = json_encode(['status' => 'ok', 'result' => ['status' => $result]]);
                            }
                            break;
                        case 'getKeyDetails':
                            if ($isAuthenticatedRequest()) {
                                $response = new App\Response\JSON();
                                $key = isset($args['key']) && is_string($args['key']) ? $args['key'] : '';
                                $details = Utilities::getPropertyKeyDetails($key);
                                $response->content = json_encode(['status' => 'ok', 'result' => $details]);
                            }
                            break;
                    }
                }
                if ($response !== null) {
                    return $response;
                }
            }
        }
    });

$app->addEventListener('sendResponse', function () {
    Utilities::sendQueuedPushNotifications();
});

$app->run();
