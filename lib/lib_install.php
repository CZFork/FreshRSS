<?php

define('BCRYPT_COST', 9);

Minz_Configuration::register('default_system', join_path(DATA_PATH, 'config.default.php'));
Minz_Configuration::register('default_user', join_path(USERS_PATH, '_', 'config.default.php'));

function checkRequirements() {
	$php = version_compare(PHP_VERSION, '5.3.3') >= 0;
	$minz = file_exists(join_path(LIB_PATH, 'Minz'));
	$curl = extension_loaded('curl');
	$pdo_mysql = extension_loaded('pdo_mysql');
	$pdo_sqlite = extension_loaded('pdo_sqlite');
	$pdo_pgsql = extension_loaded('pdo_pgsql');
	$pdo = $pdo_mysql || $pdo_sqlite || $pdo_pgsql;
	$pcre = extension_loaded('pcre');
	$ctype = extension_loaded('ctype');
	$dom = class_exists('DOMDocument');
	$xml = function_exists('xml_parser_create');
	$json = function_exists('json_encode');
	$data = DATA_PATH && is_writable(DATA_PATH);
	$cache = CACHE_PATH && is_writable(CACHE_PATH);
	$users = USERS_PATH && is_writable(USERS_PATH);
	$favicons = is_writable(join_path(DATA_PATH, 'favicons'));
	$http_referer = is_referer_from_same_domain();

	return array(
		'php' => $php ? 'ok' : 'ko',
		'minz' => $minz ? 'ok' : 'ko',
		'curl' => $curl ? 'ok' : 'ko',
		'pdo-mysql' => $pdo_mysql ? 'ok' : 'ko',
		'pdo-sqlite' => $pdo_sqlite ? 'ok' : 'ko',
		'pdo-pgsql' => $pdo_pgsql ? 'ok' : 'ko',
		'pdo' => $pdo ? 'ok' : 'ko',
		'pcre' => $pcre ? 'ok' : 'ko',
		'ctype' => $ctype ? 'ok' : 'ko',
		'dom' => $dom ? 'ok' : 'ko',
		'xml' => $xml ? 'ok' : 'ko',
		'json' => $json ? 'ok' : 'ko',
		'data' => $data ? 'ok' : 'ko',
		'cache' => $cache ? 'ok' : 'ko',
		'users' => $users ? 'ok' : 'ko',
		'favicons' => $favicons ? 'ok' : 'ko',
		'http_referer' => $http_referer ? 'ok' : 'ko',
		'all' => $php && $minz && $curl && $pdo && $pcre && $ctype && $dom && $xml &&
		         $data && $cache && $users && $favicons && $http_referer ?
		         'ok' : 'ko'
	);
}

function generateSalt() {
	return sha1(uniqid(mt_rand(), true).implode('', stat(__FILE__)));
}

function checkDb(&$dbOptions) {
	$dsn = '';
	try {
		$driver_options = null;
		switch ($dbOptions['type']) {
		case 'mysql':
			include_once(APP_PATH . '/SQL/install.sql.mysql.php');
			$driver_options = array(
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
			);
			try {	// on ouvre une connexion juste pour créer la base si elle n'existe pas
				$dsn = 'mysql:host=' . $dbOptions['host'] . ';';
				$c = new PDO($dsn, $dbOptions['user'], $dbOptions['password'], $driver_options);
				$sql = sprintf(SQL_CREATE_DB, $dbOptions['base']);
				$res = $c->query($sql);
			} catch (PDOException $e) {
				syslog(LOG_DEBUG, 'FreshRSS MySQL warning: ' . $e->getMessage());
			}
			// on écrase la précédente connexion en sélectionnant la nouvelle BDD
			$dsn = 'mysql:host=' . $dbOptions['host'] . ';dbname=' . $dbOptions['base'];
			break;
		case 'sqlite':
			include_once(APP_PATH . '/SQL/install.sql.sqlite.php');
			$dsn = 'sqlite:' . join_path(USERS_PATH, $dbOptions['default_user'], 'db.sqlite');
			$driver_options = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			);
			break;
		case 'pgsql':
			include_once(APP_PATH . '/SQL/install.sql.pgsql.php');
			$driver_options = array(
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			);
			try {	// on ouvre une connexion juste pour créer la base si elle n'existe pas
				$dsn = 'pgsql:host=' . $dbOptions['host'] . ';dbname=postgres';
				$c = new PDO($dsn, $dbOptions['user'], $dbOptions['password'], $driver_options);
				$sql = sprintf(SQL_CREATE_DB, $dbOptions['base']);
				$res = $c->query($sql);
			} catch (PDOException $e) {
				syslog(LOG_DEBUG, 'FreshRSS PostgreSQL warning: ' . $e->getMessage());
			}
			// on écrase la précédente connexion en sélectionnant la nouvelle BDD
			$dsn = 'pgsql:host=' . $dbOptions['host'] . ';dbname=' . $dbOptions['base'];
			break;
		default:
			return false;
		}
	} catch (PDOException $e) {
		$dsn = '';
		$dbOptions['error'] = $e->getMessage();
	}
	$dbOptions['dsn'] = $dsn;
	$dbOptions['options'] = $driver_options;
	return $dsn != '';
}

function deleteInstall() {
	$path = join_path(DATA_PATH, 'do-install.txt');
	@unlink($path);
	return !file_exists($path);
}
