<?php
$user = current_user();

if ($user) {
    check_user_achievements((int)$user['id']);
}
