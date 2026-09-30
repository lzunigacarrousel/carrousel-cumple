<?php
declare(strict_types=1);

/** Reads only the four catalog INSERTs. No SQL from the source file is executed. */
final class ImportCatalog
{
    public static function read(string $file): array
    {
        if(!is_file($file))throw new RuntimeException('No se encuentra el SQL de catálogos.');
        $tables=['roles'=>[],'users'=>[],'parks'=>[],'user_assignments'=>[]];
        foreach(file($file,FILE_IGNORE_NEW_LINES) as $line) {
            if(!preg_match('/^INSERT INTO `(roles|users|parks|user_assignments)` VALUES (.*);$/',$line,$match))continue;
            $tables[$match[1]]=array_merge($tables[$match[1]],self::tuples($match[2]));
        }
        if(!$tables['users']||!$tables['parks']||!$tables['roles'])throw new RuntimeException('El archivo no contiene los catálogos esperados de Caja Chica.');
        return $tables;
    }
    private static function tuples(string $text): array
    {
        $rows=[];$row=[];$value='';$quoted=false;$wasQuoted=false;$inRow=false;$length=strlen($text);
        for($i=0;$i<$length;$i++){
            $ch=$text[$i];
            if($quoted){
                if($ch==='\\'){if(++$i >=$length)throw new RuntimeException('SQL incompleto.');$value.=match($text[$i]){'n'=>"\n",'r'=>"\r",'t'=>"\t",'0'=>"\0",'Z'=>chr(26),default=>$text[$i]};}
                elseif($ch==="'"){if(($text[$i+1]??'')==="'"){$value.="'";$i++;}else $quoted=false;}
                else $value.=$ch;
                continue;
            }
            if($ch==="'"){$quoted=true;$wasQuoted=true;continue;}
            if($ch==='('){$row=[];$value='';$inRow=true;continue;}
            if(($ch===','||$ch===')')&&$inRow){$row[]=$wasQuoted?$value:(strtoupper(trim($value))==='NULL'?null:trim($value));$value='';$wasQuoted=false;if($ch===')'){$rows[]=$row;$inRow=false;}continue;}
            if($inRow)$value.=$ch;
        }
        if($quoted||$inRow)throw new RuntimeException('SQL incompleto.');return $rows;
    }
    public static function import(PDO $db,string $file): array
    {
        $t=self::read($file);
        return Database::transaction($db,function()use($db,$t){
            $roles=[];$centers=[];$users=[];$warnings=[];$addedUsers=0;$addedCenters=0;
            $defaults=[
                'ADMINISTRADOR'=>array_keys(Identity::PERMISSIONS),
                'GERENCIA'=>['customers.read','coupons.read','reports.global','audit.read'],
                'SUPERVISOR'=>['customers.read','coupons.read'],
                'PARQUE'=>['customers.read','customers.write','coupons.read','coupons.redeem'],
            ];
            foreach($t['roles'] as $r){$db->prepare('INSERT IGNORE INTO roles(code,name,permissions_json,is_system) VALUES(?,?,?,1)')->execute([$r[1],$r[2],json_encode($defaults[$r[1]]??[])]);$q=$db->prepare('SELECT id FROM roles WHERE code=?');$q->execute([$r[1]]);$roles[(int)$r[0]]=(int)$q->fetchColumn();}
            foreach($t['parks'] as $r){
                $code=trim((string)$r[2]);if($code==='')continue;
                $q=$db->prepare('INSERT IGNORE INTO cost_centers(source_park_id,code,name,park_name,active) VALUES(?,?,?,?,?)');$q->execute([(int)$r[0],$code,$r[3]?:$r[1],$r[1],(int)$r[4]]);$addedCenters+=$q->rowCount();
                $q=$db->prepare('SELECT id FROM cost_centers WHERE code=?');$q->execute([$code]);$centers[(int)$r[0]]=(int)$q->fetchColumn();
            }
            $assignments=[];$supervised=[];$now=date('Y-m-d H:i:s');
            foreach($t['user_assignments'] as $a){if($a[7]!=='ACTIVE'||($a[5]&&$a[5]>$now)||($a[6]&&$a[6]<=$now))continue;$uid=(int)$a[1];$cid=$centers[(int)$a[3]]??null;if($cid)$assignments[$uid][$cid]=true;if($cid&&$a[2])$supervised[(int)$a[2]][$cid]=true;}
            foreach($t['users'] as $r){
                $source=(int)$r[0];$q=$db->prepare('SELECT id FROM users WHERE source_user_id=?');$q->execute([$source]);$existing=$q->fetchColumn();if($existing){$users[$source]=(int)$existing;continue;}
                $email=strtolower(trim((string)$r[1]));$valid=(bool)filter_var($email,FILTER_VALIDATE_EMAIL);$active=$valid&&$r[4]==='ACTIVE'&&(int)$r[5]===1&&empty($r[11]);
                $assigned=array_keys($assignments[$source]??[]);$center=count($assigned)===1?$assigned[0]:null;
                if(!$valid)$warnings[]="Usuario origen $source sin correo válido: importado inactivo.";
                if(!$center)$warnings[]="Usuario origen $source sin centro operativo único: requiere revisión.";
                if(!isset($roles[(int)$r[3]]))throw new RuntimeException('Rol de origen no encontrado.');
                $q=$db->prepare('INSERT INTO users(source_user_id,name,email,role_id,center_id,active) VALUES(?,?,?,?,?,?)');$q->execute([$source,$r[2],$valid?$email:null,$roles[(int)$r[3]],$center,$active?1:0]);$users[$source]=(int)$db->lastInsertId();$addedUsers++;
                foreach(array_unique(array_merge($assigned,array_keys($supervised[$source]??[]))) as $cid)$db->prepare('INSERT IGNORE INTO user_centers(user_id,center_id) VALUES(?,?)')->execute([$users[$source],$cid]);
            }
            $summary=['users_added'=>$addedUsers,'centers_added'=>$addedCenters,'users_total'=>count($users),'centers_total'=>count(array_unique($centers)),'warnings'=>$warnings];
            Database::audit($db,null,'CATALOG_IMPORTED','catalog',null,['users_added'=>$addedUsers,'centers_added'=>$addedCenters]); return $summary;
        });
    }
}
