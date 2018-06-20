#!/usr/bin/env php
<?php

require(__DIR__.'/../src/inc.php');

$cmd = new App\Command\ClubhouseImportCommand(getApp());
$cmd->run();
