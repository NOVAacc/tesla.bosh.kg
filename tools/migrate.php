<?php
declare(strict_types=1);
// CLI-only schema migrator. Execute outside the site's document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$base = dirname(__DIR__);
$configFile = getenv('EV_PRIVATE_ENV') ?: '/home/g143326/.tesla-ev.env';
if (!is_file($configFile) || !is_readable($configFile)) {
    fwrite(STDERR, "Private DB config missing or unreadable\n"); exit(1);
}
$config = [];
foreach (file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$k,$v] = explode('=', $line, 2);
    $k = trim($k);
    if (preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) $config[$k] = trim(trim($v), "'\"");
}
foreach (['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $key) {
    if (!isset($config[$key]) || $config[$key] === '') {
        fwrite(STDERR,"Missing database configuration key: $key\n"); exit(1);
    }
}
try {
    $db = new PDO(
        'mysql:host='.$config['DB_HOST'].';port='.($config['DB_PORT'] ?? '3306').';dbname='.$config['DB_DATABASE'].';charset=utf8mb4',
        $config['DB_USERNAME'], $config['DB_PASSWORD'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES=>false]
    );
    $lock = $db->query("SELECT GET_LOCK('tesla_ev_schema_migrations', 15)")->fetchColumn();
    if ((int)$lock !== 1) throw new RuntimeException('Migration lock unavailable');
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(180) NOT NULL PRIMARY KEY,
            checksum CHAR(64) NOT NULL,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $paths=glob($base.'/database/[0-9][0-9][0-9]_*.sql') ?: [];
        sort($paths,SORT_STRING);
        foreach ($paths as $path) {
            $name=basename($path);
            $sql=file_get_contents($path);
            if ($sql===false) throw new RuntimeException('Unreadable migration: '.$name);
            $checksum=hash('sha256',$sql);
            $find=$db->prepare('SELECT checksum FROM schema_migrations WHERE name=?');
            $find->execute([$name]);
            $prior=$find->fetchColumn();
            if ($prior!==false) {
                if (!hash_equals((string)$prior,$checksum)) {
                    throw new RuntimeException('Previously applied migration changed: '.$name);
                }
                echo "SKIP $name\n"; continue;
            }
            echo "APPLY $name\n";
            // Existing repository migrations have one SQL statement per terminating semicolon.
            // This lexer respects semicolons in quoted strings and strips SQL line comments.
            $buf=''; $quote=''; $escaped=false;
            $lines=preg_split('/\r?\n/', $sql) ?: [];
            $statements=[];
            foreach ($lines as $line) {
                if ($quote==='' && preg_match('/^\s*--(?:\s|$)/',$line)) continue;
                $line.="\n";
                for($i=0,$n=strlen($line);$i<$n;$i++){
                    $c=$line[$i];
                    if ($quote!=='') {
                        $buf.=$c;
                        if ($escaped) { $escaped=false;continue; }
                        if ($c==='\\') { $escaped=true;continue; }
                        if ($c===$quote) {
                            if ($i+1<$n && $line[$i+1]===$quote) { $buf.=$line[++$i];continue; }
                            $quote='';
                        }
                        continue;
                    }
                    if ($c==="'" || $c==='"' || $c==='`') { $quote=$c;$buf.=$c;continue; }
                    if ($c===';') {
                        if (trim($buf)!=='') $statements[]=trim($buf);
                        $buf='';continue;
                    }
                    $buf.=$c;
                }
            }
            if ($quote!=='') throw new RuntimeException('Unclosed SQL quote in '.$name);
            if (trim($buf)!=='') $statements[]=trim($buf);
            if (!$statements) throw new RuntimeException('Empty migration '.$name);
            // MySQL DDL commits implicitly. If any statement fails, stop and investigate
            // before retrying; do not claim rollback of already executed DDL.
            foreach ($statements as $statement) $db->exec($statement);
            $insert=$db->prepare('INSERT INTO schema_migrations(name,checksum) VALUES (?,?)');
            $insert->execute([$name,$checksum]);
            echo "DONE $name\n";
        }
        echo "MIGRATIONS_OK\n";
    } finally {
        $db->query("SELECT RELEASE_LOCK('tesla_ev_schema_migrations')");
    }
} catch(Throwable $e) {
    fwrite(STDERR,'MIGRATION_FAILED: '.$e->getMessage()."\n");
    exit(1);
}
