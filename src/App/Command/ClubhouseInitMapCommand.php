<?php

namespace App\Command;

use App\Env;

class ClubhouseInitMapCommand
{
    private $app;

    public function __construct(Env $app)
    {
        $this->app = $app;
    }

    public function run()
    {
        $mapperPath = $this->app->getConfigDir() . DIRECTORY_SEPARATOR . 'mapper.php';
        $ytValsPath = $this->app->getDataDir('youtrack-issues') . DIRECTORY_SEPARATOR . 'yt-values.json';

        if (file_exists($mapperPath)) {
            echo "!!! You already have a mapper file. Delete it to re-generate a new one.\n";
            echo "Path: $mapperPath\n";
            exit(1);
        }

        if (!file_exists($ytValsPath)) {
            echo "!!! You need to download YT issue data first.\n";
            echo "Expect to see values file: $ytValsPath\n";
            echo "\$ php bin/yt-download.php\n";
            exit(1);
        }

        $ytValues = json_decode(file_get_contents($ytValsPath), true);

        echo "Attempting to map usernames ...\n";
        $usernameData = $this->mapUsernames(array_keys($ytValues['usernames']));
        echo " .. Done\n";

        echo "Attempting to map issue types ...\n";
        $issueTypeData = $this->mapTypes(array_keys($ytValues['issueTypes']));
        echo " .. Done\n";

        echo "Attempting to map states to workflows ...\n";
        $stateDataData = $this->mapStates(array_keys($ytValues['issueStates']));
        echo " .. Done\n";

        $mapperCode = $this->mapperTpl();

        //-----
        // username mapper
        //-----

        $replCode = [];
        $replCode[] = 'switch ($username) {';
        foreach ($usernameData['map'] as $find => $info) {
            $replCode[] = '    case ' . var_export($find, true) . ':';
            if ($info['clubhouse']) {
                $replCode[] = '        // Clubhouse user: ' . $info['clubhouse']['name'];
                $replCode[] = '        return ' . var_export($info['clubhouse']['id'], true) . ';';
            } else {
                $replCode[] = '        // FALLBACK Clubhouse user: ' . $usernameData['fallback']['clubhouse']['name'];
                $replCode[] = '        return ' . var_export($usernameData['fallback']['clubhouse']['id'], true) . ';';
            }
            $replCode[] = '';
        }
        $replCode[] = '}';
        $replCode[] = '';
        $replCode[] = '// Possible Clubhouse values: ';
        foreach ($usernameData['clubhouseData'] as $x) {
            $replCode[] = "// {$x['id']} :: {$x['name']} <{$x['email']}>";
        }
        $replCode[] = '';
        $replCode[] = '// FALLBACK Clubhouse user: ' . $usernameData['fallback']['clubhouse']['name'];
        $replCode[] = 'return ' . var_export($usernameData['fallback']['clubhouse']['id'], true) . ';';

        $mapperCode = str_replace('//METHOD:findUserId', trim(implode("\n", $this->indentLines($replCode, 2))), $mapperCode);

        //-----
        // type
        //-----

        $replCode = [];
        $replCode[] = 'switch ($type) {';
        foreach ($issueTypeData['map'] as $find => $info) {
            $replCode[] = '    case ' . var_export($find, true) . ':';
            if ($info['clubhouse']) {
                $replCode[] = '        return ' . var_export($info['clubhouse']['storyType'], true) . ';';
            } else {
                $replCode[] = '        return null;';
            }
            $replCode[] = '';
        }
        $replCode[] = '}';
        $replCode[] = '';
        $replCode[] = 'return "chore";';

        $mapperCode = str_replace('//METHOD:getStoryType', trim(implode("\n", $this->indentLines($replCode, 2))), $mapperCode);

        //-----
        // states
        //-----

        $replCode = [];
        $replCode[] = 'switch ($workflow) {';
        foreach ($stateDataData['map'] as $find => $info) {
            $replCode[] = '    case ' . var_export($find, true) . ':';
            if ($info['clubhouse']) {
                $replCode[] = '        // Clubhouse state: ' . $info['clubhouse']['name'];
                $replCode[] = '        return ' . var_export($info['clubhouse']['id'], true) . ';';
            } else {
                $replCode[] = '        return null;';
            }
            $replCode[] = '';
        }
        $replCode[] = '}';
        $replCode[] = '';
        $replCode[] = '// Possible Clubhouse values: ';
        foreach ($stateDataData['clubhouseData'] as $x) {
            $replCode[] = "// {$x['id']} :: {$x['name']}";
        }
        $replCode[] = '';
        $replCode[] = 'return null;';

        $mapperCode = str_replace('//METHOD:getWorkflowId', trim(implode("\n", $this->indentLines($replCode, 2))), $mapperCode);
        $mapperCode = str_replace('VALUE:PROJECT_ID', $this->app->getClubhouseProjectId(), $mapperCode);
        $mapperCode = str_replace('VALUE:YOUTRACK_URL', $this->app->getYouTrackUrl(), $mapperCode);

        echo "Writing $mapperPath\n";
        file_put_contents($mapperPath, $mapperCode);
        echo "Done\n";
        echo "!!! You should review and edit this file as necessary..\n";
    }

    private function mapStates(array $ytStates)
    {
        $ch = $this->app->getClubhouseApi();

        $projectRes = $ch->request('get', '/projects/' . $this->app->getClubhouseProjectId());
        $teamId = $projectRes['data']['team_id'];

        $workflowRes = $ch->request('get', '/workflows');
        $workflows = [];
        foreach ($workflowRes['data'] as $w) {
            if ($w['team_id'] == $teamId) {
                foreach ($w['states'] as $state) {
                    $workflows[] = [
                        'id'   => $state['id'],
                        'name' => strtolower($state['name'])
                    ];
                }
            }
        }

        $map = [];

        foreach ($ytStates as $ytState) {
            $targetW = null;

            foreach ($workflows as $w) {
                if (strtolower($ytState) === $w['name']) {
                    $targetW = $w;
                    break;
                }
            }

            $map[strtolower($ytState)] = [
                'youtrack'  => ['State' => $ytState],
                'clubhouse' => $targetW
            ];
        }

        return [
            'map'           => $map,
            'clubhouseData' => $workflows
        ];
    }

    private function mapTypes(array $ytTypes)
    {
        $map = [];

        foreach ($ytTypes as $t) {
            switch (strtolower($t)) {
                case 'bug':
                    $storyType = 'bug';
                    break;

                case 'user story':
                case 'story':
                case 'epic':
                case 'feature':
                    $storyType = 'feature';
                    break;

                default:
                    $storyType = 'chore';
                    break;
            }

            $map[strtolower($t)] = [
                'youtrack'  => ['Type' => $t],
                'clubhouse' => ['storyType' => $storyType]
            ];
        }

        return [
            'map' => $map
        ];
    }

    private function mapUsernames(array $ytUsernames)
    {
        $yt = $this->app->getYouTrackApi();
        $ytUsers = [];

        foreach ($ytUsernames as $username) {
            $res = $yt->request('get', '/admin/user/' . $username);
            if (!empty($res['data']['fullName'])) {
                $ytToName[$username] = $res['data']['fullName'];
            }
            if (!empty($res['data']['email'])) {
                $ytToEmail[$username] = $res['data']['email'];
            }

            $ytUsers[] = [
                'username' => $username,
                'name'     => !empty($res['data']['fullName']) ? strtolower($res['data']['fullName']) : null,
                'email'    => !empty($res['data']['email']) ? strtolower($res['data']['email']) : null,
            ];
        }

        $ch = $this->app->getClubhouseApi();
        $res = $ch->request('get', '/members');
        $chUsers = [];

        if (!empty($res['data'])) {
            $chUsers = array_map(function ($v) {
                return [
                    'id'    => $v['id'],
                    'name'  => strtolower($v['profile']['name']),
                    'email' => strtolower($v['profile']['email_address'])
                ];
            }, $res['data']);
        }

        $map = [];

        foreach ($ytUsers as $ytUser) {
            $targetU = null;

            foreach ($chUsers as $u) {
                if ($ytUser['email'] && $u['email'] && $ytUser['email'] === $u['email']) {
                    $targetU = $u;
                    break;
                }
                if ($ytUser['name'] && $u['name'] && $ytUser['name'] === $u['name']) {
                    $targetU = $u;
                    break;
                }
            }

            $map[strtolower($ytUser['username'])] = [
                'youtrack'  => $ytUser,
                'clubhouse' => $targetU
            ];
        }

        $mapData = [];
        $mapData['map'] = $map;
        $mapData['fallback'] = [
            'youtrack'  => null,
            'clubhouse' => $chUsers[0]
        ];
        $mapData['clubhouseData'] = $chUsers;

        return $mapData;
    }

    /**
     * @return string
     */
    private function mapperTpl()
    {
        return file_get_contents(__DIR__ . '/../Clubhouse/mapper.tpl.php');
    }

    private function indentLines($lines, $levels)
    {
        $indentString = str_repeat('    ', $levels);
        return array_map(function ($l) use ($indentString) {
            return $indentString . $l;
        }, $lines);
    }
}
