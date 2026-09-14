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
            try{$this->db->exec($statement);$ran++;}
            catch(PDOException $e){$code=(int)($e->errorInfo[1]??0);if(in_array($code,[1050,1060,1061,1062,1091,1826],true)){$skipped++;continue;}throw $e;}
        }
        $this->db->prepare('INSERT INTO platform_migrations(migration,executed_by) VALUES (?,?) ON DUPLICATE KEY UPDATE executed_by=VALUES(executed_by),executed_at=NOW()')->execute([$name,$actorId?:null]);
        return ['ran'=>$ran,'skipped'=>$skipped];
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
