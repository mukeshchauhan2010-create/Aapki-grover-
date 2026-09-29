<?php
require_once __DIR__.'/config/config.php';

// Destroy the user session completely
session_unset();
session_destroy();

// Redirect to login page
redirect('/');
