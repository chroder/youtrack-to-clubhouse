<?php

class YouTrackToClubhouseMapper
{
    private $projectId = 'VALUE:PROJECT_ID';

    /**
     * Given a YouTrack issue, map it to an epic.
     * You should return false/null here if the issue is NOT an epic.
     * When an issue is not an epic, then the issue will then attempt to
     * get mapped to a story with issueAsStory next.
     *
     * Specify an array of YouTrack issue IDs in the special `@epicIssueIds` key
     * and we will auto-map those issues to this epic.
     *
     * @param  array $youtrack YouTrack issue details
     * @return array Epic data
     */
    public function issueAsEpic(array $youtrack)
    {
        if ($youtrack['Type'] !== 'Epic') {
            return null;
        }

        $epic = [
            'external_id' => $youtrack['id'],
            'created_at'  => date('c', $youtrack['created'] / 1000),
            'updated_at'  => $youtrack['updated'] ? date('c', $youtrack['updated'] / 1000) : date('c'),
            'name'        => $youtrack['summary'],
            'description' => !empty($youtrack['description']) ? $youtrack['description'] : '',
        ];

        if (!empty($youtrack['reporterName']) && $reporter = $this->findUserId($youtrack['reporterName'])) {
            $epic['requested_by_id'] = $reporter;
        }
        if (!empty($youtrack['Assignee']) && $assigns = $this->findUserId($youtrack['Assignee'])) {
            $epic['owner_ids'] = $assigns;
        }

        if (!empty($youtrack['issueLinks'])) {
            $issueIds = [];
            foreach ($youtrack['issueLinks'] as $link) {
                if ($link['type'] === 'Subtask') {
                    $issueIds[] = $youtrack['projectId'] . '-' . $link['issueId'];
                }
            }

            if (!empty($issueIds)) {
                $epic['@epicIssueIds'] = $issueIds;
            }
        }

        return $epic;
    }

    /**
     * Given a YouTrack issue, map it to a story
     * .
     * You should return false/null here to skip importing it.
     *
     * @param  array $youtrack YouTrack issue details
     * @return array Epic data
     */
    public function issueAsStory(array $youtrack)
    {
        $issue = [
            'project_id'  => $this->projectId,
            'external_id' => $youtrack['id'],
            'created_at'  => date('c', $youtrack['created'] / 1000),
            'updated_at'  => $youtrack['updated'] ? date('c', $youtrack['updated'] / 1000) : date('c'),
            'name'        => $youtrack['summary'],
            'description' => !empty($youtrack['description']) ? $youtrack['description'] : '',
            'estimate'    => !empty($youtrack['Points']) ? ($youtrack['Points'] ?: null) : null
        ];

        if (!empty($youtrack['reporterName']) && $reporter = $this->findUserId($youtrack['reporterName'])) {
            $issue['requested_by_id'] = $reporter;
        }
        if (!empty($youtrack['Assignee']) && $assigns = $this->findUserId($youtrack['Assignee'])) {
            $issue['owner_ids'] = $assigns;
        }
        if ($storyType = $this->getStoryType($youtrack)) {
            $issue['story_type'] = $storyType;
        }
        if ($workflowId = $this->getWorkflowId($youtrack)) {
            $issue['workflow_state_id'] = $workflowId;
        }
        if ($epicId = $this->getEpicId($youtrack)) {
            $issue['epic_id'] = $epicId;
        }

        $issue['external_tickets'] = [];
        $issue['external_tickets'][] = ['external_id' => $youtrack['id'], 'external_url' => 'VALUE:YOUTRACK_URL/issue/' . $youtrack['id']];

        $issue['comments'] = [];

        foreach ($youtrack['comments'] as $idx => $ytComment) {
            if (empty($youtrack['text'])) {
                continue;
            }
            $issue['comments'][] = [
                'author_id'   => $this->findUserId($ytComment['author']),
                'text'        => $youtrack['text'],
                'external_id' => $youtrack['id'] . '#comment' . $idx,
                'created_at'  => date('c', $ytComment['created'] / 1000)
            ];
        }

        return $issue;
    }

    /**
     * Given a username from YouTrack, get a corresponding user ID in Clubhouse.
     * This MUST return. If you can't locate a suitable user, always have a fallback.
     *
     * @param  string|array $ytUsername Username in YouTrack. May also be an array of usernames
     * @return string Member UUID in Clubhouse
     */
    private function findUserId($ytUsername)
    {
        if (is_array($ytUsername)) {
            $ret = [];
            foreach ($ytUsername as $n) {
                $ret[] = $this->findUserId($n);
            }
            $ret = array_unique($ret);
            return $ret;
        }

        $username = strtolower($ytUsername);

        //METHOD:findUserId
    }

    /**
     * Given a YouTrack issue, get the story type in Clubhouse.
     * The only valid values are: feature, bug, chore
     *
     * @param  array $youtrack YouTrack issue details
     * @return string Clubhouse story type
     */
    private function getStoryType(array $youtrack)
    {
        $type = isset($youtrack['Type']) ? strtolower($youtrack['Type']) : null;
        if (!$type) {
            return 'chore';
        }

        //METHOD:getStoryType
    }

    /**
     * Given a YouTrack issue, get the epic in Clubhouse.
     * If you don't want to assign an epic, then this can be left blank.
     *
     * If the epic was imported from YT, then return YT:$id
     * and we will map it to the correct epic ID in Clubhouse.
     *
     * This is largely handled by issueAsEpic setting @epicIssueIds itself.
     * So you only need this if you want to override/add behaviour.
     *
     * @param  array $youtrack YouTrack issue details
     * @return string Clubhouse epic ID
     */
    private function getEpicId(array $youtrack)
    {
        return null;
    }

    /**
     * Given a YouTrack issue, get the workflow in Clubhouse.
     *
     * @param  array $youtrack YouTrack issue details
     * @return int    Clubhouse workflow id
     */
    private function getWorkflowId(array $youtrack)
    {
        $workflow = isset($youtrack['State']) ? strtolower($youtrack['State']) : null;
        if (!$workflow) {
            return null;
        }

        //METHOD:getWorkflowId
    }
}
