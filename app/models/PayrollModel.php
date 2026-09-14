<?php
namespace Models;

class PayrollModel extends Model
{
    protected string $table = 'employees';

    public function __construct(?\PDO $db = null)
    {
        parent::__construct($db);
        $this->ensureSchema();
        new FinanceModel($this->db);
    }

    public function employees(): array
    {
        $st=$this->db->prepare("SELECT e.*,u.username login_name FROM employees e LEFT JOIN users u ON u.id=e.user_id WHERE e.tenant_id=? AND e.is_active=1 ORDER BY e.name");
        $st->execute([\TenantContext::tenantId()]);
        $rows=$st->fetchAll();
        foreach($rows as &$row) $row['commission_due']=$this->commissionBalance((int)($row['user_id']??0));
        return $rows;
    }

    public function addEmployee(array $in): array
    {
        $name=trim((string)($in['name']??''));
        if($name==='') return ['ok'=>false,'error'=>'Enter the employee name.'];
        $id=$this->insert([
            'tenant_id'=>\TenantContext::tenantId(),'name'=>$name,
            'job_title'=>trim((string)($in['job_title']??''))?:'Employee',
            'phone'=>trim((string)($in['phone']??''))?:null,
            'salary_amount'=>max(0,(float)($in['salary_amount']??0)),
            'pay_day'=>min(31,max(1,(int)($in['pay_day']??1))),
            'commission_rate'=>max(0,min(100,(float)($in['commission_rate']??0))),
            'user_id'=>(int)($in['user_id']??0)?:null,'is_active'=>1,
        ]);
        return ['ok'=>true,'id'=>$id,'error'=>null];
    }

    public function pay(int $employeeId,array $in,int $userId): array
    {
        $st=$this->db->prepare('SELECT * FROM employees WHERE id=? AND tenant_id=? LIMIT 1');
        $st->execute([$employeeId,\TenantContext::tenantId()]);$e=$st->fetch();
        if(!$e) return ['ok'=>false,'error'=>'Employee not found.'];
        $salary=max(0,(float)($in['salary_amount']??$e['salary_amount']));
        $commission=max(0,(float)($in['commission_amount']??0));
        $due=$this->commissionBalance((int)($e['user_id']??0));
        if($commission>$due+0.01) return ['ok'=>false,'error'=>'Commission payment is above the employee’s unpaid commission.'];
        $total=round($salary+$commission,2);
        if($total<=0) return ['ok'=>false,'error'=>'Enter a salary or commission payment.'];
        $period=trim((string)($in['pay_period']??date('F Y')));
        $date=trim((string)($in['paid_on']??date('Y-m-d')));
        try{
            $this->db->beginTransaction();
            $this->db->prepare("INSERT INTO payroll_payments(tenant_id,employee_id,pay_period,salary_amount,commission_amount,total_amount,paid_on,payment_method,created_by) VALUES(?,?,?,?,?,?,?,?,?)")
                ->execute([\TenantContext::tenantId(),$employeeId,$period,$salary,$commission,$total,$date,trim((string)($in['payment_method']??'cash')),$userId]);
            $paymentId=(int)$this->db->lastInsertId();
            $this->db->prepare("INSERT INTO finance_entries(tenant_id,entry_type,category,description,amount,payment_method,reference,entry_date,created_by) VALUES(?,'expense','Staff Payroll',?,?,?,?,?,?)")
                ->execute([\TenantContext::tenantId(),$e['name'].' · '.$period,$total,trim((string)($in['payment_method']??'cash')),'PAY-'.$paymentId,$date,$userId]);
            $this->db->commit();return ['ok'=>true,'id'=>$paymentId,'error'=>null];
        }catch(\Throwable $x){if($this->db->inTransaction())$this->db->rollBack();return ['ok'=>false,'error'=>'Could not record payroll payment.'];}
    }

    public function payments(): array
    {
        $st=$this->db->prepare('SELECT p.*,e.name,e.job_title FROM payroll_payments p JOIN employees e ON e.id=p.employee_id WHERE p.tenant_id=? ORDER BY p.paid_on DESC,p.id DESC LIMIT 300');
        $st->execute([\TenantContext::tenantId()]);return $st->fetchAll();
    }

    public function availableUsers(): array
    {
        $st=$this->db->prepare('SELECT id,username FROM users WHERE tenant_id=? AND is_active=1 ORDER BY username');
        $st->execute([\TenantContext::tenantId()]);return $st->fetchAll();
    }

    public function commissionBalance(int $userId): float
    {
        if($userId<=0)return 0.0;
        $st=$this->db->prepare("SELECT
          (SELECT COALESCE(SUM(oi.commission_amount),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.tenant_id=oi.tenant_id WHERE oi.tenant_id=? AND oi.added_by=? AND o.status<>'void')
          +(SELECT COALESCE(SUM(a.commission_total),0) FROM service_appointments a WHERE a.tenant_id=? AND a.staff_id=? AND a.status<>'cancelled')
          -(SELECT COALESCE(SUM(p.commission_amount),0) FROM payroll_payments p JOIN employees e ON e.id=p.employee_id WHERE p.tenant_id=? AND e.user_id=?)");
        $tid=\TenantContext::tenantId();$st->execute([$tid,$userId,$tid,$userId,$tid,$userId]);
        return max(0,round((float)$st->fetchColumn(),2));
    }

    private function ensureSchema(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS employees(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,user_id INT NULL,name VARCHAR(160) NOT NULL,job_title VARCHAR(100) NOT NULL DEFAULT 'Employee',phone VARCHAR(40) NULL,salary_amount DECIMAL(12,2) NOT NULL DEFAULT 0,pay_day TINYINT NOT NULL DEFAULT 1,commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0,is_active TINYINT(1) NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_employee_tenant(tenant_id,is_active),KEY idx_employee_user(tenant_id,user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try{$this->db->query('SELECT commission_rate FROM employees LIMIT 1');}catch(\PDOException $e){$this->db->exec('ALTER TABLE employees ADD COLUMN commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER pay_day');}
        $this->db->exec("CREATE TABLE IF NOT EXISTS payroll_payments(id INT AUTO_INCREMENT PRIMARY KEY,tenant_id INT NOT NULL,employee_id INT NOT NULL,pay_period VARCHAR(80) NOT NULL,salary_amount DECIMAL(12,2) NOT NULL DEFAULT 0,commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0,total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,paid_on DATE NOT NULL,payment_method VARCHAR(30) NOT NULL DEFAULT 'cash',created_by INT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_payroll_tenant(tenant_id,paid_on),KEY idx_payroll_employee(tenant_id,employee_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
