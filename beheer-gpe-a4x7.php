<?php
// Dedicated entry point for the admin panel under a non-obvious path
// Keeps /admin.php inaccessible directly (it will 404 if called directly).
require_once __DIR__ . '/admin/admin.php';

