<?php
use Illuminate\Support\Facades\Route;

Route::get('/debug-user', function () {
    $user = auth()->user();
    if (!$user) return "Not logged in";
    
    $html = '<html><body><h1>Current User: ' . $user->name . '</h1>';
    $html .= '<h2>Roles: ' . $user->getRoleNames()->implode(', ') . '</h2>';
    $html .= '<h2>Permissions:</h2><ul>';
    foreach ($user->getAllPermissions() as $p) {
        $html .= '<li>' . $p->name . '</li>';
    }
    $html .= '</ul>';
    
    $checkPerms = ['inventory.stock.create', 'inventory.transfer.view', 'inventory.opname.view', 'production.order.update', 'inventory.production.fulfillment', 'inventory.request.view'];
    $html .= '<h2>Check Specific Permissions via can():</h2><ul>';
    foreach ($checkPerms as $p) {
        $html .= '<li>' . $p . ': ' . ($user->can($p) ? 'YES' : 'NO') . '</li>';
    }
    $html .= '</ul></body></html>';
    
    return $html;
});
