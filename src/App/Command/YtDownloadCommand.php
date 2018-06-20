<?php

namespace App\Command;

use App\Env;

class YtDownloadCommand
{
    private $app;

    public function __construct(Env $app)
    {
        $this->app = $app;
    }

    public function run()
    {
        $yt = $this->app->getYouTrackApi();
        $path = $this->app->getDataDir('youtrack-issues');

        $ytValues = [
            'usernames'   => [],
            'issueTypes'  => [],
            'issueStates' => []
        ];

        $perPage = 500;
        $curPage = 0;
        $afterCursor = 0;

        echo "Issues ...\n";
        while (true) {
            $afterCursor = $perPage * $curPage;
            echo ">";
            $res = $yt->request(
                'get',
                '/export/' . $this->app->getYouTrackProjectId() . "/issues?max={$perPage}&after={$afterCursor}"
            );
            echo ">";

            foreach ($res['data']['issue'] as $rawIssue) {
                $issue = $this->toModel($rawIssue);
                $savePath = $path . '/' . $issue['numberInProject'] . '.json';
                file_put_contents($savePath, json_encode($issue, \JSON_PRETTY_PRINT));
                echo ".";

                if (!empty($issue['Type'])) {
                    $ytValues['issueTypes'][$issue['Type']] = true;
                }
                if (!empty($issue['State'])) {
                    $ytValues['issueStates'][$issue['State']] = true;
                }
                if (!empty($issue['Assignee'])) {
                    foreach ($issue['Assignee'] as $x) {
                        $ytValues['usernames'][$x] = true;
                    }
                }
                if (!empty($issue['reporterName'])) {
                    $ytValues['usernames'][$issue['reporterName']] = true;
                }
                if (!empty($issue['comments'])) {
                    foreach ($issue['comments'] as $x) {
                        $ytValues['usernames'][$x['author']] = true;
                    }
                }
            }
            echo "\n";

            if (count($res['data']['issue']) < $perPage) {
                break;
            }
            $curPage++;
        }

        $perPage = 20000; // huge max because of bug in yt, `after` param doesnt work
        $curPage = 0;
        $afterCursor = 0;

        echo "Links ...\n";
        while (true) {
            $afterCursor = $perPage * $curPage;
            echo ">";
            $res = $yt->request(
                'get',
                "/export/links?max={$perPage}&after={$afterCursor}"
            );
            echo ">";

            foreach ($res['data']['issueLink'] as $rawLink) {
                if ($rawLink['source'] === 'Draft' || $rawLink['target'] === 'Draft') {
                    continue;
                }

                $savePath = $path . '/' . $this->parseId($rawLink['source']) . '.json';
                if (file_exists($savePath)) {
                    $issue = json_decode(file_get_contents($savePath), true);
                    $issue['issueLinks'][] = ['type' => $rawLink['typeName'], 'issueId' => $this->parseId($rawLink['target'])];
                    file_put_contents($savePath, json_encode($issue, \JSON_PRETTY_PRINT));
                }
                echo ".";
            }
            echo "\n";

            if (count($res['data']['issueLink']) < $perPage) {
                break;
            }

            // BUG IN YT API MAKES IT IMPOSSIBLE TO GO PAST FIRST PAGE
            break;

            $curPage++;
        }

        file_put_contents($path . '/yt-values.json', json_encode($ytValues, \JSON_PRETTY_PRINT));
    }

    private function parseId($issueId)
    {
        list ($projectId, $issueNum) = explode('-', $issueId, 2);
        return $issueNum;
    }

    private function toModel(array $raw)
    {
        $result = [];

        foreach ($raw['field'] as $fieldInfo) {
            $fieldId = $fieldInfo['name'];
            $fieldValue = $fieldInfo['values'];

            if (is_array($fieldValue) && count($fieldValue) <= 1) {
                switch ($fieldId) {
                    case 'Assignee':
                        break;
                    default:
                        $fieldValue = isset($fieldValue[0]) ? $fieldValue[0] : null;
                }
            }

            $result[$fieldId] = $fieldValue;
        }

        $result['issueLinks'] = [];

        if (!empty($raw['comment'])) {
            $result['comments'] = $raw['comment'];
        } else {
            $result['comments'] = [];
        }

        $result['id'] = $this->app->getYouTrackProjectId() . '-' . $result['numberInProject'];
        $result['projectId'] = $this->app->getYouTrackProjectId();

        return $result;
    }
}
