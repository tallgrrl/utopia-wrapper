<?php

class RequestHandler
{
	static $base;
	static $selector;


	public function RequestHandler()
	{
		throw new Exception("RequestHandler is a static class");
	}

	public static function GetBaseDirectory()
	{
		static $baseDirectory;

		if (!isset($baseDirectory))
			$baseDirectory = dirname(__FILE__) . '/../';

		return $baseDirectory;
	}

	public static function GetSelectorName()
	{
		if (!isset($selector))
			self::$selector = $_REQUEST['__select'];

		if (empty(self::$selector))
			self::$selector = 'index';

		return self::$selector;
	}

	public static function getServiceRegistry($selector)
	{
		$serviceRegistry = parse_ini_file(RequestHandler::GetBaseDirectory() . "/ini/serviceRegistry.ini", true);

		if(array_key_exists($selector, $serviceRegistry))
		{
			return $serviceRegistry[$selector];
		}

		return null;
	}
	public static function removeSlash($selector)
	{
		if(strpos('/', $selector) == 0)
		{
			return substr($selector, 1, strlen ( $selector ) -1);
		}
		else
			return $selector;
	}
	public static function CheckSelectorService($selector)
	{
		return self::GetBaseDirectory() . '/handlers/' . ($selector === false ? self::GetSelectorName() : $selector). '.php';
	}

	public static function splitUp($str)
	{
		if(strpos($str, '|'))
		{
			// if there is a pipe, we will be expecting parameters
			$tmp = explode("|", $str);
			$ret = explode("@", $tmp[0]); // same as without params
			// get params
			$params = explode(",", $tmp[1]);
			$ret['params'] = array();
			foreach($params as $foo)
			{
				$ret['params'][] = $foo;
			}
			return $ret;
		}
		else
			return explode("@", $str);
	}
	public static function prepareParams($map, $params)
	{
		//print_r($map);

		// split up $params[0] by /
		$tmp = explode("/", $params);

		if(count($tmp) != count($map))
		{
			throw new CustomBadRequestException( count($tmp)." parameters specified. ".count($map)." required");

		}
		//print_r($tmp);
		// determine at this point to drop extra params
		// determine at this point to strongtype the params
		$p = array();
		for($i=0;$i<count($map);$i++)
		{
			switch ($map[$i]) {
				case ':string':
					if ((string)$tmp[$i] === $tmp[$i])
						$p[] = (string)$tmp[$i];
					else
						throw new CustomBadRequestException("Param " . $i . " (" . $tmp[$i] . ") is not a type :string");
					break;
				case ':int':
					if (is_numeric($tmp[$i]))
						$p[] = (int)$tmp[$i];
					else
						throw new CustomBadRequestException("Param " . $i . " (" . $tmp[$i] . ") is not a type :int");
					break;
				case ':float':
					if (is_float($tmp[$i]))
						$p[] = (float)$tmp[$i];
					else
						throw new CustomBadRequestException("Param " . $i . " (" . $tmp[$i] . ") is not a type :float");
					break;

				case ':any':
					$p[] = $tmp[$i];
					break;

				default:
					throw new CustomBadRequestException("Param " . $i . " type not supported");
			}
		}
		return array($p);
	}
	public static function GetSelectorFile($selector)
	{

		$selector = self::removeSlash($selector);
		// look in config file for selected service

		// if service exists proceed
		$registry = self::getServiceRegistry($selector);

		if($registry && isset($registry[$_SERVER['REQUEST_METHOD']]))
		{
			//trigger_error("Service $selector Registered and Request Method ".$_SERVER['REQUEST_METHOD']." registered");
			// service has been registered and request method is allowed, now check if it exists
			
			ob_end_clean();
			ob_start();
			try
			{
				// split the method up
				$caller = self::splitUp($registry[$_SERVER['REQUEST_METHOD']]);
				if(isset($caller['params']))
					$params = self::prepareParams($caller['params'], self::GetParams());

				$inc_file =  self::GetBaseDirectory()."services/".$selector."/controllers/".$caller[0].".php";
				if (file_exists($inc_file))
				{
					header('Cache-Control: no-cache');
					header('Pragma: no-cache');

					include ($inc_file);
					// call static function
					//$caller[0]::$caller[1]();
					$cls = "\\$selector\\$caller[0]";
					//$cls::$caller[1]();
					// service autoloader
					self::ServiceAutoloader($selector);

					if(isset($params))
						call_user_func_array([$cls, $caller[1]], $params);
					else
						call_user_func([$cls, $caller[1]]);
					//\home\Homecontroller::get();
				}
				else
				{

					print "Service or controller does not exist";		

				}
			}
			catch (BadRequestException $brex)
			{
				trigger_error("Invalid request: " . $brex->getMessage(), E_USER_WARNING);

				if ($selector != '400')
				{
					header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
					self::ExecuteRequest('400');
					exit;
				}
			}

			while(ob_get_level() > 0)
				ob_end_flush();
		}
	}
	public static function ServiceAutoloader($selector)
	{
		// set up autoloader from class
		$inc_file =  self::GetBaseDirectory()."services/".$selector."/classes/CustomAutoLoader.class.php";
		try
		{
			require_once($inc_file);
		}
		catch (FileNotFoundException $brex)
			{
				trigger_error("Autoloader Does not exist for Service ".$selector.": " . $brex->getMessage(), E_USER_WARNING);

				header($_SERVER['SERVER_PROTOCOL'] . ' 404 Bad Request');
				self::ExecuteRequest('404');
				exit;
				
			}
	}
	public static function SetSelector($newSelector)
	{
		self::$selector = $newSelector;
	}

	public static function GetParams()
	{
		return $_REQUEST['__param'];
	}

	public static function ExecuteRequest($selector = false)
	{

		if ($selector === false)
			$selector = self::GetSelectorName();

		$inc_file = self::GetSelectorFile($selector);
	}
}

class ServiceNotFoundException extends Exception{}
class BadRequestException extends Exception {}
class FileNotFoundException extends Exception {}
class RedirectException extends Exception {}
class FoundElsewhereRedirectException extends Exception {}
class TemporaryRedirectException extends Exception {}
class CustomBadRequestException extends Exception
{
    // Redefine the exception so message isn't optional
    public function __construct($message, $code = 0, Exception $previous = null) {
        // some code
    
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
        self::outputJsonError();
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    public function outputJsonError() {
    	$data = [];
    	$data['Exception'] = "Bad Request Error";
    	$data['SERVER_NAME'] = $_SERVER['SERVER_NAME'];
    	$data['SERVER_PORT'] = $_SERVER['SERVER_PORT'];
    	$data['REQUEST_URI'] = $_SERVER['REQUEST_URI'];
    	$data['REQUEST_TIME'] = $_SERVER['REQUEST_TIME'];
    	$data['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'];
    	$data['message'] = $this->message;
    	header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 2014 05:00:00 GMT');
        header('Content-type: application/json');
        print json_encode ($data);
        exit();
    }
}
?>
