<?php

/** Executes numbered SQL migrations exclusively from the platform support portal. */
class MigrationRunnerService
{
    public function __construct(private PDO $db)
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS platform_migrations (
            migration VARCHAR(190) PRIMARY KEY, executed_by INT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function list(): array
    {
        $applied=array_flip($this->db->query('SELECT migration FROM platform_migrations')->fetchAll(PDO::FETCH_COLUMN));
        $rows=[];foreach(glob(ROOT_PATH.'/databases/migrations/*.sql')?:[] as $path){$name=basename($path);$rows[]=['name'=>$name,'applied'=>isset($applied[$name])];}
        usort($rows,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));return $rows;
    }

    public function run(string $name,int $actorId): array
    {
        $name=basename($name);
        if(!preg_match('/^\d+_[a-z0-9_.-]+\.sql$/i',$name))throw new InvalidArgumentException('Invalid migration.');
        $path=ROOT_PATH.'/databases/migrations/'.$name;
        if(!is_file($path))throw new RuntimeException('Migration file not found.');
        $sql=(string)file_get_contents($path);
        if(stripos($sql,'DELIMITER')!==false)throw new RuntimeException('DELIMITER migrations must be run from the deployment CLI.');
        $ran=0;$skipped=0;
        foreach($this->split($sql) as $statement){
            try{$this->executeStatement($statement);$ran++;}
            catch(PDOException $e){$code=(int)($e->errorInfo[1]??0);if(in_array($code,[1050,1060,1061,1062,1091,1826],true)){$skipped++;continue;}throw new RuntimeException($name.' failed: '.$e->getMessage(),0,$e);}
        }
        $this->db->prepare('INSERT INTO platform_migrations(migration,executed_by) VALUES (?,?) ON DUPLICATE KEY UPDATE executed_by=VALUES(executed_by),executed_at=NOW()')->execute([$name,$actorId?:null]);
        return ['ran'=>$ran,'skipped'=>$skipped];
    }

    public function coreSchemaReady(): bool
    {
        try{
            $tables=(int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('roles','users','tenants')")->fetchColumn();
            return $tables===3&&(int)$this->db->query('SELECT COUNT(*) FROM roles')->fetchColumn()>0;
        }catch(Throwable $e){return false;}
    }

    public function supportSchemaReady(): bool
    {
        try{
            $columns=(int)$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME IN ('enabled_modules','offline_enabled','enabled_pages','settings_revision')")->fetchColumn();
            return $columns===4;
        }catch(Throwable $e){return false;}
    }

    /** Brand-new hosting bootstrap: install the baseline, then current POS additions. */
    public function installInitialSchema(): array
    {
        if($this->coreSchemaReady())throw new RuntimeException('The core database is already installed.');
        $path=ROOT_PATH.'/databases/full_schema.sql';if(!is_file($path))throw new RuntimeException('Initial schema file not found on the server.');
        $base=$this->executeSql((string)file_get_contents($path));
        $latest=$this->runRequiredSupportMigrations(0);
        return ['ran'=>$base['ran']+$latest['ran'],'skipped'=>$base['skipped']+$latest['skipped']];
    }

    /** Bring an existing hosted database up to the support-portal release. */
    public function runRequiredSupportMigrations(int $actorId=0): array
    {
        $ran=0;$skipped=0;
        $names=[];
        foreach(glob(ROOT_PATH.'/databases/migrations/*.sql')?:[] as $path){$name=basename($path);$number=(int)strtok($name,'_');if($number>=36)$names[]=$name;}
        usort($names,'strnatcasecmp');
        foreach($names as $name){
            $result=$this->run($name,$actorId);$ran+=$result['ran'];$skipped+=$result['skipped'];
        }
        return ['ran'=>$ran,'skipped'=>$skipped];
    }

    private function executeSql(string $sql): array
    {
        if(stripos($sql,'DELIMITER')!==false)throw new RuntimeException('The schema contains unsupported DELIMITER statements.');
        $ran=0;$skipped=0;
        foreach($this->split($sql) as $statement){
            try{$this->executeStatement($statement);$ran++;}
            catch(PDOException $e){$code=(int)($e->errorInfo[1]??0);if(in_array($code,[1050,1060,1061,1062,1091,1826],true)){$skipped++;continue;}throw $e;}
        }
        return ['ran'=>$ran,'skipped'=>$skipped];
    }

    private function executeStatement(string $sql): void
    {
        $statement=$this->db->prepare($sql);$statement->execute();
        try{while($statement->nextRowset()){}}
        catch(PDOException $e){if((int)($e->errorInfo[1]??0)!==0)throw $e;}
        $statement->closeCursor();
    }

    private function split(string $sql): array
    {
        $out=[];$buffer='';$quote=null;$line=false;$block=false;$len=strlen($sql);
        for($i=0;$i<$len;$i++){$c=$sql[$i];$n=$i+1<$len?$sql[$i+1]:'';
            if($line){$buffer.=$c;if($c==="\n")$line=false;continue;}
            if($block){$buffer.=$c;if($c==='*'&&$n==='/'){$buffer.=$n;$i++;$block=false;}continue;}
            if($quote!==null){$buffer.=$c;if($c==='\\'&&$n!==''){$buffer.=$n;$i++;continue;}if($c===$quote)$quote=null;continue;}
            if($c==='-'&&$n==='-'&&($i+2>=$len||ctype_space($sql[$i+2]))){$buffer.=$c.$n;$i++;$line=true;continue;}
            if($c==='#'){$buffer.=$c;$line=true;continue;}
            if($c==='/'&&$n==='*'){$buffer.=$c.$n;$i++;$block=true;continue;}
            if(in_array($c,["'",'"','`'],true)){$buffer.=$c;$quote=$c;continue;}
            if($c===';'){$trim=trim($buffer);if($trim!==''&&trim(preg_replace('/^\s*(--|#).*$/m','',$trim))!=='')$out[]=$trim;$buffer='';continue;}
            $buffer.=$c;
        }
        $trim=trim($buffer);if($trim!=='')$out[]=$trim;return $out;
    }
}
