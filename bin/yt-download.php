#!/usr/bin/env php
<?php

require(__DIR__.'/../src/inc.php');

$cmd = new App\Command\YtDownloadCommand(getApp());
$cmd->run();
