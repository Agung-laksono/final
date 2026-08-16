<?php
$html = file_get_contents('http://localhost:8000/debug-dock');
$html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $html);
echo $html;
