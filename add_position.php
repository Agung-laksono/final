<?php
$db = new PDO('sqlite:database/database.sqlite');
$db->exec('ALTER TABLE task_subtasks ADD COLUMN position INTEGER DEFAULT 0');
echo 'Done';
