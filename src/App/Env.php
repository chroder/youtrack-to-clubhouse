<?php

namespace App;

class Env
{
    private $config;
    private $ytApi;
    private $chApi;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getConfigDir()
    {
        return realpath(__DIR__ . '/../../config');
    }

    /**
     * @return string
     */
    public function getYouTrackProjectId()
    {
        return $this->config['youtrack_project'];
    }

    /**
     * @return string
     */
    public function getClubhouseProjectId()
    {
        return $this->config['clubhouse_project'];
    }

    /**
     * @return App\YouTrack\Api
     */
    public function getYouTrackApi()
    {
        if ($this->ytApi === null) {
            $this->ytApi = new \App\YouTrack\Api(
                $this->config['youtrack_url'],
                $this->config['youtrack_api_token']
            );
        }

        return $this->ytApi;
    }

    public function getYouTrackUrl()
    {
        return rtrim($this->config['youtrack_url'], '/');
    }

    /**
     * @return App\Clubhouse\Api
     */
    public function getClubhouseApi()
    {
        if ($this->chApi === null) {
            $this->chApi = new \App\Clubhouse\Api(
                $this->config['clubhouse_api_token']
            );
        }

        return $this->chApi;
    }

    /**
     * @return string
     */
    public function getDataDir($forSubpath = null)
    {
        if (!$forSubpath) {
            return rtrim($this->config['data_dir'], '/\\');
        }

        if (is_array($forSubpath)) {
            $forSubpath = implode(DIRECTORY_SEPARATOR, $forSubpath);
        }

        $path = rtrim($this->config['data_dir'], '/\\') . DIRECTORY_SEPARATOR . $forSubpath;

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }
}
