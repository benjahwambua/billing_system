<?php

// Resolve the shared authentication code relative to this file, not the
// web server's current working directory.
require_once __DIR__ . '/../includes/auth.php';

logoutUser($conn);
