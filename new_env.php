#!/usr/bin/php
<?php

$options = Array (
  'v' => 'Create virtual host', 
  'c' => 'Add SSL certificate',
  'f' => 'Create FTP account',

  'm' => 'Create MySQL database',
  'p' => 'Grant PHPmyAdmin access',

  's' => 'Create subversion repository',
);

$conf = load('config');

$settings = $conf['default_settings'];

$argc = $_SERVER['argc'];
if ($argc <= 1) help();

$domain = $_SERVER['argv'][$argc-1];
if (($domain[0] == '-')) help();

list ($name) = explode('.', $domain);

$keys = array_keys($options);
$keys_str = implode("", $keys) . strtoupper(implode("", $keys));

foreach (getopt('012n:g:') as $p => $value)
  switch ((string)$p):
  case 'g': $group = $value; break;
  case 'n': $name = $value; break;

  case '0': $settings = Array (); break;
  case '1': $settings = Array ('v' => true, 'c' => true, 'm' => true); break;
  case '2': $settings = Array ('v' => true, 'f' => true, 'm' => true, 'p' => true); break;

  case 'h': case '?': help();

  endswitch;

foreach (getopt($keys_str) as $p => $value) 
  $settings[strtolower($p)] = ($p == strtolower($p));


// 12 alphanum characters
$name = substr(preg_replace('/[^a-z0-9-]/', '', $name), 0, 12);

if (strpos($domain, '.') <= 0) $domain .= $conf['temp_domain'];
$temp_domain = $name . $conf['temp_domain'];
$is_temp = $temp_domain == $domain;

// random alphanum password, various length
$pass = get_random_pass(12);
$sql_pass = get_random_pass(12);




/*****************************************************************************/
// Sum up settings

echo "Summary: \n";

print_table_row("Domain", $domain);
$is_temp || print_table_row("Temporary domain", $temp_domain);
print_table_row("Short name", $name);

foreach ($options as $p => $description)
  print_table_row($description, !empty($settings[$p]));

read_password("\nContinue (^C to exit)? ");

// specjalnie traktujemy konta wewnetrzne, bez FTP
$special = empty($settings['f']);

$progress = Array ();

/*****************************************************************************/
// Create vhost

if (!empty($settings['v'])):
  print_progress("Creating vhost: $domain");

  $path = $conf['vhost_path'] . $domain;
  if (is_dir($path)):
    print_error("Project env exists! Skipping this step");
    $settings['v'] = false;
  else:
    
    $progress[] = Array ('Site URL', 'http://' . $domain);
    if (!$is_temp)
      $progress[] = Array ('Temporary URL: ', 'http://' . $temp_domain);

    mkdir($path);
    chmod($path, 0750);
    chgrp($path, $conf['group_apache']);


    $dir_conf = load('directory', true);
    $apache_config = build_block('Directory', $path, $dir_conf);
    
    if ($special): foreach ($conf['env_types'] as $env):
      $progress[] = Array ("Env $env", "http://$env.$temp_domain");
      
      $envpath = "$path/$env";
      $vhost_ip = $conf['vhost_ip'] . ':80';
      $logs_path = $conf['logs_path'];
      $serverName = $domain;
      $dev_htpasswd = $conf['dev_htpasswd'];

      $vhost_conf = load('vhost', true);
      $apache_config .= build_block('VirtualHost', $vhost_ip, $vhost_conf);
    
      if (!empty($settings['c'])) if ($env == 'prod'):
        $cert = is_bool($settings['c']) ? $conf['cert_default'] : $settings['c'];
        $cert_path = $conf['cert_path'];
        $serverName = "$name.$cert"; // XX nie zadziala dla cert klienta
        $ssl_ip = $conf['ssl_ip'] . ':443';

        print_progress("Configuring SSL for $cert domain", 2);
        $progress[] = Array ("SSL env", "https://$serverName");

        
        $vhost_ssl = load('vhost', true);
        $apache_config .= build_block('VirtualHost', $ssl_ip, $vhost_ssl);
      endif;      

      mkdir($envpath, 0770);
      chgrp($envpath, $conf['group_apache']);

      // rwx dla grupy, rw dla apache
      exec_(vsprintf('setfacl -m d:g:%s:rwx -m g:%s:rwx -m d:user:%s:rx -m u:%s:rx %s',
        array_map('escapeshellarg', Array (
          $conf['group_default'], $conf['group_default'],
          $conf['user_apache'], $conf['user_apache'],
          $envpath,
      ))));
        
      foreach ($conf['skel'] as $dir => $perms):
        mkdir("$envpath/$dir", 0770);
        if ($perms == 'rw') exec_(vsprintf('setfacl -m d:u:%s:rwx -m u:%s:rwx %s',
          array_map('escapeshellarg', Array (
            $conf['user_apache'], $conf['user_apache'],
            "$envpath/$dir",
        ))));
      endforeach;

    endforeach;  else:
      mkdir("$path/logs", 0750);
      mkdir("$path/htdocs", 0750);
      mkdir("$path/tmp", 0770);
      
      $serverName = $domain;
      $envpath = $path;
      $env = 'prod';
      $logs_path = "$path/logs/";
      $vhost_ip = $conf['vhost_ip'] . ':80';
      
      $vhost_conf = load('vhost', true);
      $apache_config = build_block('VirtualHost', $vhost_ip, $vhost_conf);
      
      // tu powinna byc grupa "apache" (do odczytu) oraz user "name" (do zapisu)
    endif;

    $progress[] = null;
    
    print_progress('Creating apache config', 2);
    
    $config_path = $conf['config_path'] . sprintf($conf['config_format'], $name);
    file_put_contents($config_path, $apache_config);

    print_progress('Restarting apache', 2);

    // restart apache
    $bin_apachectl = $conf['bin_apachectl'];
    exec_("$bin_apachectl graceful");
  endif;


endif; //v

/*****************************************************************************/
// FTP account

if (!empty($settings['f'])):
  print_progress("Creating FTP account");


  if (empty($path)):
    $path = $conf['vhost_path'] . $domain;
  // useradd will do this, but with what permissions?:
  /*
    print_progress("Home directory does not exist. Creating", 2);
    mkdir($path, 0750);
  */
  endif;
  
  $progress[] = Array ('FTP host', "ftp://$temp_domain");
  $progress[] = Array ('FTP user', $name);
  $progress[] = Array ('FTP password', $pass);
  $progress[] = null;
    
  print_progress("Creating user: $name", 2);
  exec_(vsprintf('useradd -g %s -d %s -s /bin/bash %s &>/dev/null',
    array_map('escapeshellarg', Array (
      $conf['group_apache'],
      $path,
      $name,
  ))));
  
  print_progress("Adding permissions", 2);
  chown($path, $name);
  
  if (is_dir("$path/htdocs")):
    chown("$path/htdocs", $name);
    chgrp("$path/htdocs", $conf['group_apache']);
  endif;
  
  if (is_dir("$path/logs")):
    chgrp("$path/logs", $conf['group_apache']);
  endif;

  if (is_dir("$path/tmp")):
    chown("$path/tmp", $name);
    chgrp("$path/tmp", $conf['group_apache']);
  endif;
  
  print_progress("Setting password", 2);
  exec_(vsprintf('echo %s:%s | chpasswd',
    array_map('escapeshellarg', Array($name, $pass))
  ));
  
endif; //u

/*****************************************************************************/
// MySQL

if (!empty($settings['m'])):
 
  print_progress("Creating MySQL database");
  
  $PDO = null;
  do {
    $mysqlPass = read_password("   Enter MySQL root password: ");
    try {
      $PDO = new PDO('mysql:host=localhost', 'root', $mysqlPass);
    } catch (Exception $E) { print_error($E->getMessage()); }
  } while ($PDO == null);
  
  $stmt = $PDO->query("USE $name;");
  if ($stmt):
//    print_error(print_r($PDO->errorInfo(),1));
    print_error("Database '$name' already exists! Skipping...");
  else:
  
    $progress[] = Array ('MySQL host', 'localhost');
    $progress[] = Array ('MySQL user', $name);
    $progress[] = Array ('MySQL password', $sql_pass);
    
    $databases = Array ($name);
    if ($special) $databases[] = $name . "_PRE";

    $progress[] = Array ('MySQL databases', implode(', ', $databases));
    $progress[] = null;
    
    foreach ($databases as $dbname):
      print_progress("Granting access on $dbname to $name@localhost...", 2);
      $PDO->exec(sprintf('CREATE DATABASE %s;', $dbname));
      $PDO->exec(sprintf(
        'GRANT ALL PRIVILEGES on %s.* to %s@localhost IDENTIFIED BY %s',
        $dbname, $name, $PDO->quote($sql_pass)
      ));
    endforeach;
    
  endif;
  
endif;

/*****************************************************************************/
// PMA

if (!empty($settings['p'])):

  print_progress("Granting access to PHPMyAdmin");
  $progress[] = Array('PHPMyAdmin URL', $conf['pma_url']);
  $progress[] = Array('PHPMyAdmin user', $name);
  $progress[] = Array('PHPMyAdmin password', $sql_pass);
  $progress[] = null;

  $bin_htpasswd = $conf['bin_htpasswd'];
  
  exec_($bin_htpasswd . vsprintf(" -b -m %s %s %s &>/dev/null",
    array_map('escapeshellarg', Array (
      $conf['pma_htpasswd'], $name, $sql_pass
  ))));
  
endif;



/*****************************************************************************/
// Subversion

if (!empty($settings['s'])):
  print_progress('Creating subversion repository');

  $svnpath = $conf['svn_path'] . $name;
  if (is_dir($svnpath)):
    print_error("Repository $name already exists! Skipping...");
  else :
  
    $progress[] = Array ('Subversion URL', $conf['svn_url'] . $name);
    $progress[] = null;
  
    $bin_svnadmin = $conf['bin_svnadmin'];
    exec_($bin_svnadmin . " create " . escapeshellarg($svnpath));
    exec_(vsprintf("chown -R %s %s", array_map('escapeshellarg', Array (
      $conf['svn_user'], $svnpath
    ))));
  endif;
  
endif;

/*****************************************************************************/
// Summary:

if (sizeof($progress)):
  echo "\n" . str_repeat("-", 80) . "\n";

  foreach ($progress as $key => $value) {
    if ($value) print_table_row($value[0], $value[1]);
    else echo "\n";
  }

  echo str_repeat("-", 80) . "\n";

endif;





/*****************************************************************************/
// Helper functions


function help() {
  global $options; 

  echo 'Usage: ' . $_SERVER['argv'][0] . ' [params] domain '. "\n";
  foreach ($options as $p => $value):
    echo "  -$p  $value \n";
  endforeach;

  echo "\n";
  echo "  -0  Clear all settings (use only with other options)\n";
  echo "  -1  Create default internal env\n";
  echo "  -2  Create default client env\n";

  echo "\n";
  echo "  -n name  Specify custom name for project\n";

  echo "  -h  This help screen\n\n";
  exit;
}

function get_random_pass($len) {
  for ($pass = ''; strlen($pass) < $len; ):
    $pass .= base_convert(rand(pow(36, 3), pow(36, 4)), 10, 36);
  endfor;
  return $pass;
}

function read_password($prompt) {
  $cmd = 'read -s -p %s pass && echo -n $pass';
  $pass = shell_exec(sprintf($cmd, escapeshellarg($prompt)));
  echo "\n";
  return $pass;
}

function build_block ($name, $value, $data, $level = 1) {
  return "<$name \"$value\">\n"
    . format_block_data($data, $level) 
    . "</$name>\n\n";
}

function format_block_data($data, $level = 1) {
  $block = '';
  foreach ($data as $key => $value):
    if (is_numeric($key) && $value === null):
      $block .= "\n";
    elseif (is_array($value)):
      if (is_numeric($key)):
        $block .= format_block_data($value, $level);
      else:
        $block .= build_block($key, null, $value, $level + 1);
      endif;
    else:
      if ($value !== null) $block .= str_repeat("  ", $level) 
        . (!is_numeric($key) ? "$key\t" : '') .  "$value \n";
    endif;
  endforeach;
  return $block;
}

function load($__cfg, $__override = false) {
  extract ($GLOBALS, EXTR_SKIP); // fix
  $__data = include dirname(__FILE__) . '/' . $__cfg . '.php';
  if (file_exists($path = '/etc/new_env/' . $__cfg . '.php')):
    $__data = $__override 
      ? (include $path)
      : array_merge_recursive($__data, include $path);
  endif;
  return $__data;
}

function print_table_row($txt, $value, $color = null) {
  if ($color == null) {
    if (is_bool($value)) {
      $color = $value ? '1;32' : '1;31';
      $value = $value ? 'Yes' : 'No';
    } else {
      $color = '1;33';
    }
  }
  
  $value = "\x1B[${color}m$value\x1B[0m";
  
  printf("%-31s : %s \n", $txt, $value);
}
function print_error($txt) {
  if (is_array($txt)) $txt = print_r($txt, true);
  echo "\x1B[1;31m!! $txt \x1B[0m \n";
}
function print_progress($txt, $lvl = 1) {
  echo str_repeat('..', $lvl) . " $txt \n"; 
}

function exec_($str) {
//  echo '   $> ' . $str . "\n";
  echo exec($str);
}
