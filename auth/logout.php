<?php
require_once __DIR__ . '/../config/config.php';
logout_user();
set_flash('success', 'You have been logged out successfully.');
redirect(BASE_URL . '/index');
