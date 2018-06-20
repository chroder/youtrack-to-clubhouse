<?php

namespace App\Command;

use App\Env;

class ClubhouseImportCommand
{
    private $app;
    private $issuesDir;
    private $statusInfo;
    private $statusPath;

    public function __construct(Env $app)
    {
        $this->app = $app;
        $this->issuesDir = $this->app->getDataDir('youtrack-issues');
        $this->statusPath = $this->app->getDataDir() . DIRECTORY_SEPARATOR . '/import-status.json';

        if (file_exists($this->statusPath)) {
            $this->statusInfo = json_decode(file_get_contents($this->statusPath), true);
        }

        $mapperPath = $this->app->getConfigDir() . '/mapper.php';
        if (!file_exists($mapperPath)) {
            echo "!!! No mapper defined. You need to generate one.\n";
            echo "\$ php bin/init-mapper.php\n";
            exit(1);
        }

        require_once $mapperPath;
    }

    public function run()
    {
        if (!$this->statusInfo) {
            $this->statusInfo = [
                'startDate' => date('Y-m-d H:i:s'),
                'issues'    => [],
                'epicMap'   => [],
                'deferred'  => [],
            ];
        }

        while (true) {
            $defferredCountBefore = count($this->statusInfo['deferred']);
            $this->iterateIssuesDir();
            $defferredCountAfter = count($this->statusInfo['deferred']);

            if ($defferredCountAfter) {
                if ($defferredCountBefore === $defferredCountAfter) {
                    echo "!!! There are deferred issues that have no proper relationship\n";
                    echo "Issue IDs: " . implode(', ', $this->statusInfo['deferred']) . "\n";
                    echo "Aborting deferred loop.\n";
                    break;
                }
            } else {
                break;
            }
        }
    }

    private function iterateIssuesDir()
    {
        $mapper = new \YouTrackToClubhouseMapper();

        $d = dir($this->issuesDir);
        while (false !== ($f = $d->read())) {
            if ($f === '.' || $f === '..' || $f === 'yt-values.json' || !preg_match('/\.json$/', $f)) {
                continue;
            }

            $fullPath = $d->path . DIRECTORY_SEPARATOR . $f;

            $data = json_decode(file_get_contents($fullPath), true);
            if (!$data || !isset($data['id'])) {
                echo "!";
                continue;
            }

            if (isset($this->statusInfo['issues'][$data['id']])) {
                continue;
            }

            if ($epicData = $mapper->issueAsEpic($data)) {
                $this->saveEpic($data, $epicData);
            } else if ($issueData = $mapper->issueAsStory($data)) {
                $this->saveIssue($data, $issueData);
            }
        }
    }

    private function saveEpic(array $rawData, array $epicData)
    {
        $issueIds = isset($epicData['@epicIssueIds']) ? $epicData['@epicIssueIds'] : [];
        unset($epicData['@epicIssueIds']);

        $ch = $this->app->getClubhouseApi();
        $res = $ch->request('POST', '/epics', $epicData);

        if ($res['httpCode'] !== 201) {
            echo "\n!!!! Error importing {$rawData['id']}\n";
            print_r($res);
            return null;
        }

        $createdEpic = $res['data'];
        $this->statusInfo['issues'][$rawData['id']] = [
            'type' => 'epic',
            'epic' => ['id' => $createdEpic['id']]
        ];

        foreach ($issueIds as $id) {
            $this->statusInfo['epicMap'][$id] = $createdEpic['id'];
        }

        $this->saveStatus();
        echo '|';
        return $createdEpic['id'];
    }

    private function saveIssue(array $rawData, array $storyData)
    {
        $ch = $this->app->getClubhouseApi();

        if (!empty($storyData['epic_id'])) {
            if (preg_match('/^YT:\d+$/', $storyData['epic_id'])) {
                $oldId = str_replace('YT:', '', $storyData['epic_id']);
                if (isset($this->statusInfo['issues'][$storyData['epic_id']])) {
                    $done = $this->statusInfo['issues'][$storyData['epic_id']];
                    if ($done && $done['type'] === 'epic') {
                        $storyData['epic_id'] = $done['epic']['id'];
                    } else {
                        printf("\n[%s] Issue %s is  not an epic. Importing without epic link.\n", $rawData['id'], $storyData['epic_id']);
                        unset($storyData['epic_id']);
                    }
                } else {
                    echo "-";
                    $this->statusInfo['deferred'][$rawData['id']] = true;
                }
            }
        } else {
            if (isset($this->statusInfo['epicMap'][$rawData['id']])) {
                $storyData['epic_id'] = $this->statusInfo['epicMap'][$rawData['id']];
            }
        }

        $res = $ch->request('POST', '/stories', $storyData);

        if ($res['httpCode'] !== 201) {
            echo "\n!!!! Error importing {$rawData['id']}\n";
            print_r($res);
            return;
        }

        unset($this->statusInfo['deferred'][$rawData['id']]);
        unset($this->statusInfo['epicMap'][$rawData['id']]);

        $createdStory = $res['data'];
        $this->statusInfo['issues'][$rawData['id']] = [
            'type'  => 'story',
            'story' => ['id' => $createdStory['id']]
        ];

        $this->saveStatus();
        echo '.';
        return $createdStory['id'];
    }

    private function saveStatus()
    {
        file_put_contents($this->statusPath, json_encode($this->statusInfo, \JSON_PRETTY_PRINT));
    }
}
