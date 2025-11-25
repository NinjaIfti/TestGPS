<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Location updates channel (admin only)
Broadcast::channel('locations', function ($user) {
    return $user->isAdmin();
});
