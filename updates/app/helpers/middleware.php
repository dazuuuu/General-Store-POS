<?php
// app/helpers/middleware.php

require_once __DIR__ . '/PathConfig.php';

// Include Session model
require_once __DIR__ . '/../models/Session.php';

class Middleware {
    
    /**
     * Require user to be logged in
     */
    public static function requireAuth() {
        SessionModel::start();
        
        if (!SessionModel::has('logged_in') || SessionModel::get('logged_in') !== true) {
            header('Location: ' . public_url('auth/login.php'));
            exit();
        }
    }
    
    /**
     * Require user to be NOT logged in (for login/register pages)
     */
    public static function requireGuest() {
        SessionModel::start();
        
        if (SessionModel::has('logged_in') && SessionModel::get('logged_in') === true) {
            $role = SessionModel::get('role');
            if ($role === 'admin' || $role === 'superadmin') {
                header('Location: ' . public_url('admin/dashboard.php'));
            } else {
                header('Location: ' . public_url('profile/'));
            }
            exit();
        }
    }
    
    /**
     * Require admin access (admin or superadmin)
     */
    public static function requireAdmin() {
        self::requireAuth();
        
        $roleId = SessionModel::get('role_id');
        
        // role_id 1 = superadmin, 2 = admin, 3 = user
        if ($roleId > 2) {
            header('Location: ' . public_url('auth/login.php') . '?error=access_denied');
            exit();
        }
    }
    
    /**
     * Require superadmin only access
     */
    public static function requireSuperAdmin() {
        self::requireAuth();
        
        $roleId = SessionModel::get('role_id');
        
        if ($roleId !== 1) {
            header('Location: ' . public_url('admin/dashboard.php') . '?error=unauthorized');
            exit();
        }
    }
    
    /**
     * Check if current user has specific role
     */
    public static function hasRole($roleName) {
        SessionModel::start();
        $userRole = SessionModel::get('role');
        return $userRole === $roleName;
    }
    
    /**
     * Check if current user is admin (including superadmin)
     */
    public static function isAdmin() {
        SessionModel::start();
        $roleId = SessionModel::get('role_id');
        return $roleId <= 2;
    }
    
    /**
     * Check if current user is superadmin
     */
    public static function isSuperAdmin() {
        SessionModel::start();
        $roleId = SessionModel::get('role_id');
        return $roleId === 1;
    }
    
    /**
     * Get current user's role
     */
    public static function getCurrentRole() {
        SessionModel::start();
        return SessionModel::get('role');
    }
    
    /**
     * Get current user's role ID
     */
    public static function getCurrentRoleId() {
        SessionModel::start();
        return SessionModel::get('role_id');
    }
}
?>