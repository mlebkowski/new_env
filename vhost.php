<?php

$dev = $env == 'dev';
$prod = $env == 'prod';

return Array (
  'Require' => $prod ? null :'valid-user',
  'SetEnv Env' => $env,
  'SetEnv Debug' => $dev ? 1 : null,
  'SetEnv DB_PASS' => $sql_pass,
  'SetEnv TMP' => "$envpath/tmp",
    
  null,
    
  'DocumentRoot' => "$envpath/htdocs",
  'ServerName' => $prod ? $serverName : "$env.$temp_domain",
  'ServerAlias' => ($prod && empty($cert))? implode(' ', Array ($temp_domain, "www.$domain")) : null,
      
  null,
                
  'ErrorLog' => $logs_path . "$domain-$env-error_log",
  'CustomLog' => $logs_path . "$domain-$env-access_log combined",
    
  null,
  
  'php_value open_basedir' => $envpath,
  'php_value session.save_path' => "$envpath/tmp",
  'php_value upload_tmp_dir' => "$envpath/tmp",
  'php_value error_reporting' => $prod ? E_ALL ^ E_NOTICE : E_ALL,
  'php_flag display_errors' => $dev ? 'on' : 'off',
  'php_flag log_errors' => $dev ? 'off' : 'on', 

  null,

  isset($cert) ? Array (
    'SSLEngine' => 'on',
    'SSLCipherSuite' =>  'ALL:!ADH:!EXPORT56:RC4+RSA:+HIGH:+MEDIUM:+LOW:+SSLv2:+EXP:+eNULL',
    'SSLCertificateFile' => $cert_path . "$cert/server.crt",
    'SSLCertificateKeyFile' => $cert_path . "$cert/server.key",
    'SSLCACertificateFile' => $cert_path . "$cert/ca.crt",
  ) : null,
);
