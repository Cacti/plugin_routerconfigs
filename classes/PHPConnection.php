<?php
require_once(__DIR__ . '/LinePrompt.php');
require_once(__DIR__ . '/Interfaces.php');

abstract class PHPConnection {
	protected $debugbuffer = false;
	protected $use_usleep  = 1;	// change to 1 for faster execution
				// don't change to 1 on Windows servers unless you have PHP 5
	protected $sleeptime   = 125000;
	protected $timeout     = 1; //Seconds to avoid buggies connections

	protected $connection  = NULL; //stores the ssh connection pointer
	protected $stream      = NULL; //points to the ssh session stream
	protected $errorcode   = 0;
	protected $error       = 0;

	protected $debug       = '';
	protected $ip          = '';

	private $lastPrompt = 0;
	private $isEnabled = false;

	private static $knownTypes = array();

	public static function AddType($classType, $groupName) {
		if (!array_key_exists($groupName, PHPConnection::$knownTypes)) {
			PHPConnection::$knownTypes[$groupName] = array();
		}

		PHPConnection::$knownTypes[$groupName][] = $classType;
	}

	public static function GetTypes($wantedGroup = '') {
		$wantedGroup = "$wantedGroup";
		$result = (array_key_exists($wantedGroup, PHPConnection::$knownTypes)) ?
			PHPConnection::$knownTypes[$wantedGroup] :
			array();
		return $result;
	}

	function __construct($classtype, $devicetype, $device, $user, $pass, $enablepw, $bufferDebug = false, $elevated = false) {
		$this->classType = $classtype;

		$this->pw1_text = plugin_routerconfigs_maskpw($pass);
		$this->pw2_text = plugin_routerconfigs_maskpw($enablepw);

		$this->device = $device;
		$this->user = $user;
		$this->pass = $pass;
		$this->enablepw = $enablepw;
		$this->deviceType = $devicetype;

		$this->debug = '';
		$this->debugbuffer = $bufferDebug;
		$this->isEnabled = false;
		$this->isAlwaysEnabled = $elevated;
		$this->setServerDetails();

		$this->Log("DEBUG: Creating $classtype Server: $this->server, User: $this->user, Password: $this->pw1_text, Enablepw: $this->pw2_text, Elevated: $this->isAlwaysEnabled");
		$this->Log("DEBUG: deviceType: ".json_encode($this->deviceType));
	}

	function Log($message) {
		$lines = explode("\r\n", $message);
		if (sizeof($lines)) {
			foreach ($lines as $line) {
				plugin_routerconfigs_log("$this->ip ($this->classType) -> $line");
			}
		}
	}

	protected function setServerDetails() {
		$this->server = $this->device['ipaddress'];

		if (strlen($this->server)) {
			if (preg_match('/[^0-9.]/', $this->server)) {
				$ip = gethostbyname($this->server);
				if ($ip == $this->server) {
					$ip = '';
				}
			} else {
				$ip = $this->server;
			}
		} else {
			$ip = '127.0.0.1';
		}

		$this->ip = $ip;
	}

	function setTimeout($timeout) {
		if (!is_numeric($timeout) || $timeout <= 0) {
			$timeout = 1;
		}

		$this->Log("DEBUG: Setting timeout to $timeout second(s)");
		$this->timeout = $timeout;
	}

	function setSleep($sleep) {
		if (!is_numeric($sleep) || $sleep <= 0) {
			$sleep = 125000;
		}

		$u_sleep = $sleep > 10;

		$this->Log("DEBUG: Setting sleep time to $sleep " . ($u_sleep ? 'micro' : '') . 'second(s)');
		$this->use_usleep = $u_sleep;
		$this->sleeptime = $sleep;
	}

	function getDebug() {
		return $this->debug;
	}

	function ip() {
		return $this->ip;
	}

	function error($value = null) {
		if ($value !== null) {
			$this->error = $value;
		}
		return $this->error;
	}

	function prompt() {
		return $this->lastPrompt;
	}

	function IsEnabled() {
		return $this->isEnabled || $this->isAlwaysEnabled;
	}

	function EnsureEnabled() {
		# Get > to show we are at the command prompt and ready to input the en command
		# Get # to show we are already enabled so we don't need to enable
		$is_enabled = $this->IsEnabled();

		if ($is_enabled) {
			$this->Log('NOTICE: Already enabled, continuing');
			return true;
		} else {
			$this->Log('NOTICE: Ensuring process is enabled');
		}

		$res = '';
		$x = 0;
		while ($x < 10 && $this->prompt() != LinePrompt::Enabled && $this->prompt() != LinePrompt::Normal) {
			$r = '';
			if ($this->prompt() == LinePrompt::AnyKey) {
				$this->Log("DEBUG: AnyKey prompt detected, sending space");
				$this->DoCommand(' ', $r, $this->pass);
			} else {
				$this->DoCommand('', $r, $this->pass);
			}
			$res .= $r;

			$x++;
			$this->Log("DEBUG: Attempt $x of 10 to find prompt");
		}

		if ($x < 10) {
			if ($this->enablepw != '' && !$this->IsEnabled()) {
				$this->Log("DEBUG: Sending enable command");
				@fputs($this->stream, "en\r");

				# Get the password prompt again to input the enable password
				$x = 0;
				while ($x < 10 && $this->Prompt() != LinePrompt::Enabled) {
					$response = '';
					$this->Sleep();
					$this->GetResponse($response);

					if ($this->prompt() == LinePrompt::Normal) {
						$this->DoCommand('en',$response);
					}

					if ($this->prompt() == LinePrompt::Password) {
						$response = '';
						$result = $this->DoCommand($this->enablepw, $response, $this->enablepw);
						if ($result != 0) {
							$this->Log('DEBUG: Enable login failed ('.$result.')');
							$this->Disconnect();
							break;
						}

						if ($this->IsEnabled()) {
							$this->Log('DEBUG: Ok we are in enabled mode');
						}
					}

					$x++;
				}
			} elseif (empty($this->enablepw)) {
				$this->Log('DEBUG: No enable command set, unable to elevate');
			}
		}

		$this->Log('Process is now ' . ( $this->IsEnabled() ? '' : 'NOT ') . 'enabled');
		return $this->IsEnabled();
	}


	function Disconnect() {
		if ($this->stream) {
			$exit = read_config_option('routerconfigs_exit') != 'on';
			if ($exit) {
				$this->DoCommand('exit', $junk);
			}

			fclose($this->stream);
			$this->stream = NULL;
		}
	}

	function startsWith($haystack, $needle)
	{
		$length = strlen($needle);
		return (substr($haystack, 0, $length) === $needle);
	}

	function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		return $length === 0 || (substr($haystack, -$length) === $needle);
	}

	function Sleep() {
		if ($this->use_usleep) {
			usleep($this->sleeptime);
		} else {
			sleep($this->sleeptime);
		}
	}

	function DoCommand($cmd, &$response, $pass = null) {
		$result = 0;
		if ($this->stream) {
			$lines = $cmd;
			if ($pass != null) {
				$pass_text = plugin_routerconfigs_maskpw($pass);
				$lines = str_replace($pass,$pass_text,$lines);
			}
			$lines = explode("\n",$lines);
			foreach ($lines as $line) {
				$this->Log("DEBUG: --> $line");
			}

			@fwrite($this->stream,$cmd.PHP_EOL);
			$this->Sleep();
			$result = $this->GetResponse($response, $pass);
			$response = preg_replace("/^.*?\n(.*)\n([^\n]*)$/", "$2", $response);
		}

		return $result;
	}

	function GetResponse(&$response, $pass = null) {
		$time_start = microtime(true);
		$data = '';

		stream_set_timeout($this->stream, 0, 500000);
		$this->lastPrompt = LinePrompt::None;
		while (true && isset($this->stream)) {
			$buf = @fgets($this->stream);
			if ($buf !== false) {
				if ($pass != null) {
					$buf = str_replace($pass,'__password__',$buf);
				}
				$data .= $buf;
				$response .= $buf;
				$this->debug .= $buf;
				$line_buf = explode("\n",str_replace("\r", "", $buf));

				if ($this->debugbuffer) {
					if (!is_array($line_buf)) {
						$line_buf = array($line_buff);
					}
					foreach ($line_buf as $line) {
						$line = str_replace("`\r","",str_replace("\n","",$line));
						$buf_line = "DEBUG: <-- ";
						$buf_line .= $line;
						$this->Log($buf_line);
					}
				}

				$trim_buf = trim($buf);
				if (preg_match("|[a-z0-9\-_]>[ ]*$|i", $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Normal)');
					$this->isEnabled = false;
					$this->lastPrompt = LinePrompt::Normal;
					return 0;
				} else if (preg_match("|[a-z0-9\-_]#[ ]*$|i", $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Enabled)');
					$this->isEnabled = true;
					$this->lastPrompt = LinePrompt::Enabled;
					return 0;
				} else if (!empty($this->deviceType['promptpass']) &&
					preg_match('/' . $this->deviceType['promptpass'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Password)');
					$this->lastPrompt = LinePrompt::Password;
					return 0;
				} else if (!empty($this->deviceType['promptuser']) &&
					preg_match('/' . $this->deviceType['promptuser'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Username)');
					$this->lastPrompt = LinePrompt::Username;
					return 0;
				} else if (!empty($this->deviceType['promptconfirm']) &&
					preg_match('/' . $this->deviceType['promptconfirm'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Confirm)');
					$this->lastPrompt = LinePrompt::Confirm;
					return 0;
				} else if (!empty($this->deviceType['anykey']) &&
					preg_match('/' . $this->deviceType['anykey'] . '/i', $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (AnyKey)');
					$this->lastPrompt = LinePrompt::AnyKey;
					return 0;
				} else if (stripos($buf, 'Access not permitted.') !== FALSE) {
					$this->Log('DEBUG: Found Prompt (Access Denied)');
					$this->lastPrompt = LinePrompt::AccessDenied;
					return 0;
				} else if (preg_match('/[\d\w\[]\]\?[^\w]*$/i',$buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Question)');
					$this->lastPrompt = LinePrompt::Question;
					return 0;
				} else if (preg_match("|[a-z0-9\-_]:[ ]*$|i", $buf) === 1) {
					$this->Log('DEBUG: Found Prompt (Colon)');
					$this->lastPrompt = LinePrompt::Colon;
					return 0;
				}
			}

			$s = socket_get_status($this->stream);
			if ((microtime(true)-$time_start) > $this->timeout) {
				$this->Log("DEBUG: Timeout of {$this->timeout} seconds has been reached");
				return 8;
			}

		}

		return 0;
	}

}
