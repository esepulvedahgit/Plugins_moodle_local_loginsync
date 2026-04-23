<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_loggedin',
        'callback'    => '\local_loginsync\observer::on_user_loggedin',
        'priority'    => 200,
        'internal'    => false,
    ],
];
