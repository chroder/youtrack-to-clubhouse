#!/usr/bin/env php
<?php

require(__DIR__.'/../src/inc.php');

$cmd = new App\Command\ClubhouseInitMapCommand(getApp());
$cmd->run();
