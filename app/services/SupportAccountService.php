<?php

/** First-run setup and authentication helpers for the developer support team. */
class SupportAccountService
{
    public function __construct(private PDO $db) {}

    public function exists(): bool
    {
        $st=$this->db->query("SELECT 1 FROM users u JOIN roles r ON r.id=u.role_id WHERE u.tenant_id IS NULL AND r.role_name='platform_admin' LIMIT 1");
        return (bool)$st->fetchColumn();
    }

    public function create(string $name,string $email,string $password): array
    {
        $errors=[];
        $name=trim($name);$email=strtolower(trim($email));
        if($name==='')$errors['name']='Enter the support account name.';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors['email']='Enter a valid support email address.';
        if(strlen($password)<10)$errors['password']='Use at least 10 characters for the support password.';
        if($errors)return ['ok'=>false,'errors'=>$errors];
        $this->db->beginTransaction();
        try{
            if($this->exists())throw new RuntimeException('A support account already exists. Sign in instead.');
            $this->db->exec("INSERT INTO roles(role_name,scope,capabilities) SELECT 'platform_admin','platform',JSON_ARRAY('*') WHERE NOT EXISTS(SELECT 1 FROM roles WHERE role_name='platform_admin')");
            $this->db->exec("UPDATE roles SET scope='platform',capabilities=JSON_ARRAY('*') WHERE role_name='platform_admin'");
            $role=(int)$this->db->query("SELECT id FROM roles WHERE role_name='platform_admin' LIMIT 1")->fetchColumn();
            $duplicate=$this->db->prepare('SELECT 1 FROM users WHERE email=? LIMIT 1');$duplicate->execute([$email]);
            if($duplicate->fetchColumn())throw new RuntimeException('That email address is already registered.');
            $username=preg_replace('/[^a-z0-9._-]+/i','-',strtolower($name));$username=trim((string)$username,'-')?:'support';
            $base=$username;$i=1;$check=$this->db->prepare('SELECT 1 FROM users WHERE username=? LIMIT 1');
            do{$check->execute([$username]);if(!$check->fetchColumn())break;$username=$base.'-'.(++$i);}while($i<1000);
            $this->db->prepare('INSERT INTO users(tenant_id,username,email,password_hash,role_id,is_active,email_verified) VALUES (NULL,?,?,?,?,1,1)')
                ->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
            $id=(int)$this->db->lastInsertId();$this->db->commit();
            return ['ok'=>true,'id'=>$id,'errors'=>[]];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return ['ok'=>false,'errors'=>['_'=>$e->getMessage()]];}
    }

    public function isSupportUser(array $user): bool
    {
        return ($user['role_name']??'')==='platform_admin'&&($user['tenant_id']??null)===null;
    }
}
