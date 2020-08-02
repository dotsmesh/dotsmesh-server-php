<?php

use X\Utilities;

// Utilities::addPropertyKey($host, 'u');
// Utilities::addPropertyKey($host, 'g');


$propertiesList = Utilities::getPropertiesList($host);
$propertiesKeys = Utilities::getPropertiesKeys($host);

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

$renderList = function ($properties, $keys, $type) {
    foreach ($properties as $id => $details) {
        echo '<div class="card">';
        if ($details['t'] === 'u') {
            $url = 'https://dotsmesh.com/#' . $id;
            $linkTitle = 'Visit profile';
        } else {
            $url = 'https://dotsmesh.com/#g:' . $id;
            $linkTitle = 'Visit group';
        }
        echo '<a class="card-image icon-profile" href="' . $url . '" target="_blank" title="' . $linkTitle . '"></a>';
        $hint = 'Created on ' . date('F d, Y', $details['d']);
        if (isset($details['k'])) {
            $hint .= '<br>Used key: ' . $details['k'];
        }
        echo '<div class="card-title">' . $id . '</div>';
        echo '<div class="card-hint">' . $hint . '</div>';
        echo '</div>';
    }
    foreach ($keys as $key => $details) {

        if ($details['t'] === 'u') {
            $url = 'https://dotsmesh.com/#-n:' . $key;
            $linkTitle = 'Use now and create a new profile';
        } else {
            $url = ''; //'https://dotsmesh.com/#ng/' . $key;
            $linkTitle = ''; //Use now and create a new group
        }

        echo '<div class="card">';
        echo '<a class="card-image icon-key"' . ($url !== '' ? ' href="' . $url . '"' : '') . ' target="_blank" title="' . $linkTitle . '"></a>';
        echo '<a class="card-right-button icon-delete" title="Delete this key" onclick="deleteKey(\'' . $key . '\')"></a>';
        $hint = 'Created on ' . date('F d, Y', $details['d']);
        echo '<div class="card-title">' . $key . '</div>';
        echo '<div class="card-hint">' . $hint . '</div>';
        echo '</div>';
    }
    echo '<span class="button-1" onclick="makeKey(\'' . $type . '\')">Create new</span>';
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

echo '<h1>Public profiles</h1>';
$renderList($users, $usersKeys, 'u');

echo '<br><br><h1>Groups</h1>';
$renderList($groups, $groupsKeys, 'g');
