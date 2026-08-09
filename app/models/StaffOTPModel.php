<?php
// app/models/StaffOTPModel.php
namespace Models;

use PDO;

class StaffOTPModel extends Model
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Generate and save OTP for staff login
     */
    public function generateOTP(int $staffId, int $userId, string $email): string
    {
        // Clean old OTPs
        $this->cleanExpiredOTPs();
        
        $otp = $this->generateOTPCode();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $sql = "INSERT INTO staff_otp_verification (staff_id, user_id, email, otp_code, expires_at) 
                VALUES (:staff_id, :user_id, :email, :otp_code, :expires_at)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':staff_id' => $staffId,
            ':user_id' => $userId,
            ':email' => $email,
            ':otp_code' => $otp,
            ':expires_at' => $expiresAt
        ]);
        
        return $otp;
    }
    
    /**
     * Generate random OTP code
     */
    private function generateOTPCode(): string
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP(string $email, string $otp): ?array
    {
        $sql = "SELECT * FROM staff_otp_verification 
                WHERE email = :email 
                AND otp_code = :otp 
                AND is_verified = 0 
                AND expires_at > NOW()
                ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email, ':otp' => $otp]);
        $record = $stmt->fetch();
        
        if (!$record) {
            return null;
        }
        
        // Mark as verified
        $sql = "UPDATE staff_otp_verification SET is_verified = 1 WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $record['id']]);
        
        return $record;
    }
    
    /**
     * Clean expired OTPs
     */
    private function cleanExpiredOTPs(): void
    {
        $sql = "DELETE FROM staff_otp_verification WHERE expires_at < NOW() OR is_verified = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
    
    /**
     * Send OTP via email
     */
    public function sendOTP(string $email, string $otp, string $name): void
    {
        $mailService = new \Services\MailService();
        
        $subject = "Staff Login OTP - Modern POS";
        $body = "Hello $name,\n\n";
        $body .= "Your OTP code for staff login is: $otp\n\n";
        $body .= "This code is valid for 10 minutes.\n\n";
        $body .= "If you didn't request this, please ignore this email.\n\n";
        $body .= "Regards,\nModern POS Team";
        
        $mailService->send($email, $subject, $body);
    }
}