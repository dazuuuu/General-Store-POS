<?php
// app/models/Session.php

class SessionModel {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public static function delete($key) {
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        session_destroy();
        $_SESSION = [];
    }
    
    public static function setFlash($key, $message) {
        $_SESSION['flash'][$key] = $message;
    }
    
    public static function getFlash($key) {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}
?>