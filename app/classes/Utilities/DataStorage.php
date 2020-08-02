<?php

namespace X\Utilities;

use BearFramework\App;
use InvalidArgumentException;

class DataStorage
{


    private $dataKeyPrefix = null;
    /**
     * 
     * @var \BearFramework\App;
     */
    private $app = null;

    private $readOnly = false;

    public function __construct(string $dataKeyPrefix, bool $readOnly)
    {
        $this->dataKeyPrefix = $dataKeyPrefix;
        $this->app = App::get();
        $this->readOnly = $readOnly;
    }

    public function execute($commands): array
    {
        $dataKeyPrefixLength = strlen($this->dataKeyPrefix);
        $dataRepository = $this->app->data;

        $results = [];
        foreach ($commands as $command) {
            switch ($command['command']) {
                case 'set':
                    if ($this->readOnly) {
                        throw new InvalidArgumentException('The data storage is readonly!');
                    }
                    $key = $command['key']; // validate
                    $this->app->logs->log('request', 'db - set - ' . $this->dataKeyPrefix . ' - ' . $key);
                    $value = $command['value']; // validate
                    $dataRepository->setValue($this->dataKeyPrefix . $key, $value);
                    $results[] = null;
                    break;
                case 'append':
                    if ($this->readOnly) {
                        throw new InvalidArgumentException('The data storage is readonly!');
                    }
                    $key = $command['key']; // validate
                    $this->app->logs->log('request', 'db - append - ' . $this->dataKeyPrefix . ' - ' . $key);
                    $value = $command['value']; // validate
                    $dataRepository->append($this->dataKeyPrefix . $key, $value);
                    $results[] = null;
                    break;
                case 'get':
                    $key = $command['key']; // validate
                    $this->app->logs->log('request', 'db - get - ' .  $this->dataKeyPrefix . ' - ' . $key);
                    $results[] = $dataRepository->getValue($this->dataKeyPrefix . $key);
                    break;
                case 'exists':
                    $key = $command['key']; // validate
                    $this->app->logs->log('request', 'db - exists - ' .  $this->dataKeyPrefix . ' - ' . $key);
                    $results[] = $dataRepository->exists($this->dataKeyPrefix . $key);
                    break;
                case 'delete':
                    if ($this->readOnly) {
                        throw new InvalidArgumentException('The data storage is readonly!');
                    }
                    $key = $command['key']; // validate
                    $this->app->logs->log('request', 'db - delete - ' .  $this->dataKeyPrefix . ' - ' . $key);
                    $dataRepository->delete($this->dataKeyPrefix . $key);
                    $results[] = null;
                    break;
                case 'rename':
                    if ($this->readOnly) {
                        throw new InvalidArgumentException('The data storage is readonly!');
                    }
                    $sourceKey = $command['sourceKey']; // validate
                    $targetKey = $command['targetKey']; // validate
                    $this->app->logs->log('request', 'db - rename - ' . $this->dataKeyPrefix . ' - ' . $sourceKey . ' - ' . $targetKey);
                    $dataRepository->rename($this->dataKeyPrefix . $sourceKey, $this->dataKeyPrefix . $targetKey);
                    $results[] = null;
                    break;
                case 'duplicate':
                    if ($this->readOnly) {
                        throw new InvalidArgumentException('The data storage is readonly!');
                    }
                    $sourceKey = $command['sourceKey']; // validate
                    $targetKey = $command['targetKey']; // validate
                    $this->app->logs->log('request', 'db - duplicate - ' . $this->dataKeyPrefix . ' - ' . $sourceKey . ' - ' . $targetKey);
                    $dataRepository->duplicate($this->dataKeyPrefix . $sourceKey, $this->dataKeyPrefix . $targetKey);
                    $results[] = null;
                    break;
                case 'getList':
                    $options = $command['options']; // validate // keys, keyStartWith, keyEndWith, keySort, sliceProperties, offset >= 0, limit > 0
                    $list = $dataRepository->getList();

                    //if (isset($options['keys'])) {
                    // $list->filterBy('key', $options['keys'], 'inArray'); // todo
                    //}

                    $startWith = $this->dataKeyPrefix;
                    if (isset($options['keyStartWith'])) {
                        $startWith .= $options['keyStartWith'];
                    }
                    $list->filterBy('key', $startWith, 'startWith');

                    if (isset($options['keyEndWith'])) {
                        $list->filterBy('key', $options['keyEndWith'], 'endWith');
                    }

                    if (isset($options['keySort'])) {
                        $list->sortBy('key', $options['keySort']);
                    }
                    $list->toArray(); // TODO fix LIST

                    if (isset($options['limit'])) {
                        $list = $list->slice(isset($options['offset']) ? $options['offset'] : 0, $options['limit']);
                    }
                    $propertiesToReturn = ['key', 'value'];
                    if (isset($options['sliceProperties'])) {
                        $list = $list->sliceProperties($options['sliceProperties']);
                        $propertiesToReturn = $options['sliceProperties'];
                    }
                    $listResults = [];

                    $filterKeys = [];
                    if (isset($options['keys'])) { // TEMP TODO
                        foreach ($options['keys'] as $filterKey) {
                            $filterKeys[] = $this->dataKeyPrefix . $filterKey;
                        }
                    }
                    $this->app->logs->log('request', 'db - getList - ' . $this->dataKeyPrefix . ' - ' . print_r($options, true));
                    foreach ($list as $item) {
                        if (!empty($filterKeys)) { // TOOD
                            if (array_search($item->key, $filterKeys) === false) {
                                continue;
                            }
                        }
                        $itemResults = [];
                        if (array_search('key', $propertiesToReturn) !== false) {
                            $itemResults['key'] = substr($item->key, $dataKeyPrefixLength);
                        }
                        if (array_search('value', $propertiesToReturn) !== false) {
                            $itemResults['value'] = $item->value;
                        }
                        $listResults[] = $itemResults;
                    }
                    $results[] = $listResults;
                    break;
                default:
                    $results[] = null;
                    break;
            }
        }
        return $results;
    }
}
