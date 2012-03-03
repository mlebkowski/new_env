<?php

$dev = $env == 'dev';
$prod = $env == 'prod';

$PHP = Array (

  'php_admin_value' => Array (
    'open_basedir' => "$envpath:/tmp/",
//      ((strpos($logs_path, $envpath) === false) ? ":$logs_path" : null),
  ),

  'php_value' => Array (
    'session.save_path' => "$envpath/tmp",
    'upload_tmp_dir' => "$envpath/tmp",
//    'error_log' => $logs_path . ($prod ? '' : "$env-") . "error_log",
    'error_reporting' => $prod ? E_ALL ^ E_NOTICE : E_ALL,
  ),
  'php_flag' => Array (
    'display_errors' => $dev ? 'on' : 'off',
    'log_errors' => $dev ? 'off' : 'on', 
  ),
);

$mod_php = Array ();
$php_fcgi = Array ();

foreach ($PHP as $key => $values) foreach ($values as $k => $v):
  $php_fcgi['SetEnv ' . strtoupper($key)] .= sprintf("%s=%s;", $k, $v);
  $mod_php["$key $k"] = $v;
endforeach;

return Array (
  'SetEnv Env' => $env,
  'SetEnv Debug' => $dev ? 1 : null,
  'SetEnv DB_PASS' => $sql_pass,
  'SetEnv TMP' => "$envpath/tmp",
    
  null,
    
  'DocumentRoot' => "$envpath/htdocs",
  'ServerName' => $prod ? $serverName : "$env.$temp_domain",
  'ServerAlias' => ($prod && empty($cert))? implode(' ',
     Array ('prod.'.$temp_domain, $temp_domain, "www.$domain"))
     : null,
      
  null,
                
  'ErrorLog' => $logs_path . ($prod ? '' : "$env-") . "error_log",
  'CustomLog' => $logs_path . ($prod ? '' : "$env-") . "access_log combined",
    
  null,

  $fastcgi ? Array (
    'ScriptAlias' => "/cgi-bin/ $envpath/cgi-bin/",
    'FastCgiExternalServer' => $cert ? null : "$envpath/cgi-bin/php5.3-fpm -host 127.0.0.1:9000",
    $php_fcgi,
  ) : $mod_php,

  null,

  isset($cert) ? Array (
    'SSLEngine' => 'on',
    'SSLCipherSuite' =>  'ALL:!ADH:!EXPORT56:RC4+RSA:+HIGH:+MEDIUM:+LOW:+SSLv2:+EXP:+eNULL',
    'SSLCertificateFile' => $cert_path . "$cert/server.crt",
    'SSLCertificateKeyFile' => $cert_path . "$cert/server.key",
    'SSLCACertificateFile' => $cert_path . "$cert/ca.crt",
  ) : null,
);
