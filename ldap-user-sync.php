<?php
/*
Plugin Name: LDAP User Sync
Plugin URI: https://github.com/ck-ws/ldap-user-sync
Description: Plugin to synchronize users and user data from LDAP to WordPress
Version: 1.0.0
Author: Christoph Kreutzer
Author URI: https://github.com/ck-ws
License: BSD 2-Clause
GitHub Plugin URI: https://github.com/ck-ws/ldap-user-sync
GitHub Branch: master
Domain Path: languages/
Text Domain: ldap-user-sync
*/

if(!defined('WPINC')) die();
define('TXTDOM', 'ldap-user-sync');

/* Setup tasks */
$ldapUserSync = ckwsLdapUserSync::getInstance();

/* class definition */
class ckwsLdapUserSync
{
	protected static $instance = null;

	protected $options = array();

	protected $logger = array();

	protected function __construct()
	{
		$this->options = array(
			'hostname' => array(
				'type' => 'text',
				'name' => __('Hostname', TXTDOM),
				'desc' => __('Hostname of LDAP server', TXTDOM),
			),
			'port' => array(
				'type' => 'text',
				'name' => __('Port', TXTDOM),
				'desc' => __('Port of LDAP server', TXTDOM),
			),
			'binddn' => array(
				'type' => 'text',
				'name' => __('Bind DN', TXTDOM),
				'desc' => __('User to sign in on LDAP', TXTDOM),
			),
			'bindpass' => array(
				'type' => 'text',
				'name' => __('Bind Password', TXTDOM),
				'desc' => __('Password to sign in on LDAP', TXTDOM),
			),
			'basedn' => array(
				'type' => 'text',
				'name' => __('Base DN', TXTDOM),
				'desc' => __('Base DN to search users', TXTDOM),
			),
			'filter' => array(
				'type' => 'text',
				'name' => __('Filter', TXTDOM),
				'desc' => __('LDAP filter for user accounts', TXTDOM),
			),
			'mapping' => array(
				'type' => 'textarea',
				'name' => __('Attribute mapping', TXTDOM),
				'desc' => __('Mapping of LDAP attributes to account data', TXTDOM),
				'default' => "user_login=uid
user_nicename=nickname
nickname=nickname
user_email=mail
display_name=cn
first_name=givenname
last_name=sn",
			),
		);

		if(is_admin())
		{
			add_action('admin_menu', array(&$this, 'setupAdminMenu'));
			register_deactivation_hook( __FILE__, array(&$this, 'disableCron'));
		}

		add_action(TXTDOM . '-cronsync', array(&$this, 'doSync'));
	}

	public static function getInstance()
	{
		if(is_null(self::$instance))
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function setupAdminMenu()
	{
		add_submenu_page('options-general.php', __('LDAP User Sync', TXTDOM), __('LDAP User Sync', TXTDOM), 'manage_options', TXTDOM, array(&$this, 'displayAdminMenu'));
	}

	public function enableCron($interval)
	{
		wp_clear_scheduled_hook(TXTDOM . '-cronsync');
		wp_schedule_event(time(), $interval, TXTDOM . '-cronsync');
	}
	public function disableCron()
	{
		wp_clear_scheduled_hook(TXTDOM . '-cronsync');
	}
	public function cronActive()
	{
		return !!wp_next_scheduled(TXTDOM . '-cronsync');
	}

	protected function getOption($key)
	{
		return get_option(TXTDOM . '_' . $key, null);
	}
	protected function setOption($key, $value)
	{
		return update_option(TXTDOM . '_' . $key, $value);
	}
	protected function delOption($key)
	{
		return delete_option(TXTDOM . '_' . $key);
	}

	public function displayAdminMenu()
	{
		echo '<h1>' . __('LDAP User Sync', TXTDOM) . '</h1>';
		echo '<h2>' . __('Configuration', TXTDOM) . '</h2>';

		$action = $_REQUEST['action'];
		if($action === 'save')
		{
			foreach($this->options as $key => $params)
			{
				if(isset($_REQUEST[$key]))
					$this->setOption($key, $_REQUEST[$key]);
				else
					$this->delOption($key);
			}

			echo '<div class="updated fade"><p>' . __('The settings have been saved.', TXTDOM) . '</p></div>';
		}
		if($action === 'cron')
		{
			$interval = (!empty($_REQUEST['cron_interval'])) ? $_REQUEST['cron_interval'] : 'daily';
			$this->setOption('cron-interval', $interval);

			if(empty($_REQUEST['cron_active']))
			{
				$this->disableCron();
				$this->doSync();
			}
			else
			{
				$this->enableCron($interval);
			}

			echo '<div class="updated fade"><p>' . __('The schedule options have been saved.', TXTDOM) . '</p></div>';
		}

		// display form
		echo '<form method="post">';
		echo '<input type="hidden" name="action" value="save" />';
		echo '<table class="form-table"><tbody>';
		foreach($this->options as $key => $params)
		{
			$val = get_option(TXTDOM . '_' . $key, null);
			if(is_null($val) && isset($params['default'])) $val = $params['default'];
			echo '<tr>';
			echo '<th><label for="' . $key . '">' . $params['name'] . '</label></th>';
			echo '<td>';
			switch($params['type'])
			{
				case 'textarea':
					echo '<textarea name="'.$key.'" id="'.$key.'" rows="5" cols="30" class="regular-text">'.htmlspecialchars($val).'</textarea>';
					echo '<p class="description">' . $params['desc'] . '</p>';
					break;
				default:
					echo '<input type="text" name="'.$key.'" id="'.$key.'" value="'.htmlspecialchars($val).'" class="regular-text" />';
					echo '<span class="description">' . $params['desc'] . '</span>';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save Settings', TXTDOM) . '" /></p>';
		echo '</form>';

		echo '<h2>' . __('Schedule', TXTDOM) . '</h2>';
		echo '<form method="post">';
		echo '<input type="hidden" name="action" value="cron" />';

		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="cron_active">' . __('Schedule active?', TXTDOM) . '</label></th><td><input type="checkbox" name="cron_active" id="cron_active" value="1"' . (($this->cronActive()) ? ' checked="checked"' : '') . ' /></td></tr>';
		echo '<tr><th><label for="cron_interval">' . __('Schedule interval', TXTDOM) . '</label></th><td><select name="cron_interval" id="cron_interval">';
		$wpschedules = wp_get_schedules();
		$activeInterval = $this->getOption('cron-interval');
		foreach($wpschedules as $id => $info)
		{
			echo '<option value="' . $id . '"' . (($id == $activeInterval ) ? ' selected="selected"' : '') . '>' . $info['display'] . '</option>';
		}
		echo '</select></td></tr>';
		echo '</tbody></table>';

		echo '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="' . __('Save Schedule & Sync now!', TXTDOM) . '" /></p>';
		echo '</form>';
		
		echo '<h2>' . __('Log', TXTDOM) . '</h2>';
		echo '<pre>' . $this->getOption('log') . '</pre>';
	}

	public function doSync()
	{
		$mappings = array();
		$allAttrs = array();
		$map = preg_split("/\\r\\n|\\r|\\n/", $this->getOption('mapping'));
		foreach($map as $mapping)
		{
			$assignment = explode('=', $mapping, 2);
			$fallback = explode(',', $assignment[1]);
			$mappings[$assignment[0]] = $fallback;
			$allAttrs = array_merge($allAttrs, $fallback);
		}
		unset($map);

		$conn = ldap_connect($this->getOption('hostname'), $this->getOption('port'));
		if(!$conn) {
			$this->log(__('Connect to LDAP server failed. Please check hostname and port.', TXTDOM));
			return;
		}
		ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
		$this->log(__('Connection to LDAP server succeeded.', TXTDOM));

		$bind = ldap_bind($conn, $this->getOption('binddn'), $this->getOption('bindpass'));
		if(!$bind) {
			ldap_get_option($conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $info);
			$this->log(sprintf(__('Bind to LDAP server failed. Please check Bind DN and password. Additional information: %s', TXTDOM), $info));
			return;
		}
		$this->log(__('Bind to LDAP server succeeded.', TXTDOM));

		$res = ldap_search($conn, $this->getOption('basedn'), $this->getOption('filter'), $allAttrs);
		if(!$res) {
			$this->log(__('Search was not successful. Please check Base DN and filter.', TXTDOM));
			return;
		}
		$this->log(__('Search was successful.', TXTDOM));

		$data = ldap_get_entries($conn, $res);
		if(!$data) {
			$this->log(__('Entries could not be retrieved. Try again later.', TXTDOM));
			return;
		}
		$this->log(sprintf(__('Entries retrieved: %d', TXTDOMAIN), $data['count']));

		$users = array();
		for($i = 0; $i < $data['count']; $i++)
		{
			$res = array();
			foreach($mappings as $key => $attrs)
			{
				foreach($attrs as $attr)
				{
					if(!empty($data[$i][$attr]))
					{
						$res[$key] = $data[$i][$attr][0];
						break;
					}
				}
			}
			$users[] = $res;
		}
		$this->log(__('Entries proceeded. Update users...', TXTDOM));

		$cntSuccess = $cntErrorMail = $cntUnknown = $cntError = 0;
		foreach($users as $user)
		{
			if(empty($user['user_login']))
			{
				$cntUnknown++;
				$this->log(__('An unknown user without user_login was ignored.', TXTDOM));
				continue;
			}
			if(empty($user['user_email']))
			{
				$cntErrorMail++;
				$this->log(sprintf(__('User %s was ignored, no e-mail given.', TXTDOM), $user['user_login']));
				continue;
			}

			$uid = username_exists($user['user_login']);

			if(!$uid && !email_exists($user['user_email']))
			{
				$uid = wp_insert_user($user);
				if(is_wp_error($uid))
				{
					$cntError++;
					$this->log(sprintf(__('Error creating new user %s: %s', TXTDOM), $user['user_login'], $uid->get_error_message()));
				}
				else
				{
					$cntSuccess++;
				}
			}
			elseif($uid && (email_exists($user['user_email']) == $uid || !email_exists($user['user_email'])))
			{
				$user['ID'] = $uid;
				$uid = wp_update_user($user);
				if(is_wp_error($uid))
				{
					$cntError++;
					$this->log(sprintf(__('Error updating user %s: %s', TXTDOM), $user['user_login'], $uid->get_error_message()));
				}
				else
				{
					$cntSuccess++;
				}
			}
			elseif($uid)
			{
				$cntError++;
				$this->log(sprintf(__('Error updating user %s: Given e-mail as already in use for other user.', TXTDOM), $user['user_login']));
			}
			else
			{
				$cntError++;
				$this->log(sprintf(__('Error creating user %s: Given e-mail as already in use for other user.', TXTDOM), $user['user_login']));
			}
		}

		$this->log(sprintf(__('STATISTICS: Users created or updated: %d, unknown users: %d, users without e-mail: %d, errors: %d.', TXTDOM), $cntSuccess, $cntUnknown, $cntErrorMail, $cntError));

		$this->flushLog();
		$this->setOption('lastsync', time());
	}

	protected function log($txtstring)
	{
		$this->logger[] = $txtstring;
	}

	protected function flushLog()
	{
		$this->setOption('log', date('r') . PHP_EOL . implode(PHP_EOL, $this->logger));
		$this->logger = array();
	}
}
