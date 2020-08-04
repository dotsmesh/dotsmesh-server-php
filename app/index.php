<?php

/**

Host data structure
p/ - properties/
c/ - changes/

 */

use BearFramework\App;
use Minishlink\WebPush\VAPID;
use X\API;
use X\API\EndpointError;
use X\Utilities;

if (!defined('DOTSMESH_SERVER_DEV_MODE')) {
    define('DOTSMESH_SERVER_DEV_MODE', false);
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

$requestHost = strtolower(substr($app->request->host, 9)); // remove dotsmesh. // todo validate

if (array_search($requestHost, DOTSMESH_SERVER_HOSTS) === false) {
    http_response_code(503);
    echo 'Invalid host!';
    exit;
}

$app->enableErrorHandler(['logErrors' => true, 'displayErrors' => DOTSMESH_SERVER_DEV_MODE]);

$app->data->useFileDriver(DOTSMESH_SERVER_DATA_DIR);
$app->logs->useFileLogger(DOTSMESH_SERVER_LOGS_DIR);

$app->addons
    ->add('ivopetkov/locks-bearframework-addon');

$app->classes
    ->add('X\API\*', __DIR__ . '/classes/API/*.php')
    ->add('X\Utilities', __DIR__ . '/classes/Utilities.php')
    ->add('X\Utilities\*', __DIR__ . '/classes/Utilities/*.php');

$app->routes
    ->add('/', function (App\Request $request) use ($app, $requestHost) {
        if ($request->query->exists('host')) {
            if ($request->query->exists('pushkey')) {
                if (!$app->data->exists('vapidpublic')) {
                    $keys = VAPID::createVapidKeys();
                    $app->data->setValue('vapidpublic', $keys['publicKey']);
                    $app->data->setValue('vapidprivate', $keys['privateKey']);
                }
                $value = (string) $app->data->getValue('vapidpublic');
                $response = new App\Response($value);
                $response->headers->set($response->headers->make('Content-Type', 'text/plain'));
                $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
                $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                return $response;
                // } elseif ($request->query->exists('about')) {
                //     $data = ['version' => '0.1.0-dev'];
                //     $response = new App\Response\JSON(json_encode($data));
                //     $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
                //     $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                //     return $response;
            }
            if ($request->query->exists('admin')) {
                $hasAPISecret = defined('DOTSMESH_SERVER_ADMIN_API_SECRET') && strlen(DOTSMESH_SERVER_ADMIN_API_SECRET) > 0;
                if (!$hasAPISecret) {
                    $hasLoggedInAdmin = Utilities::hasLoggedInAdmin($request, true);
                    $templateHTML = Utilities::getHTMLFileContent('admin-template', ['host' => $requestHost, 'hasLoggedInAdmin' => $hasLoggedInAdmin]);
                    if ($hasLoggedInAdmin) {
                        $contentHTML = Utilities::getHTMLFileContent('admin-dashboard', ['host' => $requestHost]);
                    } else {
                        $contentHTML = Utilities::getHTMLFileContent('admin-home', ['host' => $requestHost]);
                    }
                    $response = new App\Response\HTML(str_replace('{{content}}', $contentHTML, $templateHTML));
                    $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
                    return $response;
                }
            }
            //  elseif ($request->query->exists('setadminpassword')) {
            //     Utilities::setAdminPassword($requestHost, $request->query->getValue('setadminpassword'));
            //     $response = new App\Response('ok');
            //     $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
            //     return $response;
            // }
            if ($request->query->exists('viewdata')) {
                $keys = [];
                $list = $app->data->getList()->sliceProperties(['key']);
                foreach ($list as $item) {
                    $keys[] = $item->key;
                }
                print_r($keys);
                exit;
            }
            if ($request->query->exists('update4')) {
                // $keysToRename = [];
                // $keysToDelete = [];
                // $list = $app->data->getList()->sliceProperties(['key']);
                // foreach ($list as $item) {

                //     $key = $item->key;
                //     // $parts = explode('/', $key);

                //     // if ($parts[0] === 'p' && ($parts[2] === 'p' || $parts[2] === 's')) {
                //     //     $parts[2] = 'd/' . $parts[2];
                //     //     $newKey = implode('/', $parts);
                //     //     $keysToRename[] = [$key, $newKey];
                //     // }
                //     if (substr($key, -4) === '/d/g') {
                //         $keysToDelete[] = $key;
                //     }
                //     if (substr($key, -12) === '/d/profile/d') {
                //         $keysToDelete[] = $key;
                //     }
                // }
                // print_r($keysToRename);
                // print_r($keysToDelete);
                // if ($app->request->query->exists('do')) {
                //     foreach ($keysToRename as $key) {
                //         $app->logs->log('update3', 'rename ' . $key[0] . ' - ' . $key[1]);
                //         $app->data->rename($key[0], $key[1]);
                //     }
                //     foreach ($keysToDelete as $key) {
                //         $app->logs->log('update3', 'delete ' . $key);
                //         $app->data->delete($key);
                //     }
                //     echo 'Done!';
                // }

                $propertiesIDs = scandir($app->data->getFilename('p'));
                $result = [];
                foreach ($propertiesIDs as $propertyID) {
                    if ($propertyID !== '.' && $propertyID !== '..') {
                        $dataKey = 'p/' . $propertyID . '/x';
                        $data = $app->data->getValue($dataKey);
                        if ($data !== null) {
                            $data = json_decode($data, true);
                            print_r($data);
                            if ($data['t'] === 'group') {
                                $data['t'] = 'g';
                            } else if ($data['t'] === 'user') {
                                $data['t'] = 'u';
                            }
                            $app->data->setValue($dataKey, json_encode($data));
                        }
                    }
                }

                exit;
            }
        }
    })
    ->add('OPTIONS /', function (App\Request $request) {
        $method = strtoupper($request->headers->getValue('Access-Control-Request-Method'));
        if ($method === 'POST') {
            $response = new App\Response();
            $response->headers->set($response->headers->make('Access-Control-Allow-Origin', '*'));
            $response->headers->set($response->headers->make('Access-Control-Allow-Methods', 'POST,GET,OPTIONS'));
            $response->headers->set($response->headers->make('Access-Control-Allow-Headers', 'Content-Type,Accept'));
            $response->headers->set($response->headers->make('Access-Control-Max-Age', '864000'));
            $response->headers->set($response->headers->make('X-Robots-Tag', 'noindex,nofollow'));
            return $response;
        }
    })
    ->add('POST /', function (App\Request $request) use ($app, $requestHost) {
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
                            'group.posts.delete' => API\Endpoints\GroupPostsDelete::class,
                            'group.posts.addPostReaction' => API\Endpoints\GroupPostsAddPostReaction::class,
                            'host.validatePropertyKey' => API\Endpoints\HostValidatePropertyKey::class,
                            'host.validatePropertyID' => API\Endpoints\HostValidatePropertyID::class,
                            'host.changes.get' => API\Endpoints\HostChangesGet::class,
                            'host.changes.notify' => API\Endpoints\HostChangesNotify::class,
                            'host.changes.subscription' => API\Endpoints\HostChangesSubscription::class
                        ];
                        $method = $requestData['method'];
                        if (isset($methods[$method])) {
                            //$app->logs->log('request', $method);
                            $class = $methods[$method];
                            $result = (new $class($requestData['args'], $requestData['options']))->run();
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
                                $response->content = Utilities::loginAdmin($requestHost, $password, $response) ? 'ok' : 'invalid';
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
                                $key = Utilities::createPropertyKey($requestHost, $type);
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
                                $details = Utilities::getKeyDetails($key);
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
