<?php

/*
 * Dots Mesh Server (PHP)
 * https://github.com/dotsmesh/dotsmesh-server-php
 * Free to use under the GPL-3.0 license.
 */

use X\Utilities;

// Utilities::addPropertyKey($host, 'u');
// Utilities::addPropertyKey($host, 'g');

$propertiesList = Utilities::getPropertiesList();
$propertiesKeys = Utilities::getPropertyKeys();

$users = [];
$groups = [];
foreach ($propertiesList as $id => $item) {
    if ($item['t'] === 'u') {
        $users[$id] = $item;
    } else if ($item['t'] === 'g') {
        $groups[$id] = $item;
    }
}
ksort($users);
ksort($groups);

$usersKeys = [];
$groupsKeys = [];
foreach ($propertiesKeys as $key => $item) {
    if (isset($item['i'])) { // used
        $id = $item['i'];
        if (isset($users[$id])) {
            $users[$id]['k'] = $key;
        } elseif (isset($groups[$id])) {
            $groups[$id]['k'] = $key;
        }
    } else {
        if ($item['t'] === 'u') {
            $usersKeys[$key] = $item;
        } else if ($item['t'] === 'g') {
            $groupsKeys[$key] = $item;
        }
    }
}
uasort($usersKeys, function ($a, $b) {
    return $a['d'] - $b['d'];
});
uasort($groupsKeys, function ($a, $b) {
    return $a['d'] - $b['d'];
});

ksort($users);
ksort($groups);

$renderList = function ($properties, $keys, $type) use ($host) {
    $hasItems = false;
    foreach ($properties as $id => $details) {
        echo '<div class="card">';
        if ($details['t'] === 'u') {
            $url = 'https://dotsmesh.' . $host . '/#' . $id;
            $linkTitle = 'Visit profile';
        } else {
            $url = 'https://dotsmesh.' . $host . '/#g:' . $id;
            $linkTitle = 'Visit group';
        }
        echo '<a class="card-image icon-profile" href="' . $url . '" target="_blank" title="' . $linkTitle . '"></a>';
        $hint = 'Created on ' . date('F d, Y', $details['d']);
        if (isset($details['k'])) {
            $hint .= '<br>Used key: ' . strtoupper($details['k']);
        }
        echo '<div class="card-title">' . $id . '</div>';
        echo '<div class="card-hint">' . $hint . '</div>';
        echo '</div>';
        $hasItems = true;
    }
    foreach ($keys as $key => $details) {
        if ($details['t'] === 'u') {
            $url = 'https://dotsmesh.' . $host . '/#-n:' . strtoupper($key);
            $linkTitle = 'Use now and create a new profile';
        } else {
            $url = '';
            $linkTitle = '';
        }
        echo '<div class="card">';
        echo '<a class="card-image icon-key"' . ($url !== '' ? ' href="' . $url . '"' : '') . ' target="_blank" title="' . $linkTitle . '"></a>';
        echo '<a class="card-right-button icon-delete" title="Delete this key" onclick="deleteKey(\'' . $key . '\')"></a>';
        $hint = 'Created on ' . date('F d, Y', $details['d']);
        echo '<div class="card-title">' . strtoupper($key) . '</div>';
        echo '<div class="card-hint">' . $hint . '</div>';
        echo '</div>';
        $hasItems = true;
    }
    if (!$hasItems) {
        if ($type === 'u') {
            echo 'Create a space on this host for a new public profile. Then, share its key with a friend or use it to create your own profile.<br><br>';
        } else {
            echo 'Generate a new group key that points to this host. Gift it to someone or use it to create a new group that you\'ll manage.<br><br>';
        }
    }
    echo '<span class="button button-2" onclick="makeKey(\'' . $type . '\')">Create new</span>';
};

?><script>
    var makeKey = async type => {
        var result = await call('makeKey', {
            type: type
        });
        result = JSON.parse(result);
        if (result.status === 'ok') {
            location.reload();
        }
    };
    var deleteKey = async key => {
        if (confirm('Are you sure you want to delete the key ' + key + '?')) {
            var result = await call('deleteKey', {
                key: key
            });
            result = JSON.parse(result);
            if (result.status === 'ok') {
                location.reload();
            }
        }
    };
</script>
<?php

echo '<div class="title" style="padding:0 10px 70px 10px;">This is the administrator panel for<br>' . $host . '</div>';

echo '<h1>Public profiles</h1>';
$renderList($users, $usersKeys, 'u');

echo '<br><br><h1>Groups</h1>';
$renderList($groups, $groupsKeys, 'g');

if (defined('DOTSMESH_INSTALLER_CONFIG')) {
    $config = DOTSMESH_INSTALLER_CONFIG;
    if (isset($config['autoUpdate'])) {
        if ($config['autoUpdate']) {
            echo '<br><br><h1>Auto updates (Enabled)</h1>';
            echo 'The Dots Mesh software is updated automatically. This can be configured in the config.php file.';
        } else {
            echo '<br><br><h1>Auto updates (Disabled)</h1>';
            echo 'You can enable them in the config.php file (located in the installation directory).';
        }
    }
}
if (defined('DOTSMESH_INSTALLER_DIR')) {
    echo '<br><br><h1>Installation directory</h1>';
    echo 'The host data is located at ' . realpath(DOTSMESH_INSTALLER_DIR);
}
