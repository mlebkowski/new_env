<?php
  return Array (
    'vhost_path' => '/var/www/serwisy/',
    'vhost_ip' => '*',
    
    'logs_path' => '/var/www/apache2/',
    'config_path' => '/etc/apache/vhost.d/',
    'config_format' => '80_%s.conf',
    'dev_htpasswd' => '/etc/apache/.dev',
    
    'group_default' => 'newmedia',
    'group_apache' => 'apache',
    'user_apache' => 'apache',

    'group_default' => 'dev',
    'group_apache' => 'www',
    'user_apache' => 'apache',
    
    'bin_apachectl' => '/usr/sbin/apache2ctl',
    'bin_htpasswd' => '/usr/bin/htpasswd',
    'bin_svnadmin' => '/usr/bin/svnadmin',
  
    'default_settings' => Array ('v' => true, 'c' => true, 'm' => true),
    
    'temp_domain' => '.q2.newmedialabs.pl',

    'ssl_ip' => '127.0.0.1',
    'cert_path' => '/etc/apache/cert/',
    'cert_default' => 'goldbachinteractive.pl',
    
    
    'env_types' => Array ('dev', 'staging', 'prod'),
    'skel' => Array (
      'htdocs' => 'r',
      'data' => 'rw',
      'cache' => 'rw',
      'tmp' => 'rw',
    ),

    'svn_path' => '/home/svn/',
    'svn_url' => 'https://svn.goldbachinteractive.pl/', 
    'svn_user' => 'svn:svn',
    
    'pma_url' => 'https://pma.goldbachinteractive.pl/',
    'pma_htpasswd' => '/home/puck/tmp/pma.htpasswd',  


// moje, testowe:
    'vhost_path' => '/home/puck/tmp/',
    'config_path' => '/home/puck/tmp/',
    'pma_htpasswd' => '/home/puck/tmp/.pma',
    'svn_path' => '/home/puck/tmp/',



  );
